<?php

/**
 * Coleções nomeadas de Pokémon (além de favoritos).
 */

declare(strict_types=1);

class CollectionModel
{
    /**
     * @return list<array{id:int|string,nome:string,item_count:int}>
     */
    public function allWithCounts(): array
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return [];
        }
        try
        {
            $isMysql = str_starts_with(strtolower((string) DB_DSN), 'mysql');
            if ($isMysql)
            {
                $sql = 'SELECT c.id, c.nome, COUNT(i.id) AS item_count FROM collections c '
                    . 'LEFT JOIN collection_items i ON i.collection_id = c.id '
                    . 'GROUP BY c.id, c.nome ORDER BY c.data_registro DESC LIMIT 80';
            }
            else
            {
                $sql = 'SELECT c.id, c.nome, COUNT(i.id)::int AS item_count FROM collections c '
                    . 'LEFT JOIN collection_items i ON i.collection_id = c.id '
                    . 'GROUP BY c.id, c.nome ORDER BY c.data_registro DESC LIMIT 80';
            }
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll();

            return is_array($rows) ? $rows : [];
        }
        catch (Throwable)
        {
            return [];
        }
    }

    /**
     * @return list<array{id:int|string,pokemon_id:int,nome:string,data_registro:string}>
     */
    public function items(int $collectionId): array
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null || $collectionId <= 0)
        {
            return [];
        }
        try
        {
            $stmt = $pdo->prepare(
                'SELECT id, pokemon_id, nome, data_registro FROM collection_items WHERE collection_id = :cid ORDER BY data_registro DESC LIMIT 200'
            );
            $stmt->execute(['cid' => $collectionId]);
            $rows = $stmt->fetchAll();

            return is_array($rows) ? $rows : [];
        }
        catch (Throwable)
        {
            return [];
        }
    }

    public function create(string $nome): int
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null)
        {
            return 0;
        }
        $nome = trim($nome);
        if ($nome === '')
        {
            return 0;
        }
        $stmt = $pdo->prepare('INSERT INTO collections (nome, data_registro) VALUES (:n, NOW())');
        $stmt->execute(['n' => $nome]);

        return (int) $pdo->lastInsertId();
    }

    public function deleteCollection(int $id): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null || $id <= 0)
        {
            return false;
        }
        $stmt = $pdo->prepare('DELETE FROM collections WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function addItem(int $collectionId, int $pokemonId, string $nome): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null || $collectionId <= 0 || $pokemonId <= 0)
        {
            return false;
        }
        $nome = trim($nome);
        if ($nome === '')
        {
            return false;
        }
        try
        {
            (new PokemonStorageModel())->ensurePokemonRowForFavorite($pokemonId, $nome);
        }
        catch (Throwable)
        {
        }
        $stmt = $pdo->prepare(
            'INSERT INTO collection_items (collection_id, pokemon_id, nome, data_registro) VALUES (:cid, :pid, :nome, NOW())'
        );
        try
        {
            $stmt->execute(['cid' => $collectionId, 'pid' => $pokemonId, 'nome' => $nome]);

            return true;
        }
        catch (Throwable)
        {
            return false;
        }
    }

    public function removeItem(int $collectionId, int $pokemonId): bool
    {
        $pdo = DatabaseService::getPdo();
        if ($pdo === null || $collectionId <= 0 || $pokemonId <= 0)
        {
            return false;
        }
        $stmt = $pdo->prepare('DELETE FROM collection_items WHERE collection_id = :cid AND pokemon_id = :pid');
        $stmt->execute(['cid' => $collectionId, 'pid' => $pokemonId]);

        return $stmt->rowCount() > 0;
    }
}
