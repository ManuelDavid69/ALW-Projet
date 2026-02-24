<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Politique CORS du webservice.
 *
 * - seules les origines du site front sont autorisées ;
 * - Access-Control-Allow-Origin est envoyé sur toutes les réponses ;
 * - les autres en-têtes ne sont envoyés qu'en réponse à un préflight OPTIONS ;
 * - les méthodes autorisées dépendent de la ressource demandée.
 */
class Cors
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:50180',
        'http://127.0.0.1:50180',
    ];

    /**
     * Routes de l'API et méthodes autorisées pour chacune.
     *
     * @var array<string, string[]>
     */
    private const ROUTES = [
        '#^/$#' => ['GET', 'OPTIONS'],
        '#^/api/config$#' => ['GET', 'OPTIONS'],
        '#^/api/weather$#' => ['GET', 'OPTIONS'],
        '#^/api/players/[^/]+/inventory$#' => ['GET', 'OPTIONS'],
        '#^/api/players/[^/]+/buildings$#' => ['GET', 'OPTIONS'],
        '#^/api/players/[^/]+/products/[^/]+$#' => ['GET', 'PUT', 'DELETE', 'OPTIONS'],
        '#^/api/players/[^/]+/generators/[^/]+$#' => ['GET', 'PUT', 'OPTIONS'],
        '#^/api/players/[^/]+/buildings/[^/]+/harvest$#' => ['POST', 'OPTIONS'],
        '#^/api/players/[^/]+/buildings/[^/]+/upgrade$#' => ['POST', 'OPTIONS'],
        '#^/api/events$#' => ['GET', 'POST', 'OPTIONS'],
        '#^/api/events/stream$#' => ['GET', 'OPTIONS'],
    ];

    public static function apply(): void
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if (in_array($origin, self::ALLOWED_ORIGINS, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Vary: Origin', false);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
            return;
        }

        $methods = self::methodsFor(self::path());
        if ($methods === null) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Ressource introuvable.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: Content-Type, Accept');
        header('Access-Control-Max-Age: 60');

        $requestedMethod = (string) ($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '');
        if ($requestedMethod !== '' && !in_array($requestedMethod, $methods, true)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $methods));
            exit;
        }

        http_response_code(204);
        exit;
    }

    private static function path(): string
    {
        if (isset($_GET['url'])) {
            return '/' . trim((string) $_GET['url'], '/');
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * @return string[]|null
     */
    private static function methodsFor(string $path): ?array
    {
        foreach (self::ROUTES as $pattern => $methods) {
            if (preg_match($pattern, $path) === 1) {
                return $methods;
            }
        }

        return null;
    }
}
