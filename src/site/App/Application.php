<?php

namespace App;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DefaultController;
use CPE\Framework\AbstractApplication;
use CPE\Framework\Router;

class Application extends AbstractApplication
{
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
        $this->router->map('GET', '/test/{int:nombre}', DefaultController::class, 'test');

        $route = $this->router->findRoute();
        $controller = $this->router->getController($route->controller);
        $controller->execute($route->action, $route->foundParams);
    }
}
