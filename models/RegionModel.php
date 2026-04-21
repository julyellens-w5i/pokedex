<?php

/**
 * Model — regiões (endpoint /region da PokeAPI).
 */

declare(strict_types=1);

class RegionModel
{
    private PokeApiService $api;

    /** Nomes amigáveis em português (slug da API → rótulo). */
    private const LABELS_PT = [
        'kanto' => 'Kanto',
        'johto' => 'Johto',
        'hoenn' => 'Hoenn',
        'sinnoh' => 'Sinnoh',
        'unova' => 'Unova',
        'kalos' => 'Kalos',
        'alola' => 'Alola',
        'galar' => 'Galar',
        'hisui' => 'Hisui',
        'paldea' => 'Paldea',
    ];

    public function __construct(?PokeApiService $api = null)
    {
        $this->api = $api ?? new PokeApiService();
    }

    /**
     * Lista regiões ordenadas por id (Kanto, Johto, …).
     *
     * @return list<array{name:string,label:string}>
     */
    public function findAll(): array
    {
        $data = $this->api->getRegionList(100);
        $results = $data['results'] ?? [];
        if (!is_array($results))
        {
            return [];
        }
        usort(
            $results,
            static function (array $a, array $b): int
            {
                return PokeApiService::extractIdFromUrl((string) ($a['url'] ?? ''))
                    <=> PokeApiService::extractIdFromUrl((string) ($b['url'] ?? ''));
            }
        );
        $out = [];
        foreach ($results as $row)
        {
            if (!is_array($row))
            {
                continue;
            }
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === '')
            {
                continue;
            }
            $out[] = [
                'name' => $name,
                'label' => self::labelForSlug($name),
            ];
        }
        return $out;
    }

    public static function labelForSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        return self::LABELS_PT[$slug] ?? ucfirst($slug);
    }
}
