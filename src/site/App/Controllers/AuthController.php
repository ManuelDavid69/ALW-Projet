<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameConfigRepository;
use App\Repositories\SaveRepository;
use App\Repositories\UserRepository;
use CPE\Framework\AbstractController;

/**
 * Connexion, déconnexion et ouverture de la session du joueur.
 */
class AuthController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion.
     */
    public function showLogin()
    {
        // Un joueur déjà connecté n'a rien à faire sur le formulaire.
        if (isset($_SESSION['player']['login'])) {
            header('Location: /dashboard');
            exit;
        }

        $this->app->view()->setParam('error', null);
        $this->app->view()->setParam('lastLogin', '');
        $this->app->view()->render('login.tpl.php');
    }

    /**
     * Traite la soumission du formulaire de connexion (route POST /login).
     */
    public function authenticate()
    {
        $login = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $error = null;
        if ($login === '' || $password === '') {
            $error = 'Veuillez renseigner votre identifiant et votre mot de passe.';
        } else {
            $repo = new UserRepository('Data/users.json');
            $user = $repo->get($login);

            // password_verify() compare la saisie au hash stocké : le mot de
            // passe en clair n'est jamais conservé dans les fichiers.
            if ($user === null || !password_verify($password, $user->password)) {
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        }

        if ($error !== null) {
            $this->app->view()->setParam('error', $error);
            $this->app->view()->setParam('lastLogin', $login);
            $this->app->view()->render('login.tpl.php');
            return;
        }

        // Nouvel identifiant de session après authentification pour éviter
        // qu'une session préparée à l'avance par un attaquant soit réutilisée.
        session_regenerate_id(true);
        $_SESSION['player'] = ['login' => $user->login];

        $config = new GameConfigRepository('Data/Config/game_config.json');
        $saves = new SaveRepository('Data/Saves');
        $saves->ensure($user->login, $config);

        header('Location: /dashboard');
        exit;
    }
}
