<?php

/**
 * Conexão PDO opcional — favoritos e histórico.
 */

declare(strict_types=1);

class DatabaseService
{
    private static ?PDO $pdo = null;

    public static function getPdo(): ?PDO
    {
        if (self::$pdo !== null)
        {
            return self::$pdo;
        }
        if (DB_DSN === '' || DB_DSN === null)
        {
            return null;
        }
        try
        {
            self::$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$pdo;
        }
        catch (Throwable $e)
        {
            self::$pdo = null;
            return null;
        }
    }

    public static function available(): bool
    {
        return self::getPdo() !== null;
    }
}
