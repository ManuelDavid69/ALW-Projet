<?php

namespace App;

use App\Controllers\ApiController;
use App\Controllers\DefaultController;
use CPE\Framework\AbstractApplication;
use CPE\Framework\Router;

class Application extends AbstractApplication
{
    public function run()
    {
        // Contournement initial des CORS : le front (port 50180) et l'API
        // (port 50181) sont deux origines distinctes.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');

        $this->router = new Router($this);
        $this->router->mapDefault(DefaultController::class, 'error404');

        $this->router->map('GET', '/', DefaultController::class, 'index');
        $this->router->map('GET', '/api/config', ApiController::class, 'config');
        $this->router->map('GET', '/api/players/{string:player}/inventory', ApiController::class, 'inventory');
        $this->router->map('GET', '/api/players/{string:player}/buildings', ApiController::class, 'buildings');

        $route = $this->router->findRoute();
        $controller = $this->router->getController($route->controller);
        $controller->execute($route->action, $route->foundParams);
    }
}
