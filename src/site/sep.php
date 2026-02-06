<?php

declare(strict_types=1);

// Point d'entrée unique (Single Entry Point) : toutes les pages passent par
// ce script, qui charge le code commun puis délègue au script de la page.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/login.php';
