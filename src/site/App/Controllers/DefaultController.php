<?php

namespace App\Controllers;

use CPE\Framework\AbstractController;

class DefaultController extends AbstractController
{
    public function error404()
    {
        http_response_code(404);
        echo $this->app->twig()->render('404.html.twig');
    }
}
