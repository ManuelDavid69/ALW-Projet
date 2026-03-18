<?php

declare(strict_types=1);

/*
 * Routeur de développement pour le serveur intégré de PHP.
 *
 * Apache utilise le fichier .htaccess du projet ; le serveur intégré, lui, ne
 * lit pas .htaccess. Ce script reproduit donc la réécriture d'URL pour pouvoir
 * tester le site et l'API sans Docker :
 *
 *   php -S 127.0.0.1:50180 -t src/site tools/router.php
 *   php -S 127.0.0.1:50181 -t src/webservice tools/router.php
 */

$port = (string) ($_SERVER['SERVER_PORT'] ?? '50180');
$appRoot = $port === '50181'
    ? dirname(__DIR__) . '/src/webservice'
    : dirname(__DIR__) . '/src/site';

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requested = $appRoot . $uri;

// Les fichiers réellement présents (CSS, JavaScript, images) sont servis
// directement par le serveur intégré. Les dossiers privés restent inaccessibles,
// comme avec les règles .htaccess d'Apache.
$blocked = preg_match('#^/(App|Data|Framework|Twig)(/|$)#', $uri) === 1;
if (!$blocked && $uri !== '/' && is_file($requested)) {
    return false;
}

chdir($appRoot);
$_GET['url'] = '/' . ltrim($uri, '/');
$_SERVER['HTTP_MOD_REWRITE'] = 'On';

require $appRoot . '/index.php';
