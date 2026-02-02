<?php

namespace App\Controllers;

use CPE\Framework\AbstractController;

class DefaultController extends AbstractController
{
    public function index()
    {
        $data = "Bonjour le monde !";
        $this->app->view()->setParam('pageTitle', $data);
        $this->app->view()->render('homepage.tpl.php');
    }

    public function test()
    {
        echo '<p>Cette page a reçu un paramètre nommé "nombre" et valant "' . $this->parameters['nombre'] . '"</p>
              <p>Contenu complet de <code>$this->parameters</code>:</p>
              <pre>';
        print_r($this->parameters);
    }

    public function error404()
    {
        http_response_code(404);
        $this->app->view()->render('404.tpl.php');
    }
}
