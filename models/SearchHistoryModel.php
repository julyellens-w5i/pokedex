<?php

/**
 * Model — histórico de termos buscados.
 */

declare(strict_types=1);

class SearchHistoryModel
{
    /**
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit = 30): array
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return [];
        }
        $limit = min(100, max(1, $limit));
        $stmt = $pdo->prepare(
            'SELECT id, termo, data_registro FROM search_history ORDER BY data_registro DESC LIMIT ' . (int) $limit
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function record(string $termo): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return false;
        }
        $termo = trim($termo);
        if ($termo === '' || strlen($termo) > 120)
        {
            return false;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO search_history (termo, data_registro) VALUES (:t, NOW())'
        );
        try
        {
            $stmt->execute(['t' => $termo]);
            return true;
        }
        catch (Throwable)
        {
            return false;
        }
    }
}
