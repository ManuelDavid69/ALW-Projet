<?php

/**
 * Accès en lecture aux règles du jeu (produits et bâtiments).
 *
 * Le fichier JSON est la source de vérité : le code ne doit pas contenir de
 * liste codée en dur des produits ou des bâtiments.
 */
class GameConfigRepository
{
    private array $config;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('Fichier de configuration introuvable : ' . $filePath);
        }

        $config = json_decode(file_get_contents($filePath), true);
        if (!is_array($config) || !isset($config['products'], $config['buildings'])) {
            throw new RuntimeException('Configuration du jeu invalide : ' . $filePath);
        }

        $this->config = $config;
    }

    /**
     * Liste des produits, chaque entrée étant enrichie de son identifiant.
     *
     * @return array<string, array>
     */
    public function getProducts(): array
    {
        $products = [];
        foreach ($this->config['products'] as $id => $product) {
            $products[$id] = ['id' => $id] + $product;
        }

        return $products;
    }

    /**
     * Liste des bâtiments, chaque entrée étant enrichie de son identifiant.
     *
     * @return array<string, array>
     */
    public function getBuildings(): array
    {
        $buildings = [];
        foreach ($this->config['buildings'] as $id => $building) {
            $buildings[$id] = ['id' => $id] + $building;
        }

        return $buildings;
    }

    public function getProduct(string $id): ?array
    {
        $products = $this->getProducts();

        return $products[$id] ?? null;
    }

    public function getBuilding(string $id): ?array
    {
        $buildings = $this->getBuildings();

        return $buildings[$id] ?? null;
    }

    /**
     * Coût pour faire passer un bâtiment du niveau donné au niveau suivant.
     *
     * Le coût de base est multiplié par costGrowth à chaque niveau déjà
     * possédé, puis arrondi au supérieur pour éviter les fractions.
     *
     * @return array<string, int> produit => quantité nécessaire
     */
    public function getUpgradeCost(string $buildingId, int $level): array
    {
        $building = $this->getBuilding($buildingId);
        if ($building === null) {
            throw new InvalidArgumentException('Bâtiment inconnu : ' . $buildingId);
        }

        $growth = (float) ($building['costGrowth'] ?? 1.0);
        $factor = $growth ** max(0, $level - 1);

        $cost = [];
        foreach ($building['upgradeCost'] as $product => $baseQuantity) {
            $cost[$product] = (int) ceil($baseQuantity * $factor);
        }

        return $cost;
    }

    /**
     * Quantité produite par une récolte au niveau donné.
     */
    public function getHarvestYield(string $buildingId, int $level): int
    {
        $building = $this->getBuilding($buildingId);
        if ($building === null) {
            throw new InvalidArgumentException('Bâtiment inconnu : ' . $buildingId);
        }

        $base = (int) ($building['baseYield'] ?? 1);
        $perLevel = (int) ($building['yieldPerLevel'] ?? 0);

        return max(1, $base + ($level - 1) * $perLevel);
    }
}
