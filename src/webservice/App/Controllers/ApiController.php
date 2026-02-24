<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Repositories\GameConfigRepository;
use App\Repositories\SaveRepository;
use App\Services\FarmService;
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

    public function __construct(AbstractApplication $app)
    {
        parent::__construct($app);

        $this->farm = new FarmService(
            new GameConfigRepository(DATA_PATH . '/Config/game_config.json'),
            new SaveRepository(DATA_PATH . '/Saves')
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
    protected function respond(callable $action): void
    {
        try {
            $this->json($action());
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
