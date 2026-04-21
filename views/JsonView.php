<?php

/**
 * View em JSON — respostas padronizadas da API.
 */

declare(strict_types=1);

class JsonView
{
    /**
     * @param array<string,mixed> $payload
     */
    public static function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string,mixed>|list<mixed> $data
     */
    public static function success(mixed $data, int $code = 200): void
    {
        self::json(['success' => true, 'data' => $data], $code);
    }

    public static function error(string $message, int $code = 400): void
    {
        self::json(['success' => false, 'error' => $message], $code);
    }
}
