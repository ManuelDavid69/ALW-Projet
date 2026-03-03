<?php

namespace App;

use App\Controllers\ApiController;
use App\Controllers\DefaultController;
use App\Http\Cors;
use CPE\Framework\AbstractApplication;
use CPE\Framework\Router;

class Application extends AbstractApplication
{
    public function run()
    {
        // Le front (port 50180) et l'API (port 50181) sont deux origines
        // distinctes : les en-têtes CORS doivent être présents sur toutes les
        // réponses, y compris les préflight OPTIONS.
        Cors::apply();

        $this->router = new Router($this);
        $this->router->mapDefault(DefaultController::class, 'error404');

        $this->router->map('GET', '/', DefaultController::class, 'index');
        $this->router->map('GET', '/api/config', ApiController::class, 'config');
        $this->router->map('GET', '/api/weather', ApiController::class, 'weather');
        $this->router->map('GET', '/api/players/{string:player}/inventory', ApiController::class, 'inventory');
        $this->router->map('GET', '/api/players/{string:player}/buildings', ApiController::class, 'buildings');
        $this->router->map('GET', '/api/players/{string:player}/products/{string:product}', ApiController::class, 'readProduct');
        $this->router->map('PUT', '/api/players/{string:player}/products/{string:product}', ApiController::class, 'updateProduct');
        $this->router->map('DELETE', '/api/players/{string:player}/products/{string:product}', ApiController::class, 'deleteProduct');
        $this->router->map('GET', '/api/players/{string:player}/generators/{string:generator}', ApiController::class, 'readGenerator');
        $this->router->map('PUT', '/api/players/{string:player}/generators/{string:generator}', ApiController::class, 'updateGenerator');
        $this->router->map('POST', '/api/players/{string:player}/buildings/{string:building}/harvest', ApiController::class, 'harvest');
        $this->router->map('POST', '/api/players/{string:player}/buildings/{string:building}/upgrade', ApiController::class, 'upgrade');

        $route = $this->router->findRoute();
        $controller = $this->router->getController($route->controller);
        $controller->execute($route->action, $route->foundParams);
    }
}
