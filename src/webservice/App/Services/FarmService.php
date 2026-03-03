<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Repositories\GameConfigRepository;
use App\Repositories\SaveRepository;

/**
 * Règles métier de la ferme, indépendantes de HTTP.
 *
 * Le contrôleur ne fait que traduire les requêtes HTTP en appels à ce service
 * et les résultats en JSON. Les quantités sont toujours calculées ici, jamais
 * transmises par le client.
 */
class FarmService
{
    public function __construct(
        private GameConfigRepository $config,
        private SaveRepository $saves,
        private WeatherService $weather
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function inventory(string $player): array
    {
        return $this->requireSave($player)['inventory'];
    }

    /**
     * @return array<string, array{level: int}>
     */
    public function buildings(string $player): array
    {
        return $this->requireSave($player)['buildings'];
    }

    public function readProduct(string $player, string $productId): array
    {
        $product = $this->requireProduct($productId);
        $save = $this->requireSave($player);

        return [
            'id' => $productId,
            'name' => $product['name'],
            'unit' => $product['unit'],
            'stock' => (int) ($save['inventory'][$productId] ?? 0),
        ];
    }

    public function updateProductStock(string $player, string $productId, mixed $stock): array
    {
        $product = $this->requireProduct($productId);
        $quantity = $this->asNonNegativeInt($stock, 'Le stock');

        $save = $this->saves->mutate($player, function (array $save) use ($productId, $quantity): array {
            $save['inventory'][$productId] = $quantity;
            return $save;
        });

        return [
            'id' => $productId,
            'name' => $product['name'],
            'unit' => $product['unit'],
            'stock' => (int) $save['inventory'][$productId],
        ];
    }

    /**
     * Jette le stock d'un produit (DELETE), sans supprimer le type de produit.
     */
    public function discardProduct(string $player, string $productId): array
    {
        $product = $this->requireProduct($productId);

        $this->saves->mutate($player, function (array $save) use ($productId): array {
            $save['inventory'][$productId] = 0;
            return $save;
        });

        return [
            'id' => $productId,
            'name' => $product['name'],
            'unit' => $product['unit'],
            'stock' => 0,
        ];
    }

    public function readGenerator(string $player, string $buildingId): array
    {
        $building = $this->requireBuilding($buildingId);
        $save = $this->requireSave($player);

        return [
            'id' => $buildingId,
            'name' => $building['name'],
            'level' => (int) ($save['buildings'][$buildingId]['level'] ?? 1),
        ];
    }

    public function updateGeneratorLevel(string $player, string $buildingId, mixed $level): array
    {
        $building = $this->requireBuilding($buildingId);
        $newLevel = $this->asNonNegativeInt($level, 'Le niveau');
        if ($newLevel < 1) {
            throw new ApiException('Le niveau doit être au moins 1.', 400);
        }

        $save = $this->saves->mutate($player, function (array $save) use ($buildingId, $newLevel): array {
            $save['buildings'][$buildingId]['level'] = $newLevel;
            return $save;
        });

        return [
            'id' => $buildingId,
            'name' => $building['name'],
            'level' => (int) $save['buildings'][$buildingId]['level'],
        ];
    }

    /**
     * Récolte un bâtiment et ajoute la production à l'inventaire.
     *
     * Le client ne transmet aucune quantité : c'est le serveur qui la calcule
     * à partir du niveau réel du bâtiment.
     */
    public function harvest(string $player, string $buildingId): array
    {
        $building = $this->requireBuilding($buildingId);
        $this->requireSave($player);

        $productId = (string) $building['produces'];
        $modifier = $this->weather->productionModifier();

        $save = $this->saves->mutate($player, function (array $save) use ($buildingId, $productId, $modifier): array {
            $level = max(1, (int) ($save['buildings'][$buildingId]['level'] ?? 1));
            $baseQuantity = $this->config->getHarvestYield($buildingId, $level);
            $quantity = max(1, (int) round($baseQuantity * $modifier));

            $save['inventory'][$productId] = (int) ($save['inventory'][$productId] ?? 0) + $quantity;
            $save['lastHarvest'] = [
                'building' => $buildingId,
                'product' => $productId,
                'quantity' => $quantity,
                'at' => gmdate('c'),
            ];

            return $save;
        });

        $level = max(1, (int) ($save['buildings'][$buildingId]['level'] ?? 1));
        $baseQuantity = $this->config->getHarvestYield($buildingId, $level);

        return [
            'building' => $buildingId,
            'product' => $productId,
            'baseQuantity' => $baseQuantity,
            'modifier' => $modifier,
            'quantity' => max(1, (int) round($baseQuantity * $modifier)),
            'level' => $level,
            'inventory' => $save['inventory'],
            'buildings' => $save['buildings'],
        ];
    }

    /**
     * Améliore un bâtiment d'un niveau.
     *
     * Le coût est vérifié puis débité dans la même opération verrouillée que
     * l'incrément du niveau : deux clics simultanés ne peuvent pas payer deux
     * fois avec le même stock.
     */
    public function upgrade(string $player, string $buildingId): array
    {
        $this->requireBuilding($buildingId);
        $this->requireSave($player);

        $save = $this->saves->mutate($player, function (array $save) use ($buildingId): array {
            $level = max(1, (int) ($save['buildings'][$buildingId]['level'] ?? 1));
            $cost = $this->config->getUpgradeCost($buildingId, $level);

            $missing = [];
            foreach ($cost as $productId => $quantity) {
                $available = (int) ($save['inventory'][$productId] ?? 0);
                if ($available < $quantity) {
                    $product = $this->config->getProduct($productId);
                    $name = $product['name'] ?? $productId;
                    $missing[] = sprintf('%s (%d/%d)', $name, $available, $quantity);
                }
            }

            if ($missing !== []) {
                throw new ApiException(
                    'Ressources insuffisantes pour améliorer ce bâtiment : ' . implode(', ', $missing),
                    409
                );
            }

            foreach ($cost as $productId => $quantity) {
                $save['inventory'][$productId] = (int) $save['inventory'][$productId] - $quantity;
            }

            $save['buildings'][$buildingId]['level'] = $level + 1;

            return $save;
        });

        return [
            'building' => $buildingId,
            'level' => (int) $save['buildings'][$buildingId]['level'],
            'inventory' => $save['inventory'],
            'buildings' => $save['buildings'],
        ];
    }

    private function requireSave(string $player): array
    {
        if (!SaveRepository::isValidLogin($player) || !$this->saves->exists($player)) {
            throw new ApiException('Joueur inconnu : ' . $player, 404);
        }

        return $this->saves->get($player);
    }

    private function requireProduct(string $productId): array
    {
        $product = $this->config->getProduct($productId);
        if ($product === null) {
            throw new ApiException('Produit inconnu : ' . $productId, 404);
        }

        return $product;
    }

    private function requireBuilding(string $buildingId): array
    {
        $building = $this->config->getBuilding($buildingId);
        if ($building === null) {
            throw new ApiException('Bâtiment inconnu : ' . $buildingId, 404);
        }

        return $building;
    }

    private function asNonNegativeInt(mixed $value, string $label): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 0) {
            throw new ApiException($label . ' doit être un entier positif ou nul.', 400);
        }

        return $value;
    }
}
