<?php

declare(strict_types=1);

// Point d'entrée unique (Single Entry Point) : toutes les pages passent par
// ce script, qui charge le code commun puis délègue au script de la page.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Table de routage : le paramètre « page » de l'URL désigne une action, pas
// directement un fichier. Un nom inconnu ne peut donc pas inclure n'importe
// quel fichier du serveur.
$routes = [
    'login' => 'login.php',
    'dashboard' => 'dashboard.php',
];

$page = (string) ($_GET['page'] ?? 'login');
if (!array_key_exists($page, $routes)) {
    http_response_code(404);
    ?><!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Page introuvable — La Ferme</title>
        <link rel="stylesheet" href="/Public/style.css">
    </head>
    <body class="page-dashboard">
        <div class="error-box">
            <h1>404 — Page introuvable</h1>
            <p>La page demandée n'existe pas.</p>
            <p><a href="sep.php?page=login">Retour à la connexion</a></p>
        </div>
    </body>
    </html><?php
    exit;
}

require_once __DIR__ . '/' . $routes[$page];
