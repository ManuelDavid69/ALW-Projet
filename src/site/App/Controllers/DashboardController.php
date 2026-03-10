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
            echo $this->app->twig()->render('401.html.twig');
            return;
        }

        $login = (string) $_SESSION['player']['login'];
        $config = new GameConfigRepository(DATA_PATH . '/Config/game_config.json');
        $saves = new SaveRepository(DATA_PATH . '/Saves');

        $products = $config->getProducts();
        $buildings = $config->getBuildings();
        $save = $saves->ensure($login, $config);

        $productViews = [];
        foreach ($products as $productId => $product) {
            $product['stock'] = (int) ($save['inventory'][$productId] ?? 0);
            $productViews[$productId] = $product;
        }

        $buildingViews = [];
        $totalLevel = 0;
        foreach ($buildings as $buildingId => $building) {
            $level = max(1, (int) ($save['buildings'][$buildingId]['level'] ?? 1));
            $totalLevel += $level;

            $cost = $config->getUpgradeCost($buildingId, $level);
            $canAfford = true;
            $costParts = [];
            foreach ($cost as $productId => $quantity) {
                $costParts[] = [
                    'name' => $products[$productId]['name'],
                    'icon' => $products[$productId]['icon'],
                    'quantity' => $quantity,
                ];

                if ((int) ($save['inventory'][$productId] ?? 0) < $quantity) {
                    $canAfford = false;
                }
            }

            $yield = $config->getHarvestYield($buildingId, $level);
            $produced = $products[$building['produces']];
            $yieldUnit = $yield > 1
                ? $produced['unit']
                : ($produced['unitSingular'] ?? $produced['unit']);

            $buildingViews[] = [
                'id' => $buildingId,
                'name' => $building['name'],
                'icon' => $building['icon'],
                'description' => $building['description'],
                'produces' => $produced,
                'level' => $level,
                'yield' => $yield,
                'yieldUnit' => $yieldUnit,
                'cost' => $cost,
                'costParts' => $costParts,
                'canAfford' => $canAfford,
            ];        }

        echo $this->app->twig()->render('dashboard.html.twig', [
            'login' => $login,
            'products' => $productViews,
            'buildings' => $buildingViews,
            'totalLevel' => $totalLevel,
        ]);
    }
}
