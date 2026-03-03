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
            header('Location: /dashboard', true, 303);
            exit;
        }

        // Le message d'erreur est conservé le temps d'une redirection (PRG).
        $error = $_SESSION['login_error'] ?? null;
        $lastLogin = (string) ($_SESSION['login_last'] ?? '');
        unset($_SESSION['login_error'], $_SESSION['login_last']);

        echo $this->app->twig()->render('login.html.twig', [
            'error' => $error,
            'lastLogin' => $lastLogin,
        ]);
    }

    /**
     * Traite la soumission du formulaire de connexion (route POST /login).
     */
    public function authenticate()
    {
        $login = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $error = null;
        $user = null;
        if ($login === '' || $password === '') {
            $error = 'Veuillez renseigner votre identifiant et votre mot de passe.';
        } else {
            $repo = new UserRepository(DATA_PATH . '/users.json');
            $user = $repo->get($login);

            // password_verify() compare la saisie au hash stocké : le mot de
            // passe en clair n'est jamais conservé dans les fichiers.
            if ($user === null || !password_verify($password, $user->password)) {
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        }

        if ($error !== null) {
            // Post/Redirect/Get : on redirige en GET vers le formulaire, et le
            // message voyage dans la session (flash) pour ne pas être perdu.
            $_SESSION['login_error'] = $error;
            $_SESSION['login_last'] = $login;

            header('Location: /login', true, 303);
            exit;
        }

        // Nouvel identifiant de session après authentification pour éviter
        // qu'une session préparée à l'avance par un attaquant soit réutilisée.
        session_regenerate_id(true);
        $_SESSION['player'] = ['login' => $user->login];

        $config = new GameConfigRepository(DATA_PATH . '/Config/game_config.json');
        $saves = new SaveRepository(DATA_PATH . '/Saves');
        $saves->ensure($user->login, $config);

        header('Location: /dashboard', true, 303);
        exit;
    }

    /**
     * Ferme la session et renvoie vers la page de connexion.
     */
    public function logout()
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: /login', true, 303);
        exit;
    }
}
