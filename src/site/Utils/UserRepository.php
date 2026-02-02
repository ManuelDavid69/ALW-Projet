<?php

// namespace App\Repositories;

// use App\Models\User;

// Attention : tous les require/include se font relativement au script exécuté
// require_once "App/Models/User.php";

/**
 * Undocumented class
 */
class UserRepository
{
    private string $filePath;
    private array $users = [];

    /**
     * Mémorise l'emplacement du JSON décrivant les utilisateurs et le charge.
     *
     * @param string $filePath
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->loadUsers();
    }

    /**
     * Charges les utilisateurs à partir d'un fichier JSON donné.
     *
     * @return void
     */
    public function loadUsers()
    {
        if (!file_exists($this->filePath)) {
            throw new Exception("Fichier JSON introuvable: " . $this->filePath);
        }

        $data = json_decode(file_get_contents($this->filePath), true);

        foreach ($data as $login => $userData) {
            $user = new User();
            $user->login = $login;
            $user->password = $userData['password'];

            $this->users[$user->login] = $user;
        }
    }

    /**
     * Retourne un utilisateur par son login.
     *
     * @param string $login
     * @return User|null
     */
    public function get(string $login): ?User {
        return $this->users[$login] ?? null;
    }

    /**
     * Retourne tous les utilisateurs.
     *
     * @return User[]
     */
    public function getAll(): array {
        return $this->users;
    }
}
