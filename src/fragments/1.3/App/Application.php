<?php

namespace App;

use App\Controllers\DefaultController;
use CPE\Framework\AbstractApplication;
use CPE\Framework\Router;

class Application extends AbstractApplication
{
    public function run()
    {
        // map all routes to corresponding controllers/actions
        $this->router = new Router($this);
        $this->router->mapDefault(DefaultController::class, 'error404');

        $this->router->map('GET', '/', DefaultController::class, 'index');
        $this->router->map('GET', '/test/{int:nombre}', DefaultController::class, 'test');

        $route = $this->router->findRoute();
        $controller = $this->router->getController($route->controller);
        $controller->execute($route->action, $route->foundParams);
    }
}
