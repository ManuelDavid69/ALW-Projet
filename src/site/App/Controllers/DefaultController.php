<?php

namespace App\Controllers;

use CPE\Framework\AbstractController;

class DefaultController extends AbstractController
{
    public function index()
    {
        // La racine du site affiche la connexion ; cette action reste un
        // exemple de contrôleur pour le framework.
        header('Location: /login');
        exit;
    }

    public function test()
    {
        echo '<p>Cette page a reçu un paramètre nommé "nombre" et valant "'
            . htmlspecialchars((string) ($this->parameters['nombre'] ?? ''), ENT_QUOTES, 'UTF-8')
            . '"</p><p>Contenu complet de <code>$this->parameters</code> :</p><pre>';
        print_r($this->parameters);
        echo '</pre>';
    }

    public function error404()
    {
        http_response_code(404);
        echo $this->app->twig()->render('404.html.twig');
    }
}
