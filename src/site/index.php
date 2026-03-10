<?php

declare(strict_types=1);

use App\Application;

ini_set('display_errors', 'On');
date_default_timezone_set('Europe/Paris');

/*
 * Les deux backends (site et webservice) partagent le même dossier de données.
 * En Docker, ce dossier est monté dans l'application (/var/www/html/Data) ;
 * en développement local, il se trouve à la racine de src/.
 */
$dataPath = is_dir(__DIR__ . '/Data')
    ? __DIR__ . '/Data'
    : dirname(__DIR__) . '/Data';
define('DATA_PATH', $dataPath);

require_once('Framework/autoloader.php');

// start the application
$app = new Application();
$app->run();
