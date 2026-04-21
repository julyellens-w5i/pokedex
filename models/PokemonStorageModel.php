<?php

/**
 * Persistência local de Pokémon para listagem (id, nome, imagem) e metadados de cache.
 * Reduz chamadas à PokeAPI quando o intervalo da Pokédex Nacional já está preenchido no banco.
 */

declare(strict_types=1);

class PokemonStorageModel
{
    private const META_NATIONAL_TOTAL = 'national_pokemon_total';

    private PDO $pdo;
    private bool $isMysql;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? DatabaseService::getPdo();
        if ($pdo === null)
        {
            throw new RuntimeException('PDO indisponível.');
        }
        $this->pdo = $pdo;
        $dsn = DB_DSN;
        $this->isMysql = is_string($dsn) && str_starts_with(strtolower($dsn), 'mysql');
    }

    public static function available(): bool
    {
        return DatabaseService::available();
    }

    public function setNationalTotalCount(int $total): void
    {
        if ($total <= 0)
        {
            return;
        }
        $this->setMeta(self::META_NATIONAL_TOTAL, (string) $total);
    }

    public function getNationalTotalCount(): ?int
    {
        $v = $this->getMeta(self::META_NATIONAL_TOTAL);
        if ($v === null || $v === '')
        {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public function countPokemon(): int
    {
        $n = $this->pdo->query('SELECT COUNT(*) AS c FROM pokemon')->fetch();
        if (!is_array($n))
        {
            return 0;
        }

        return max(0, (int) ($n['c'] ?? 0));
    }

    /**
     * Linhas consecutivas por id (Pokédex Nacional na PokeAPI = ordem crescente de id).
     *
     * @return list<array{id:int|string,name:string,image:string}>
     */
    public function fetchIdRange(int $startId, int $endId): array
    {
        if ($startId < 1 || $endId < $startId)
        {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, name, image FROM pokemon WHERE id >= :a AND id <= :b ORDER BY id ASC'
        );
        $stmt->execute(['a' => $startId, 'b' => $endId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows))
        {
            return [];
        }

        /** @var list<array{id:int|string,name:string,image:string}> */
        return $rows;
    }

    /**
     * @param list<array{id:int|string,name:string,image:string}> $rows
     */
    public function isCompleteConsecutiveSlice(array $rows, int $startId, int $expectedCount): bool
    {
        if ($expectedCount <= 0 || count($rows) !== $expectedCount)
        {
            return false;
        }
        $i = 0;
        foreach ($rows as $r)
        {
            if ((int) ($r['id'] ?? 0) !== $startId + $i)
            {
                return false;
            }
            ++$i;
        }

        return true;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{id:int,name:string,image:string}>
     */
    public function fetchByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $x): bool => $x > 0)));
        if ($ids === [])
        {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name, image FROM pokemon WHERE id IN ($ph)");
        $stmt->execute($ids);
        $out = [];
        while ($r = $stmt->fetch())
        {
            if (!is_array($r))
            {
                continue;
            }
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0)
            {
                continue;
            }
            $out[$id] = [
                'id' => $id,
                'name' => (string) ($r['name'] ?? ''),
                'image' => (string) ($r['image'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Índice nacional completo (id + nome em minúsculas) para busca sem chamar /pokemon?limit=10000.
     *
     * @return list<array{id:int,name:string}>
     */
    public function fetchAllForNationalIndex(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM pokemon ORDER BY id ASC');
        if ($stmt === false)
        {
            return [];
        }
        $rows = $stmt->fetchAll();
        if (!is_array($rows))
        {
            return [];
        }
        $out = [];
        foreach ($rows as $r)
        {
            if (!is_array($r))
            {
                continue;
            }
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'name' => strtolower((string) ($r['name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{id:int,name:string,image:string}> $items
     */
    public function upsertItems(array $items): void
    {
        if ($items === [])
        {
            return;
        }
        $sql = $this->isMysql
            ? 'INSERT INTO pokemon (id, name, image) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), image = VALUES(image)'
            : 'INSERT INTO pokemon (id, name, image) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, image = EXCLUDED.image';
        $stmt = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try
        {
            foreach ($items as $it)
            {
                $id = (int) ($it['id'] ?? 0);
                if ($id <= 0)
                {
                    continue;
                }
                $name = strtolower(trim((string) ($it['name'] ?? '')));
                $image = (string) ($it['image'] ?? '');
                if ($name === '' || $image === '')
                {
                    continue;
                }
                $stmt->execute([$id, $name, $image]);
            }
            $this->pdo->commit();
        }
        catch (Throwable $e)
        {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Garante linha em `pokemon` antes de INSERT em `favorites` (integridade da FK).
     *
     * @param string $nome slug ou nome vindo da API (ex.: pikachu)
     */
    public function ensurePokemonRowForFavorite(int $pokemonId, string $nome): void
    {
        if ($pokemonId <= 0)
        {
            return;
        }
        $name = strtolower(trim($nome));
        if ($name === '')
        {
            $name = 'species-' . $pokemonId;
        }
        $image = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/'
            . $pokemonId . '.png';
        $this->upsertItems([
            [
                'id' => $pokemonId,
                'name' => $name,
                'image' => $image,
            ],
        ]);
    }

    /**
     * Detalhe completo (JSON) cacheado após primeira montagem.
     *
     * @return array<string,mixed>|null
     */
    public function getDetailPayload(int $id): ?array
    {
        if ($id <= 0)
        {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT payload FROM pokemon_detail WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row))
        {
            return null;
        }
        $raw = $row['payload'] ?? null;
        if (!is_string($raw) || $raw === '')
        {
            return null;
        }
        try
        {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (Throwable)
        {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function saveDetailPayload(int $id, array $payload): void
    {
        if ($id <= 0)
        {
            return;
        }
        try
        {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        catch (Throwable)
        {
            return;
        }
        try
        {
            $sql = $this->isMysql
                ? 'INSERT INTO pokemon_detail (id, payload) VALUES (:id, :p) ON DUPLICATE KEY UPDATE payload = VALUES(payload)'
                : 'INSERT INTO pokemon_detail (id, payload) VALUES (:id, :p) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id, 'p' => $json]);
        }
        catch (Throwable)
        {
            /* tabela ausente ou payload grande */
        }
    }

    private function setMeta(string $key, string $value): void
    {
        $key = trim($key);
        if ($key === '')
        {
            return;
        }
        if ($this->isMysql)
        {
            $sql = 'INSERT INTO pokemon_cache_meta (meta_key, meta_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)';
        }
        else
        {
            $sql = 'INSERT INTO pokemon_cache_meta (meta_key, meta_value) VALUES (:k, :v) ON CONFLICT (meta_key) DO UPDATE SET meta_value = EXCLUDED.meta_value';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['k' => $key, 'v' => $value]);
    }

    private function getMeta(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT meta_value FROM pokemon_cache_meta WHERE meta_key = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();
        if (!is_array($row))
        {
            return null;
        }
        $v = $row['meta_value'] ?? null;

        return is_string($v) ? $v : null;
    }
}
