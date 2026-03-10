<?php

declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

/**
 * Journal des événements de la ferme, conservé dans un fichier JSON.
 *
 * Le fichier contient les cent derniers événements et un compteur d'identifiants
 * strictement croissant, ce qui permet à un client SSE de reprendre le flux
 * sans recevoir deux fois le même événement.
 */
class EventRepository
{
    private string $path;

    public function __construct(?string $directory = null)
    {
        $directory = $directory ?? DATA_PATH . '/Events';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier des événements : ' . $directory);
        }

        $this->path = rtrim($directory, '/\\') . '/feed.json';

        if (!is_file($this->path)) {
            $this->writeState(['nextId' => 1, 'events' => []]);
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Ajoute un événement et retourne l'événement complet (avec id et date).
     */
    public function publish(array $event): array
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Impossible d\'ouvrir le journal des événements.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Impossible de verrouiller le journal des événements.');
            }

            $state = $this->decode((string) stream_get_contents($handle));
            $event['id'] = (int) $state['nextId'];
            $event['timestamp'] = gmdate('c');
            $state['nextId'] = $event['id'] + 1;
            $state['events'][] = $event;
            $state['events'] = array_slice($state['events'], -100);

            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Impossible d\'encoder le journal des événements.');
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $event;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Événements postérieurs à un identifiant, dans l'ordre chronologique.
     *
     * @return array<int, array>
     */
    public function recent(int $afterId = 0, int $limit = 50): array
    {
        $state = $this->readState();

        $events = array_values(array_filter(
            $state['events'],
            static fn (array $event): bool => (int) $event['id'] > $afterId
        ));

        return array_slice($events, -max(1, $limit));
    }

    public function latestId(): int
    {
        $state = $this->readState();

        return (int) ($state['nextId'] ?? 1) - 1;
    }

    private function readState(): array
    {
        if (!is_file($this->path)) {
            return ['nextId' => 1, 'events' => []];
        }

        $handle = fopen($this->path, 'r');
        if ($handle === false) {
            return ['nextId' => 1, 'events' => []];
        }

        try {
            flock($handle, LOCK_SH);
            $state = $this->decode((string) stream_get_contents($handle));
            flock($handle, LOCK_UN);

            return $state;
        } finally {
            fclose($handle);
        }
    }

    private function writeState(array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d\'initialiser le journal des événements.');
        }
    }

    private function decode(string $contents): array
    {
        $state = json_decode($contents, true);
        if (!is_array($state) || !isset($state['events']) || !is_array($state['events'])) {
            return ['nextId' => 1, 'events' => []];
        }

        $state['nextId'] = max(1, (int) ($state['nextId'] ?? 1));

        return $state;
    }
}
