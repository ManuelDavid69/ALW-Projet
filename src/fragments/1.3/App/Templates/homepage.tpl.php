<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Page d'exemple</title>

    <!-- chemin relatif à la racine du projet -->
    <link rel="stylesheet" href="Public/style.css">
</head>
<body>
    <h1><?php echo $pageTitle; ?></h1>
    <p>Ceci est un exemple de rendu HTML généré par le Framework :</p>
    <ol>
        <li>Sa route est définie dans <code>Application.php</code> par un appel à <code>$this->router->map(...);</code></li>
        <li>L'action correspondante est définie dans <code>DefaultController.php</code> : c'est la fonction <code>index()</code></li>
        <li>
            Cette action fait appel à un template situé dans <code>App/Templates/homepage.tpl.php</code>. Celui-ci contient essentiellement du HTML avec quelques éléments dynamiques.
            <ul>
                <li>Au lieu d'utiliser un template, vous pouvez simplement faire afficher (<code>echo</code>) quelque chose directement depuis les contrôleurs (HTML fait "à la main", Json, etc.), comme c'est fait pour la méthode <code>test()</code></li>
            </ul>
        </li>
    </ol>
    <pre><?php print_r(get_defined_vars()); ?></pre>
    <p>D'autres exemples de pages :</p>
    <ul>
        <li><a href="<?php echo $this->buildRoute("/n'importe quelle url inexistante"); ?>">Page 404</a></li>
        <li><a href="<?php
            $varContainingDynamicValue = 12;
            echo $this->buildRoute('/test/%s', $varContainingDynamicValue);
            ?>">Page "test" paramétrée (avec la valeur 12)</a></li>
    </ul>
</body>
</html>