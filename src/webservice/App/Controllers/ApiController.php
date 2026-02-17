<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameConfigRepository;
use App\Repositories\SaveRepository;
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
    protected GameConfigRepository $config;
    protected SaveRepository $saves;

    public function __construct(AbstractApplication $app)
    {
        parent::__construct($app);

        $this->config = new GameConfigRepository(DATA_PATH . '/Config/game_config.json');
        $this->saves = new SaveRepository(DATA_PATH . '/Saves');
    }

    /**
     * Règles du jeu, transmises telles quelles au moteur JavaScript.
     */
    public function config()
    {
        $path = DATA_PATH . '/Config/game_config.json';

        header('Content-Type: application/json; charset=utf-8');
        readfile($path);
    }

    /**
     * GET /api/players/{player}/inventory
     */
    public function inventory()
    {
        $player = (string) ($this->parameters['player'] ?? '');
        $save = $this->findSave($player);
        if ($save === null) {
            return;
        }

        $this->json($save['inventory']);
    }

    /**
     * Charge la sauvegarde du joueur ou renvoie une erreur 404.
     */
    protected function findSave(string $player): ?array
    {
        if (!SaveRepository::isValidLogin($player) || !$this->saves->exists($player)) {
            $this->json(['error' => 'Joueur inconnu : ' . $player], 404);
            return null;
        }

        return $this->saves->get($player);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
