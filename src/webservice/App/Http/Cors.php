<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Politique CORS du webservice.
 *
 * Version initiale : toutes les origines sont autorisées et le préflight
 * annonce l'ensemble des méthodes de l'API. La politique sera resserrée dans
 * la troisième partie.
 */
class Cors
{
    public static function apply(): void
    {
        header('Access-Control-Allow-Origin: *');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
            return;
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
        header('Access-Control-Max-Age: 60');
        http_response_code(204);
        exit;
    }
}
