<?php

declare(strict_types=1);

// La session est ouverte pour tous les visiteurs : elle sert à la fois à
// mémoriser le joueur connecté et à conserver les messages d'erreur.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/Framework/autoloader.php';

$error = null;
$repo = new \App\Repositories\UserRepository(__DIR__ . '/Data/users.json');
$player = $_SESSION['player'] ?? null;

// var_dump($_SERVER);
// var_dump($_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $player === null) {
    $login = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = 'Veuillez renseigner votre identifiant et votre mot de passe.';
    } else {
        $user = $repo->get($login);

        // password_verify() compare le mot de passe saisi au hash stocké :
        // le mot de passe en clair ne circule jamais dans nos fichiers.
        if ($user === null || !password_verify($password, $user->password)) {
            $error = 'Identifiant ou mot de passe incorrect.';
        } else {
            // Nouvel identifiant de session après authentification pour éviter
            // qu'une session préparée à l'avance par un attaquant soit réutilisée.
            session_regenerate_id(true);
            $_SESSION['player'] = ['login' => $user->login];
            $player = $_SESSION['player'];

            $config = new \App\Repositories\GameConfigRepository(__DIR__ . '/Data/Config/game_config.json');
            $saves = new \App\Repositories\SaveRepository(__DIR__ . '/Data/Saves');
            $saves->ensure($user->login, $config);
        }
    }
}

$isAuthenticated = $player !== null;

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — La Ferme</title>
    <link rel="stylesheet" href="/Public/style.css">
</head>
<body>
    <?php if ($isAuthenticated) { ?>
        <section class="loginForm">
            <h2>Bienvenue, <?php echo htmlspecialchars($player['login'], ENT_QUOTES, 'UTF-8'); ?> !</h2>
            <p>Votre ferme vous attend. La session est ouverte : vous pouvez
               maintenant rejoindre le tableau de bord.</p>
            <p><a href="sep.php?page=dashboard">Accéder au tableau de bord</a></p>
        </section>
    <?php } else { ?>
        <form action="" method="POST" class="loginForm">
            <h2>Connexion</h2>
            <input type="text" name="username" placeholder="Nom d'utilisateur" required autocomplete="username">
            <input type="password" name="password" placeholder="Mot de passe" required autocomplete="current-password">

            <?php if ($error !== null) { ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php } ?>

            <button type="submit">Se connecter</button>
        </form>
    <?php } ?>
</body>
</html>
