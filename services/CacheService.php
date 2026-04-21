<?php

/**
 * Cache simples em arquivos JSON (por chave hash da URL).
 */

declare(strict_types=1);

class CacheService
{
    private string $dir;
    private int $ttl;

    public function __construct(string $dir = CACHE_DIR, int $ttl = CACHE_TTL)
    {
        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $this->ttl = $ttl;
        if (!is_dir($this->dir))
        {
            @mkdir($this->dir, 0755, true);
        }
    }

    public function get(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path))
        {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false)
        {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['_expires'], $data['payload']))
        {
            return null;
        }
        if (time() > (int) $data['_expires'])
        {
            @unlink($path);
            return null;
        }
        return $data['payload'];
    }

    public function set(string $key, array $payload): void
    {
        $path = $this->path($key);
        $wrap = [
            '_expires' => time() + $this->ttl,
            'payload' => $payload,
        ];
        @file_put_contents($path, json_encode($wrap), LOCK_EX);
    }

    private function path(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'c_' . hash('sha256', $key) . '.json';
    }
}
