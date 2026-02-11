<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — La Ferme</title>
    <link rel="stylesheet" href="/Public/style.css">
</head>
<body class="page-dashboard">
    <header class="topbar">
        <a class="brand" href="/dashboard">🌾 La <span>Ferme</span></a>
        <nav>
            <a href="/dashboard">Tableau de bord</a>
            <span>Connecté : <?php echo htmlspecialchars((string) $login, ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">Ferme de <?php echo htmlspecialchars((string) $login, ENT_QUOTES, 'UTF-8'); ?></p>
                <h1>Votre exploitation</h1>
                <p class="lede">Récoltez vos produits, améliorez vos bâtiments et faites prospérer la ferme.</p>
            </div>
            <dl class="stats">
                <div><dt>Produits</dt><dd><?php echo count($products); ?></dd></div>
                <div><dt>Bâtiments</dt><dd><?php echo count($buildings); ?></dd></div>
                <div><dt>Niveaux</dt><dd><?php echo $totalLevel; ?></dd></div>
            </dl>
        </section>

        <section class="panel" id="inventory">
            <header>
                <h2>Inventaire</h2>
                <span class="hint">Stock actuel</span>
            </header>
            <div class="product-grid">
                <?php foreach ($products as $productId => $product) { ?>
                    <article id="product-<?php echo htmlspecialchars((string) $productId, ENT_QUOTES, 'UTF-8'); ?>" class="product-card">
                        <span class="icon" aria-hidden="true"><?php echo $product['icon']; ?></span>
                        <div>
                            <h3><?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars((string) $product['unit'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <output class="stock"><?php echo (int) ($save['inventory'][$productId] ?? 0); ?></output>
                    </article>
                <?php } ?>
            </div>
        </section>

        <section class="panel" id="buildings">
            <header>
                <h2>Bâtiments</h2>
                <span class="hint">Produire et améliorer</span>
            </header>
            <div class="building-grid">
                <?php foreach ($buildings as $buildingId => $building) {
                    $level = (int) ($save['buildings'][$buildingId]['level'] ?? 1);
                    $yield = $config->getHarvestYield($buildingId, $level);
                    $cost = $config->getUpgradeCost($buildingId, $level);
                    $canAfford = true;
                    foreach ($cost as $productId => $quantity) {
                        if ((int) ($save['inventory'][$productId] ?? 0) < $quantity) {
                            $canAfford = false;
                        }
                    }
                    ?>
                    <article id="building-<?php echo htmlspecialchars((string) $buildingId, ENT_QUOTES, 'UTF-8'); ?>" class="building-card">
                        <header>
                            <span class="icon" aria-hidden="true"><?php echo $building['icon']; ?></span>
                            <div>
                                <h3><?php echo htmlspecialchars((string) $building['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p>Produit : <?php echo htmlspecialchars((string) $products[$building['produces']]['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="level-badge">Niv. <output class="level"><?php echo $level; ?></output></span>
                        </header>

                        <p class="description"><?php echo htmlspecialchars((string) $building['description'], ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="production">
                            Récolte : <strong><?php echo $yield; ?> <?php echo htmlspecialchars((string) $products[$building['produces']]['unit'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>

                        <div class="actions">
                            <button class="harvest" type="button">Produire</button>
                            <button class="upgrade" type="button">Améliorer</button>
                        </div>

                        <output class="cost <?php echo $canAfford ? 'affordable' : 'unaffordable'; ?>">Coût : <?php
                            $parts = [];
                            foreach ($cost as $productId => $quantity) {
                                $parts[] = $products[$productId]['icon'] . ' ' . $quantity;
                            }
                            echo htmlspecialchars(implode(' · ', $parts), ENT_QUOTES, 'UTF-8');
                        ?></output>
                    </article>
                <?php } ?>
            </div>
        </section>
    </main>
</body>
</html>
