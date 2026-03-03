<?php

declare(strict_types=1);

use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

require __DIR__ . '/vendor/autoload.php';

/**
 * Serveur WebSocket de la ferme (Workerman).
 *
 * Il diffuse les nouveaux événements du journal (le même fichier que l'API
 * SSE) et accepte des messages texte des clients pour alimenter le fil.
 *
 * Démarrage : php server.php start
 */
$feedPath = getenv('FEED_PATH') ?: __DIR__ . '/Data/Events/feed.json';
$port = (int) (getenv('WS_PORT') ?: 50200);

$worker = new Worker("websocket://0.0.0.0:$port");
$worker->name = 'ferme-websocket';
$worker->count = 1;

$lastMtime = -1;
$lastId = latestEventId($feedPath);

$worker->onConnect = static function (TcpConnection $connection): void {
    $connection->send(json_encode([
        'type' => 'welcome',
        'message' => 'Connecté au flux temps réel de la ferme.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};

/**
 * Diffusion des événements ajoutés au journal par l'API (ou par un autre
 * client WebSocket).
 */
$worker->onWorkerStart = static function (Worker $worker) use ($feedPath, &$lastMtime, &$lastId): void {
    Timer::add(0.5, static function () use ($worker, $feedPath, &$lastMtime, &$lastId): void {
        clearstatcache(true, $feedPath);
        $mtime = @filemtime($feedPath);
        if ($mtime === false || $mtime === $lastMtime) {
            return;
        }

        $lastMtime = $mtime;
        $state = readState($feedPath);

        foreach ($state['events'] as $event) {
            if ((int) $event['id'] <= $lastId) {
                continue;
            }

            $lastId = (int) $event['id'];
            $payload = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($worker->connections as $connection) {
                $connection->send($payload);
            }
        }
    });
};

/**
 * Un client peut publier une brève, qui est enregistrée dans le journal puis
 * diffusée à tous les autres clients.
 */
$worker->onMessage = static function (TcpConnection $connection, string $data) use ($worker, $feedPath): void {
    $payload = json_decode($data, true);
    $message = is_array($payload)
        ? trim((string) ($payload['message'] ?? ''))
        : trim($data);

    if ($message === '') {
        $connection->send(json_encode(['type' => 'error', 'message' => 'Message vide.']));
        return;
    }

    $event = publishEvent($feedPath, $message);

    foreach ($worker->connections as $client) {
        $client->send(json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
};

Worker::runAll();

/**
 * @return array{nextId: int, events: array<int, array>}
 */
function readState(string $path): array
{
    if (!is_file($path)) {
        return ['nextId' => 1, 'events' => []];
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return ['nextId' => 1, 'events' => []];
    }

    try {
        flock($handle, LOCK_SH);
        $state = json_decode((string) stream_get_contents($handle), true);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if (!is_array($state) || !isset($state['events']) || !is_array($state['events'])) {
        return ['nextId' => 1, 'events' => []];
    }

    $state['nextId'] = max(1, (int) ($state['nextId'] ?? 1));

    return $state;
}

function latestEventId(string $path): int
{
    $state = readState($path);

    return (int) ($state['nextId'] ?? 1) - 1;
}

function publishEvent(string $path, string $message): array
{
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        return [
            'type' => 'world',
            'message' => $message,
            'timestamp' => gmdate('c'),
        ];
    }

    try {
        flock($handle, LOCK_EX);
        $state = json_decode((string) stream_get_contents($handle), true);
        if (!is_array($state) || !isset($state['events']) || !is_array($state['events'])) {
            $state = ['nextId' => 1, 'events' => []];
        }

        $event = [
            'type' => 'world',
            'player' => null,
            'message' => $message,
            'id' => (int) ($state['nextId'] ?? 1),
            'timestamp' => gmdate('c'),
        ];
        $state['nextId'] = $event['id'] + 1;
        $state['events'][] = $event;
        $state['events'] = array_slice($state['events'], -100);

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json . PHP_EOL);
            fflush($handle);
        }

        flock($handle, LOCK_UN);

        return $event;
    } finally {
        fclose($handle);
    }
}
