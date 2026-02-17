<?php

declare(strict_types=1);

use App\Application;

ini_set('display_errors', 'On');

// Même logique que côté site : le dossier Data est partagé entre les deux
// backends (monté dans /var/www/html/Data par Docker).
$dataPath = is_dir(__DIR__ . '/Data')
    ? __DIR__ . '/Data'
    : dirname(__DIR__) . '/Data';
define('DATA_PATH', $dataPath);

require_once('Framework/autoloader.php');

$app = new Application();
$app->run();
