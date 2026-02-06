<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/Utils/GameConfigRepository.php';
require_once __DIR__ . '/Utils/SaveRepository.php';

// Cette page est réservée aux joueurs authentifiés. Sans session valide, on
// répond avec le code 401 (Unauthorized) plutôt que d'afficher la ferme.
if (!isset($_SESSION['player']['login'])) {
    http_response_code(401);
    ?><!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès refusé — La Ferme</title>
        <style>
            body { background: #2b2b2b; color: #e9f2f5; font-family: system-ui, sans-serif; }
            .error-box { max-width: 34rem; margin: 4rem auto; padding: 1.4rem; background: #1b1b1b; border: 1px solid #ff6b6b55; border-radius: .8rem; }
            h1 { color: #ff9c9c; font-size: 1.3rem; }
            a { color: #0bbbef; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>401 — Authentification requise</h1>
            <p>Vous devez être connecté pour consulter le tableau de bord de votre ferme.</p>
            <p><a href="login.php">Se connecter</a></p>
        </div>
    </body>
    </html><?php
    exit;
}

$config = new GameConfigRepository(__DIR__ . '/Data/Config/game_config.json');
$saves = new SaveRepository(__DIR__ . '/Data/Saves');

$login = (string) $_SESSION['player']['login'];
$products = $config->getProducts();
$buildings = $config->getBuildings();
$save = $saves->ensure($login, $config);

$totalLevel = 0;
foreach ($save['buildings'] as $buildingState) {
    $totalLevel += (int) ($buildingState['level'] ?? 1);
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — La Ferme</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #2b2b2b;
            --panel: #111;
            --panel-soft: #1b1b1b;
            --line: #0086b7;
            --blue: #0bbbef;
            --blue-dark: #0086b7;
            --brown: #b35a1f;
            --green: #4af626;
            --muted: #a7b0b5;
            --error: #b30000;
            font-family: system-ui, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                radial-gradient(1100px 500px at 15% -10%, #0d4a5e55, transparent 60%),
                var(--bg);
            color: #e9f2f5;
            min-height: 100vh;
        }
        a { color: var(--blue); }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1.4rem;
            background: #0b0b0bcc;
            border-bottom: 1px solid #ffffff14;
            position: sticky;
            top: 0;
            backdrop-filter: blur(8px);
            z-index: 10;
        }
        .brand {
            font-weight: 800;
            letter-spacing: .02em;
            color: #fff;
            text-decoration: none;
            font-size: 1.15rem;
        }
        .brand span { color: var(--blue); }
        .topbar nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: .95rem;
        }
        .topbar nav a { color: var(--muted); text-decoration: none; }
        .topbar nav a:hover { color: var(--blue); }
        main {
            width: min(1180px, 94vw);
            margin: 0 auto;
            padding: 2rem 0 4rem;
        }
        .hero {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.6rem 1.8rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #0d3b4a, #101010 70%);
            border: 1px solid #ffffff17;
            border-radius: 1rem;
        }
        .eyebrow {
            margin: 0 0 .3rem;
            text-transform: uppercase;
            letter-spacing: .16em;
            font-size: .72rem;
            color: var(--blue);
            font-weight: 700;
        }
        h1 { margin: 0; font-size: clamp(1.7rem, 3vw, 2.4rem); }
        .hero p.lede { margin: .5rem 0 0; color: var(--muted); max-width: 46ch; }
        .stats { display: flex; gap: 1.6rem; margin: 0; }
        .stats div { text-align: right; }
        .stats dt { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; }
        .stats dd { margin: .15rem 0 0; font-size: 1.6rem; font-weight: 800; color: #fff; }
        .panel {
            background: var(--panel);
            border: 1px solid #ffffff12;
            border-radius: 1rem;
            padding: 1.3rem 1.4rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .panel > header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .panel h2 {
            margin: 0;
            color: var(--blue);
            font-size: 1.15rem;
            letter-spacing: .02em;
        }
        .panel header .hint { color: var(--muted); font-size: .85rem; }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: .9rem;
        }
        .product-card {
            display: flex;
            align-items: center;
            gap: .9rem;
            padding: .9rem 1rem;
            background: var(--panel-soft);
            border: 1px solid #ffffff10;
            border-radius: .8rem;
        }
        .product-card .icon {
            display: grid;
            place-items: center;
            width: 2.6rem;
            height: 2.6rem;
            font-size: 1.5rem;
            background: #000;
            border-radius: .7rem;
            border: 1px solid #ffffff12;
        }
        .product-card h3 { margin: 0; font-size: 1rem; }
        .product-card p { margin: .1rem 0 0; color: var(--muted); font-size: .8rem; }
        .product-card .stock {
            margin-left: auto;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--green);
            font-variant-numeric: tabular-nums;
        }
        .building-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 1rem;
        }
        .building-card {
            display: flex;
            flex-direction: column;
            gap: .8rem;
            padding: 1rem;
            background: var(--panel-soft);
            border: 1px solid #ffffff10;
            border-radius: .9rem;
        }
        .building-card > header {
            display: flex;
            align-items: center;
            gap: .8rem;
        }
        .building-card .icon {
            display: grid;
            place-items: center;
            width: 2.8rem;
            height: 2.8rem;
            font-size: 1.6rem;
            background: #000;
            border-radius: .7rem;
            border: 1px solid #ffffff12;
        }
        .building-card h3 { margin: 0; font-size: 1.02rem; }
        .building-card header p { margin: .1rem 0 0; color: var(--muted); font-size: .78rem; }
        .level-badge {
            margin-left: auto;
            padding: .25rem .55rem;
            border-radius: 999px;
            background: #0bbbef1a;
            border: 1px solid #0bbbef55;
            color: var(--blue);
            font-size: .78rem;
            white-space: nowrap;
        }
        .building-card .description { margin: 0; color: var(--muted); font-size: .86rem; min-height: 2.4em; }
        .production {
            padding: .5rem .65rem;
            border-left: 3px solid var(--brown);
            background: #b35a1f12;
            border-radius: .3rem;
            font-size: .86rem;
        }
        .actions { display: flex; gap: .6rem; }
        button {
            font: inherit;
            font-size: .9rem;
            padding: .5rem .9rem;
            border-radius: .5rem;
            border: 1px solid transparent;
            cursor: pointer;
        }
        button.harvest {
            background: var(--blue-dark);
            color: #001b23;
            font-weight: 700;
        }
        button.upgrade {
            background: transparent;
            color: var(--blue);
            border-color: #0bbbef66;
        }
        button:hover { filter: brightness(1.12); }
        .cost {
            font-size: .82rem;
            color: var(--muted);
        }
        .cost.affordable { color: var(--green); }
        .cost.unaffordable { color: #ff9c9c; }
        .error-box {
            max-width: 34rem;
            margin: 4rem auto;
            padding: 1.4rem;
            background: #1b1b1b;
            border: 1px solid #ff6b6b55;
            border-radius: .8rem;
        }
        .error-box h1 { color: #ff9c9c; font-size: 1.3rem; }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="dashboard.php">🌾 La <span>Ferme</span></a>
        <nav>
            <a href="dashboard.php">Tableau de bord</a>
            <span>Connecté : <?php echo htmlspecialchars($login, ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">Ferme de <?php echo htmlspecialchars($login, ENT_QUOTES, 'UTF-8'); ?></p>
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
                    <article id="product-<?php echo htmlspecialchars($productId, ENT_QUOTES, 'UTF-8'); ?>" class="product-card">
                        <span class="icon" aria-hidden="true"><?php echo $product['icon']; ?></span>
                        <div>
                            <h3><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8'); ?></p>
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
                    <article id="building-<?php echo htmlspecialchars($buildingId, ENT_QUOTES, 'UTF-8'); ?>" class="building-card">
                        <header>
                            <span class="icon" aria-hidden="true"><?php echo $building['icon']; ?></span>
                            <div>
                                <h3><?php echo htmlspecialchars($building['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p>Produit : <?php echo htmlspecialchars($products[$building['produces']]['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="level-badge">Niv. <output class="level"><?php echo $level; ?></output></span>
                        </header>

                        <p class="description"><?php echo htmlspecialchars($building['description'], ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="production">
                            Récolte : <strong><?php echo $yield; ?> <?php echo htmlspecialchars($products[$building['produces']]['unit'], ENT_QUOTES, 'UTF-8'); ?></strong>
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
