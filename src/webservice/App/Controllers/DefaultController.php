<?php

declare(strict_types=1);

namespace App\Controllers;

use CPE\Framework\AbstractController;

class DefaultController extends AbstractController
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'service' => 'Ferme API',
            'version' => '1.0.0',
            'status' => 'ok',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function error404()
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Ressource introuvable.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
