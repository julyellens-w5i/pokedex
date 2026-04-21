<?php

/**
 * Model — persistência de favoritos.
 */

declare(strict_types=1);

class FavoriteModel
{
    /**
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return [];
        }
        $stmt = $pdo->query(
            'SELECT id, pokemon_id, nome, data_registro FROM favorites ORDER BY data_registro DESC LIMIT 100'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function create(int $pokemonId, string $nome): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return false;
        }
        $nome = trim($nome);
        if ($nome === '' || $pokemonId <= 0)
        {
            return false;
        }
        $sql = 'INSERT INTO favorites (pokemon_id, nome, data_registro) VALUES (:pid, :nome, NOW())';
        try
        {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['pid' => $pokemonId, 'nome' => $nome]);
            return true;
        }
        catch (Throwable)
        {
            return false;
        }
    }

    public function deleteById(int $favoriteId): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return false;
        }
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE id = :id');
        $stmt->execute(['id' => $favoriteId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByPokemonId(int $pokemonId): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return false;
        }
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE pokemon_id = :pid');
        $stmt->execute(['pid' => $pokemonId]);
        return $stmt->rowCount() > 0;
    }
}
