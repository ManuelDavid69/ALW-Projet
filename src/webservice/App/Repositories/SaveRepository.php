<?php

namespace App\Repositories;

use InvalidArgumentException;
use RuntimeException;

/**
 * Persistance de la partie d'un joueur dans un fichier JSON.
 *
 * Un fichier par joueur : Data/Saves/{login}.json. Le login est validé avant
 * tout accès au système de fichiers pour empêcher toute traversée de chemin.
 */
class SaveRepository
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Impossible de créer le dossier de sauvegardes : ' . $this->directory);
        }
    }

    public static function isValidLogin(string $login): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $login) === 1;
    }

    public function exists(string $login): bool
    {
        return is_file($this->path($login));
    }

    /**
     * Charge la sauvegarde d'un joueur.
     *
     * @throws RuntimeException si la sauvegarde est absente ou illisible
     */
    public function get(string $login): array
    {
        $path = $this->path($login);
        if (!is_file($path)) {
            throw new RuntimeException('Aucune sauvegarde pour le joueur ' . $login);
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Sauvegarde illisible pour le joueur ' . $login);
        }

        return $data;
    }

    /**
     * Crée la sauvegarde d'un joueur si elle n'existe pas encore.
     *
     * Les stocks démarrent à zéro et chaque bâtiment au niveau 1, comme dans
     * la maquette du sujet.
     */
    public function ensure(string $login, GameConfigRepository $config): array
    {
        if ($this->exists($login)) {
            return $this->get($login);
        }

        $inventory = [];
        foreach ($config->getProducts() as $productId => $product) {
            $inventory[$productId] = 0;
        }

        $buildings = [];
        foreach ($config->getBuildings() as $buildingId => $building) {
            $buildings[$buildingId] = ['level' => 1];
        }

        $now = gmdate('c');
        $save = [
            'login' => $login,
            'inventory' => $inventory,
            'buildings' => $buildings,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $this->write($login, $save);

        return $save;
    }

    public function save(array $save): void
    {
        $login = (string) ($save['login'] ?? '');
        $save['updatedAt'] = gmdate('c');

        $this->write($login, $save);
    }

    /**
     * Modifie une sauvegarde sous verrou exclusif.
     *
     * Le cycle lecture → modification → écriture doit être atomique : deux
     * requêtes simultanées (deux clics sur « Récolter ») ne doivent pas
     * écraser mutuellement leurs changements.
     *
     * @param callable(array): array $mutator reçoit puis retourne la sauvegarde
     */
    public function mutate(string $login, callable $mutator): array
    {
        $path = $this->path($login);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Impossible d\'ouvrir la sauvegarde de ' . $login);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Impossible de verrouiller la sauvegarde de ' . $login);
            }

            $contents = stream_get_contents($handle);
            $save = json_decode((string) $contents, true);
            if (!is_array($save)) {
                throw new RuntimeException('Sauvegarde illisible pour le joueur ' . $login);
            }

            $save = $mutator($save);
            $save['updatedAt'] = gmdate('c');

            $json = json_encode($save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Impossible d\'encoder la sauvegarde de ' . $login);
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $save;
        } finally {
            fclose($handle);
        }
    }

    private function write(string $login, array $save): void
    {
        $path = $this->path($login);
        $json = json_encode($save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Impossible d\'encoder la sauvegarde de ' . $login);
        }

        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d\'écrire la sauvegarde de ' . $login);
        }

        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Impossible de remplacer la sauvegarde de ' . $login);
        }
    }

    private function path(string $login): string
    {
        if (!self::isValidLogin($login)) {
            throw new InvalidArgumentException('Nom de joueur invalide.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $login . '.json';
    }
}
