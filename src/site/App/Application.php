<?php

namespace App;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DefaultController;
use CPE\Framework\AbstractApplication;
use CPE\Framework\Router;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Application extends AbstractApplication
{
    protected ?Environment $twig = null;

    public function run()
    {
        // La session est ouverte une seule fois, pour toutes les actions.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // map all routes to corresponding controllers/actions
        $this->router = new Router($this);
        $this->router->mapDefault(DefaultController::class, 'error404');

        $this->router->map('GET', '/', AuthController::class, 'showLogin');
        $this->router->map('GET', '/login', AuthController::class, 'showLogin');
        $this->router->map('POST', '/login', AuthController::class, 'authenticate');
        $this->router->map('GET', '/dashboard', DashboardController::class, 'index');
        $this->router->map('POST', '/logout', AuthController::class, 'logout');

        $route = $this->router->findRoute();
        $controller = $this->router->getController($route->controller);
        $controller->execute($route->action, $route->foundParams);
    }

    /**
     * Retourne le moteur de templates Twig, initialisé à la première demande.
     *
     * Centraliser cette initialisation évite de la répéter dans chaque
     * contrôleur et garantit que tous les templates partagent la même
     * configuration (répertoire, cache, variables globales).
     */
    public function twig(): Environment
    {
        if ($this->twig === null) {
            $loader = new FilesystemLoader(__DIR__ . '/Templates');
            $this->twig = new Environment($loader, [
                'cache' => false,
                'debug' => true,
                'charset' => 'UTF-8',
            ]);

            // Le layout a besoin de savoir qui est connecté, quelle que soit
            // la page affichée.
            $this->twig->addGlobal('player', $_SESSION['player']['login'] ?? null);
        }

        return $this->twig;
    }
}
