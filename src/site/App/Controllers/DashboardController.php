<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameConfigRepository;
use App\Repositories\SaveRepository;
use CPE\Framework\AbstractController;

/**
 * Tableau de bord de la ferme.
 */
class DashboardController extends AbstractController
{
    public function index()
    {
        // Page réservée aux joueurs authentifiés : 401 sans session valide.
        if (!isset($_SESSION['player']['login'])) {
            http_response_code(401);
            $this->app->view()->render('401.tpl.php');
            return;
        }

        $login = (string) $_SESSION['player']['login'];
        $config = new GameConfigRepository('Data/Config/game_config.json');
        $saves = new SaveRepository('Data/Saves');

        $products = $config->getProducts();
        $buildings = $config->getBuildings();
        $save = $saves->ensure($login, $config);

        $totalLevel = 0;
        foreach ($save['buildings'] as $buildingState) {
            $totalLevel += (int) ($buildingState['level'] ?? 1);
        }

        $view = $this->app->view();
        $view->setParam('login', $login);
        $view->setParam('products', $products);
        $view->setParam('buildings', $buildings);
        $view->setParam('save', $save);
        $view->setParam('totalLevel', $totalLevel);
        $view->setParam('config', $config);
        $view->render('dashboard.tpl.php');
    }
}
