<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Repositories\EventRepository;
use App\Repositories\GameConfigRepository;
use App\Repositories\SaveRepository;
use App\Services\FarmService;
use App\Services\WeatherService;
use CPE\Framework\AbstractApplication;
use CPE\Framework\AbstractController;

/**
 * Contrôleur de l'API REST de la ferme.
 *
 * Les URLs suivent une hiérarchie lisible : /api/players/{joueur}/inventory.
 * Les réponses sont toujours du JSON, avec un code de statut explicite.
 */
class ApiController extends AbstractController
{
    protected FarmService $farm;
    protected WeatherService $weather;
    protected EventRepository $events;

    public function __construct(AbstractApplication $app)
    {
        parent::__construct($app);

        $this->weather = new WeatherService();
        $this->events = new EventRepository();
        $this->farm = new FarmService(
            new GameConfigRepository(DATA_PATH . '/Config/game_config.json'),
            new SaveRepository(DATA_PATH . '/Saves'),
            $this->weather,
            $this->events
        );
    }

    /**
     * Règles du jeu, transmises telles quelles au moteur JavaScript.
     */
    public function config()
    {
        header('Content-Type: application/json; charset=utf-8');
        readfile(DATA_PATH . '/Config/game_config.json');
    }

    /**
     * GET /api/weather
     */
    public function weather()
    {
        $this->respond(fn () => $this->weather->current());
    }

    /**
     * GET /api/players/{player}/inventory
     */
    public function inventory()
    {
        $this->respond(fn () => $this->farm->inventory($this->player()));
    }

    /**
     * GET /api/players/{player}/buildings
     */
    public function buildings()
    {
        $this->respond(fn () => $this->farm->buildings($this->player()));
    }

    /**
     * GET /api/players/{player}/products/{product}
     */
    public function readProduct()
    {
        $this->respond(fn () => $this->farm->readProduct($this->player(), $this->param('product')));
    }

    /**
     * PUT /api/players/{player}/products/{product}
     */
    public function updateProduct()
    {
        $this->respond(function () {
            $body = $this->body();
            if (!array_key_exists('stock', $body)) {
                throw new ApiException('Le champ "stock" est obligatoire.', 400);
            }

            return $this->farm->updateProductStock(
                $this->player(),
                $this->param('product'),
                $body['stock']
            );
        });
    }

    /**
     * DELETE /api/players/{player}/products/{product}
     */
    public function deleteProduct()
    {
        $this->respond(fn () => $this->farm->discardProduct($this->player(), $this->param('product')));
    }

    /**
     * GET /api/players/{player}/generators/{generator}
     */
    public function readGenerator()
    {
        $this->respond(fn () => $this->farm->readGenerator($this->player(), $this->param('generator')));
    }

    /**
     * PUT /api/players/{player}/generators/{generator}
     */
    public function updateGenerator()
    {
        $this->respond(function () {
            $body = $this->body();
            if (!array_key_exists('level', $body)) {
                throw new ApiException('Le champ "level" est obligatoire.', 400);
            }

            return $this->farm->updateGeneratorLevel(
                $this->player(),
                $this->param('generator'),
                $body['level']
            );
        });
    }

    /**
     * POST /api/players/{player}/buildings/{building}/harvest
     */
    public function harvest()
    {
        $this->respond(fn () => $this->farm->harvest($this->player(), $this->param('building')));
    }

    /**
     * POST /api/players/{player}/buildings/{building}/upgrade
     */
    public function upgrade()
    {
        $this->respond(fn () => $this->farm->upgrade($this->player(), $this->param('building')));
    }

    /**
     * GET /api/events
     */
    public function listEvents()
    {
        $this->respond(function () {
            $after = max(0, (int) ($_GET['after'] ?? 0));
            $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));

            return [
                'events' => $this->events->recent($after, $limit),
                'latestId' => $this->events->latestId(),
            ];
        });
    }

    /**
     * POST /api/events
     */
    public function publishEvent()
    {
        $this->respond(function () {
            $body = $this->body();
            $message = trim((string) ($body['message'] ?? ''));
            if ($message === '') {
                throw new ApiException('Le champ "message" est obligatoire.', 400);
            }

            $type = preg_replace('/[^a-z0-9_-]/i', '', (string) ($body['type'] ?? 'world'));
            $type = $type !== '' ? $type : 'world';

            return $this->events->publish([
                'type' => $type,
                'player' => isset($body['player']) ? (string) $body['player'] : null,
                'message' => $message,
            ]);
        }, 201);
    }

    /**
     * GET /api/events/stream — flux Server-Sent Events.
     */
    public function streamEvents()
    {
        $after = max(0, (int) ($_GET['after'] ?? 0));

        ignore_user_abort(false);
        set_time_limit(0);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('Content-Encoding: identity');
        header('X-Accel-Buffering: no');

        echo "retry: 5000\n\n";
        echo ": flux ouvert\n\n";
        @ob_flush();
        flush();

        $lastPing = time();

        while (!connection_aborted()) {
            // Le journal est petit (100 événements maximum) : le relire à
            // chaque tour évite de rater deux événements écrits dans la même
            // seconde, ce que filemtime() ne distinguerait pas.
            $newEvents = $this->events->recent($after);

            if ($newEvents !== []) {
                foreach ($newEvents as $event) {
                    $after = max($after, (int) $event['id']);
                    echo 'id: ' . $event['id'] . "\n";
                    echo "event: farm\n";
                    echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                }

                @ob_flush();
                flush();
            }

            // Un commentaire périodique permet de détecter la fermeture de la
            // connexion et d'empêcher les proxys de couper un flux silencieux.
            if (time() - $lastPing >= 10) {
                echo ": ping\n\n";
                @ob_flush();
                flush();
                $lastPing = time();
            }

            usleep(500000);
        }
    }

    protected function player(): string
    {
        return (string) ($this->parameters['player'] ?? '');
    }

    protected function param(string $name): string
    {
        return (string) ($this->parameters[$name] ?? '');
    }

    /**
     * Lit le corps de la requête, en JSON ou en formulaire.
     */
    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $_POST;
        }

        if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                throw new ApiException('Le corps de la requête n\'est pas un objet JSON valide.', 400);
            }

            return $data;
        }

        parse_str($raw, $data);

        return is_array($data) ? $data : [];
    }

    /**
     * Exécute une action et traduit ses erreurs en réponses JSON.
     */
    protected function respond(callable $action, int $status = 200): void
    {
        try {
            $this->json($action(), $status);
        } catch (ApiException $exception) {
            $this->json(['error' => $exception->getMessage()], $exception->status());
        } catch (\Throwable $exception) {
            error_log((string) $exception);
            $this->json(['error' => 'Erreur interne du serveur.'], 500);
        }
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
