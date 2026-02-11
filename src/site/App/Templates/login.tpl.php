<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — La Ferme</title>
    <link rel="stylesheet" href="/Public/style.css">
</head>
<body>
    <form action="/login" method="POST" class="loginForm">
        <h2>Connexion</h2>
        <input type="text" name="username" placeholder="Nom d'utilisateur" required autocomplete="username"
               value="<?php echo htmlspecialchars((string) ($lastLogin ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="password" name="password" placeholder="Mot de passe" required autocomplete="current-password">

        <?php if (($error ?? null) !== null) { ?>
            <div class="error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <button type="submit">Se connecter</button>
    </form>
</body>
</html>
