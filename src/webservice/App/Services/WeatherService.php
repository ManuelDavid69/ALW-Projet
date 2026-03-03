<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Client de l'API Open-Meteo et cache local de la météo.
 *
 * Le webservice joue ici le rôle de client HTTP : il interroge un service
 * externe, nettoie la réponse et la traduit en modificateur de production.
 * Un cache de dix minutes évite d'appeler Open-Meteo à chaque récolte.
 */
class WeatherService
{
    private const CACHE_TTL = 600;
    private const URL = 'https://api.open-meteo.com/v1/forecast'
        . '?latitude=45.7640&longitude=4.8357'
        . '&current=temperature_2m,weather_code,wind_speed_10m'
        . '&timezone=Europe%2FParis';

    private string $cachePath;

    public function __construct(?string $stateDirectory = null)
    {
        $directory = $stateDirectory ?? DATA_PATH . '/State';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier d\'état : ' . $directory);
        }

        $this->cachePath = rtrim($directory, '/\\') . '/weather.json';
    }

    /**
     * Météo courante, éventuellement servie depuis le cache.
     */
    public function current(): array
    {
        $cached = $this->readCache();

        if ($cached !== null && (time() - (int) ($cached['_timestamp'] ?? 0)) < self::CACHE_TTL) {
            return $this->publicPayload($cached);
        }

        $fresh = $this->fetch();
        if ($fresh !== null) {
            $this->writeCache($fresh);
            return $this->publicPayload($fresh);
        }

        if ($cached !== null) {
            $cached['stale'] = true;
            return $this->publicPayload($cached);
        }

        return $this->fallback();
    }

    public function productionModifier(): float
    {
        return (float) ($this->current()['productionModifier'] ?? 1.0);
    }

    private function fetch(): ?array
    {
        $body = $this->download(self::URL);
        if ($body === null) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['current']) || !is_array($data['current'])) {
            return null;
        }

        $current = $data['current'];
        $code = (int) ($current['weather_code'] ?? 0);
        $mapped = $this->mapWeatherCode($code);

        return [
            'location' => 'Lyon',
            'temperature' => round((float) ($current['temperature_2m'] ?? 0), 1),
            'windSpeed' => round((float) ($current['wind_speed_10m'] ?? 0), 1),
            'weatherCode' => $code,
            'condition' => $mapped['condition'],
            'icon' => $mapped['icon'],
            'productionModifier' => $mapped['modifier'],
            'advice' => $mapped['advice'],
            'fetchedAt' => gmdate('c'),
            '_timestamp' => time(),
        ];
    }

    private function download(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT => 'ALW-Ferme/1.0',
            ]);

            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            return is_string($body) && $status === 200 ? $body : null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 4,
                'header' => "User-Agent: ALW-Ferme/1.0\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return is_string($body) ? $body : null;
    }

    /**
     * @return array{condition: string, icon: string, modifier: float, advice: string}
     */
    private function mapWeatherCode(int $code): array
    {
        return match (true) {
            $code === 0 => [
                'condition' => 'Ciel dégagé',
                'icon' => '☀️',
                'modifier' => 1.2,
                'advice' => 'Le soleil accélère la pousse : production +20 %.',
            ],
            $code <= 2 => [
                'condition' => 'Peu nuageux',
                'icon' => '🌤️',
                'modifier' => 1.1,
                'advice' => 'Une météo clémente : production +10 %.',
            ],
            $code === 3 => [
                'condition' => 'Couvert',
                'icon' => '☁️',
                'modifier' => 1.0,
                'advice' => 'Production nominale.',
            ],
            in_array($code, [45, 48], true) => [
                'condition' => 'Brouillard',
                'icon' => '🌫️',
                'modifier' => 0.95,
                'advice' => 'Le brouillard ralentit un peu la ferme : production -5 %.',
            ],
            $code >= 51 && $code <= 67 => [
                'condition' => 'Pluie',
                'icon' => '🌧️',
                'modifier' => 1.1,
                'advice' => 'La pluie nourrit les champs : production +10 %.',
            ],
            $code >= 71 && $code <= 77 => [
                'condition' => 'Neige',
                'icon' => '🌨️',
                'modifier' => 0.8,
                'advice' => 'La neige ralentit la production : -20 %.',
            ],
            $code >= 80 && $code <= 82 => [
                'condition' => 'Averses',
                'icon' => '🌦️',
                'modifier' => 1.05,
                'advice' => 'Quelques averses : production +5 %.',
            ],
            $code >= 95 => [
                'condition' => 'Orage',
                'icon' => '⛈️',
                'modifier' => 0.85,
                'advice' => 'L\'orage perturbe la ferme : production -15 %.',
            ],
            default => [
                'condition' => 'Météo variable',
                'icon' => '🌡️',
                'modifier' => 1.0,
                'advice' => 'Production nominale.',
            ],
        };
    }

    private function readCache(): ?array
    {
        if (!is_file($this->cachePath)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->cachePath), true);

        return is_array($data) ? $data : null;
    }

    private function writeCache(array $weather): void
    {
        $json = json_encode($weather, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            @file_put_contents($this->cachePath, $json . PHP_EOL, LOCK_EX);
        }
    }

    private function publicPayload(array $weather): array
    {
        unset($weather['_timestamp']);
        $weather['stale'] = (bool) ($weather['stale'] ?? false);

        return $weather;
    }

    private function fallback(): array
    {
        return [
            'location' => 'Lyon',
            'temperature' => null,
            'windSpeed' => null,
            'weatherCode' => null,
            'condition' => 'Météo indisponible',
            'icon' => '🌡️',
            'productionModifier' => 1.0,
            'advice' => 'Production nominale en attendant les données météo.',
            'fetchedAt' => gmdate('c'),
            'stale' => true,
        ];
    }
}
