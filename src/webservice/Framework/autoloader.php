<?php

// Twig est installé « à la main » (sans Composer). Il ne dépend de la fonction
// trigger_deprecation() que pour signaler ses propres dépréciations : une
// version silencieuse suffit donc ici.
if (!function_exists('trigger_deprecation')) {
    function trigger_deprecation(string $package, string $version, string $message, mixed ...$args): void
    {
    }
}

spl_autoload_register(function ($class) {
    $baseFramework = 'CPE\Framework';
    $baseApp = 'App';
    $baseTwig = 'Twig';

    // for now, let's assume the file path is the same as the namespace
    $classPath = str_replace('\\', '/', $class) . '.php';

    if (substr($class, 0, strlen($baseFramework)) == $baseFramework) {
        // framework classes: remove "CPE/" in the path
        $classPath = substr($classPath, 4);
    } elseif (
        substr($class, 0, strlen($baseApp)) != $baseApp
        && substr($class, 0, strlen($baseTwig)) != $baseTwig
    ) {
        // classes that are not in "CPE\Framework", "App" or "Twig": error
        throw new LogicException('Unhandled class namespace: "' . $class . '"');
    }

    // make sure the file exists
    if (file_exists($classPath) === false) {
        throw new LogicException('Class file not found: "' . $classPath . '"');
    }

    // call the PHP file that will declare the class
    require_once $classPath;
});
