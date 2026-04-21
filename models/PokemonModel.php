<?php

/**
 * Model — regras e montagem de dados de Pokémon (PokeAPI).
 */

declare(strict_types=1);

class PokemonModel
{
    private PokeApiService $api;
    private ?PokemonStorageModel $pokemonStore = null;

    public function __construct(?PokeApiService $api = null)
    {
        $this->api = $api ?? new PokeApiService();
    }

    private function pokemonStore(): ?PokemonStorageModel
    {
        if (!PokemonStorageModel::available())
        {
            return null;
        }
        try
        {
            if ($this->pokemonStore === null)
            {
                $this->pokemonStore = new PokemonStorageModel();
            }

            return $this->pokemonStore;
        }
        catch (Throwable)
        {
            return null;
        }
    }

    /**
     * @param list<array{id:int,name:string,image:string}> $items
     */
    private function persistListItemsCache(?PokemonStorageModel $store, array $items): void
    {
        if ($store === null || $items === [])
        {
            return;
        }
        try
        {
            $store->upsertItems($items);
        }
        catch (Throwable)
        {
            /* tabela ausente ou erro transitório — segue só com API */
        }
    }

    /**
     * @param list<array{id:int,name:string,image:string}> $items
     * @return list<array{id:int,name:string,image:string}>
     */
    private function hydrateListItemsFromDb(?PokemonStorageModel $store, array $items): array
    {
        if ($store === null || $items === [])
        {
            return $items;
        }
        try
        {
            $ids = [];
            foreach ($items as $it)
            {
                $ids[] = (int) ($it['id'] ?? 0);
            }
            $fromDb = $store->fetchByIds($ids);
            $out = [];
            foreach ($items as $it)
            {
                $id = (int) ($it['id'] ?? 0);
                $out[] = $fromDb[$id] ?? $it;
            }

            return $out;
        }
        catch (Throwable)
        {
            return $items;
        }
    }

    /**
     * Lista uma página (1-based). Sem região = Pokédex Nacional (todos).
     * Com `region` = espécies das Pokédexes da região (API), mescladas e deduplicadas.
     *
     * @return array{
     *   items: list<array{id:int,name:string,image:string}>,
     *   page:int,
     *   per_page:int,
     *   total:int,
     *   total_pages:int,
     *   region?: string,
     *   region_label?: string
     * }
     */
    public function findListPage(int $page, int $perPage, ?string $region = null): array
    {
        $regionKey = $region !== null ? strtolower(trim($region)) : '';
        if ($regionKey === '')
        {
            return $this->findListPageNational($page, $perPage);
        }

        return $this->findListPageForRegion($page, $perPage, $regionKey);
    }

    /**
     * @return array{items: list<array{id:int,name:string,image:string}>, page:int, per_page:int, total:int, total_pages:int}
     */
    private function findListPageNational(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $startId = $offset + 1;
        $endId = $offset + $perPage;

        $store = $this->pokemonStore();
        if ($store !== null)
        {
            try
            {
                $total = $store->getNationalTotalCount();
                if ($total === null)
                {
                    $peek = $this->api->getPokemonList(0, 1);
                    $cnt = (int) ($peek['count'] ?? 0);
                    if ($cnt > 0)
                    {
                        $store->setNationalTotalCount($cnt);
                        $total = $cnt;
                    }
                }
                if ($total !== null && $total > 0)
                {
                    $rows = $store->fetchIdRange($startId, $endId);
                    if ($store->isCompleteConsecutiveSlice($rows, $startId, $perPage))
                    {
                        $items = [];
                        foreach ($rows as $r)
                        {
                            $items[] = [
                                'id' => (int) $r['id'],
                                'name' => (string) $r['name'],
                                'image' => (string) $r['image'],
                            ];
                        }
                        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

                        return [
                            'items' => $items,
                            'page' => $page,
                            'per_page' => $perPage,
                            'total' => $total,
                            'total_pages' => $totalPages,
                        ];
                    }
                }
            }
            catch (Throwable)
            {
                /* fallback: PokeAPI */
            }
        }

        $pageData = $this->api->getPokemonList($offset, $perPage);
        $results = $pageData['results'] ?? [];
        $total = isset($pageData['count']) ? (int) $pageData['count'] : 0;
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

        $items = [];
        foreach ($results as $row)
        {
            if (!is_array($row))
            {
                continue;
            }
            $url = (string) ($row['url'] ?? '');
            $name = (string) ($row['name'] ?? '');
            $pid = PokeApiService::extractIdFromUrl($url);
            $items[] = $this->listItemFromPokemonNameAndId($name, $pid);
        }

        $this->persistListItemsCache($store, $items);
        if ($store !== null && $total > 0)
        {
            try
            {
                if ($store->getNationalTotalCount() === null)
                {
                    $store->setNationalTotalCount($total);
                }
            }
            catch (Throwable)
            {
            }
        }

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return array{items: list<array{id:int,name:string,image:string}>, page:int, per_page:int, total:int, total_pages:int, region:string, region_label:string}
     */
    private function findListPageForRegion(int $page, int $perPage, string $regionSlug): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        try
        {
            $species = $this->collectMergedRegionalSpecies($regionSlug);
        }
        catch (InvalidArgumentException $e)
        {
            throw $e;
        }
        catch (Throwable $e)
        {
            throw new RuntimeException('Não foi possível carregar a região.', 0, $e);
        }

        $total = count($species);
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($species, $offset, $perPage);

        $items = [];
        foreach ($slice as $row)
        {
            $items[] = $this->listItemFromPokemonNameAndId($row['name'], $row['species_id']);
        }

        $store = $this->pokemonStore();
        $items = $this->hydrateListItemsFromDb($store, $items);
        $this->persistListItemsCache($store, $items);

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'region' => $regionSlug,
            'region_label' => RegionModel::labelForSlug($regionSlug),
        ];
    }

    /**
     * Busca global por nome (substring) ou ID exato (apenas dígitos), sobre a Pokédex Nacional
     * ou sobre as espécies da região quando `region` é informado.
     *
     * @return array{
     *   items: list<array{id:int,name:string,image:string}>,
     *   total:int,
     *   query:string,
     *   scope:string,
     *   scope_label:string
     * }
     */
    public function searchGlobal(string $query, ?string $region, int $limit): array
    {
        $q = strtolower(trim($query));
        $limit = min(200, max(1, $limit));
        $regionKey = $region !== null ? strtolower(trim($region)) : '';

        if ($regionKey !== '')
        {
            try
            {
                $species = $this->collectMergedRegionalSpecies($regionKey);
            }
            catch (InvalidArgumentException $e)
            {
                throw $e;
            }
            catch (Throwable $e)
            {
                throw new RuntimeException('Não foi possível carregar a região.', 0, $e);
            }

            $candidates = [];
            foreach ($species as $row)
            {
                $candidates[] = [
                    'name' => strtolower((string) $row['name']),
                    'id' => (int) $row['species_id'],
                ];
            }
            $scope = $regionKey;
            $scopeLabel = RegionModel::labelForSlug($regionKey);
        }
        else
        {
            $scope = 'national';
            $scopeLabel = 'Pokédex Nacional';
            $candidates = [];
            $store = $this->pokemonStore();
            $fromDb = false;
            if ($store !== null)
            {
                try
                {
                    $metaTotal = $store->getNationalTotalCount();
                    if ($metaTotal !== null && $store->countPokemon() >= $metaTotal)
                    {
                        foreach ($store->fetchAllForNationalIndex() as $r)
                        {
                            $n = (string) ($r['name'] ?? '');
                            $id = (int) ($r['id'] ?? 0);
                            if ($n === '' || $id <= 0)
                            {
                                continue;
                            }
                            $candidates[] = [
                                'name' => $n,
                                'id' => $id,
                            ];
                        }
                        $fromDb = $candidates !== [];
                    }
                }
                catch (Throwable)
                {
                    $candidates = [];
                    $fromDb = false;
                }
            }
            if (!$fromDb)
            {
                $pageData = $this->api->getFullPokemonIndex();
                $candidates = [];
                $toUpsert = [];
                foreach ($pageData['results'] ?? [] as $row)
                {
                    if (!is_array($row))
                    {
                        continue;
                    }
                    $url = (string) ($row['url'] ?? '');
                    $name = strtolower(trim((string) ($row['name'] ?? '')));
                    if ($name === '')
                    {
                        continue;
                    }
                    $pid = PokeApiService::extractIdFromUrl($url);
                    $candidates[] = [
                        'name' => $name,
                        'id' => $pid,
                    ];
                    $toUpsert[] = $this->listItemFromPokemonNameAndId($name, $pid);
                }
                $this->persistListItemsCache($store, $toUpsert);
                $cntFromApi = (int) ($pageData['count'] ?? 0);
                if ($store !== null && $cntFromApi > 0)
                {
                    try
                    {
                        $store->setNationalTotalCount($cntFromApi);
                    }
                    catch (Throwable)
                    {
                    }
                }
            }
        }

        $isDigits = (bool) preg_match('/^\d+$/', $q);
        $matches = [];
        foreach ($candidates as $c)
        {
            $hit = false;
            if ($isDigits)
            {
                if ((string) $c['id'] === $q)
                {
                    $hit = true;
                }
            }
            elseif (str_contains($c['name'], $q))
            {
                $hit = true;
            }
            if ($hit)
            {
                $matches[] = $c;
            }
        }

        $total = count($matches);
        if ($isDigits)
        {
            usort(
                $matches,
                static function (array $a, array $b): int
                {
                    return $a['id'] <=> $b['id'];
                }
            );
        }
        else
        {
            usort(
                $matches,
                static function (array $a, array $b): int
                {
                    $cmp = strcmp($a['name'], $b['name']);
                    if ($cmp !== 0)
                    {
                        return $cmp;
                    }

                    return $a['id'] <=> $b['id'];
                }
            );
        }

        $matches = array_slice($matches, 0, $limit);
        $items = [];
        foreach ($matches as $m)
        {
            $items[] = $this->listItemFromPokemonNameAndId($m['name'], $m['id']);
        }

        $store = $this->pokemonStore();
        $items = $this->hydrateListItemsFromDb($store, $items);
        $this->persistListItemsCache($store, $items);

        return [
            'items' => $items,
            'total' => $total,
            'query' => $query,
            'scope' => $scope,
            'scope_label' => $scopeLabel,
        ];
    }

    /**
     * @return list<array{name:string,species_id:int}>
     */
    private function collectMergedRegionalSpecies(string $regionSlug): array
    {
        $region = $this->api->getRegionByIdOrName($regionSlug);
        $refs = $region['pokedexes'] ?? [];
        if (!is_array($refs) || $refs === [])
        {
            throw new InvalidArgumentException('Região sem Pokédex regional.');
        }

        $seen = [];
        $ordered = [];

        foreach ($refs as $ref)
        {
            if (!is_array($ref))
            {
                continue;
            }
            $dexName = (string) ($ref['name'] ?? '');
            $dexUrl = (string) ($ref['url'] ?? '');
            if ($dexUrl === '' || !$this->shouldIncludeRegionalPokedex($dexName))
            {
                continue;
            }
            $dex = $this->api->fetchJson($dexUrl);
            foreach ($dex['pokemon_entries'] ?? [] as $entry)
            {
                if (!is_array($entry))
                {
                    continue;
                }
                $sp = $entry['pokemon_species'] ?? null;
                if (!is_array($sp))
                {
                    continue;
                }
                $name = strtolower(trim((string) ($sp['name'] ?? '')));
                if ($name === '')
                {
                    continue;
                }
                if (isset($seen[$name]))
                {
                    continue;
                }
                $seen[$name] = true;
                $spUrl = (string) ($sp['url'] ?? '');
                $ordered[] = [
                    'name' => $name,
                    'species_id' => PokeApiService::extractIdFromUrl($spUrl),
                ];
            }
        }

        if ($ordered === [])
        {
            throw new InvalidArgumentException('Nenhuma entrada de Pokédex para esta região.');
        }

        return $ordered;
    }

    private function shouldIncludeRegionalPokedex(string $dexName): bool
    {
        $n = strtolower($dexName);
        if ($n === '' || $n === 'national')
        {
            return false;
        }
        if (str_contains($n, 'letsgo') || str_contains($n, 'lets-go'))
        {
            return false;
        }
        /** @var list<string> */
        static $exclude = ['lumiose-city', 'hyperspace'];
        return !in_array($n, $exclude, true);
    }

    /**
     * @return array{id:int,name:string,image:string}
     */
    private function listItemFromPokemonNameAndId(string $name, int $id): array
    {
        $name = strtolower(trim($name));

        return [
            'id' => $id,
            'name' => $name,
            'image' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' . $id . '.png',
        ];
    }

    /**
     * Detalhe + evoluções + textos localizados quando disponíveis na API.
     *
     * @return array<string,mixed>
     */
    public function findDetail(string $idOrName): array
    {
        try
        {
            $pokemon = $this->api->getPokemonByIdOrName($idOrName);
        }
        catch (InvalidArgumentException $e)
        {
            throw $e;
        }
        catch (Throwable $e)
        {
            throw new RuntimeException('Não foi possível carregar o Pokémon.', 0, $e);
        }

        $speciesUrl = $pokemon['species']['url'] ?? '';
        $species = null;
        $evolutionStages = [];
        $evolutionChainUrl = null;

        if ($speciesUrl !== '')
        {
            try
            {
                $species = $this->api->getSpeciesByUrl($speciesUrl);
                $chainUrl = $species['evolution_chain']['url'] ?? null;
                if (is_string($chainUrl) && $chainUrl !== '')
                {
                    $evolutionChainUrl = $chainUrl;
                    $chain = $this->api->getEvolutionChainByUrl($chainUrl);
                    $root = $chain['chain'] ?? null;
                    if (is_array($root))
                    {
                        $evolutionStages = $this->buildEvolutionStages($root);
                        $evolutionStages = $this->enrichEvolutionDisplayNames($evolutionStages);
                    }
                }
            }
            catch (Throwable)
            {
                $evolutionStages = [];
            }
        }

        return [
            'pokemon' => $this->mapPokemonRich($pokemon, is_array($species) ? $species : null),
            'evolution_stages' => $evolutionStages,
            'evolution_chain_url' => $evolutionChainUrl,
        ];
    }

    /**
     * @param list<list<array{name:string,species_id:int,display_name?:string}>> $stages
     * @return list<list<array{name:string,species_id:int,display_name:string}>>
     */
    private function enrichEvolutionDisplayNames(array $stages): array
    {
        foreach ($stages as $gi => $group)
        {
            foreach ($group as $i => $entry)
            {
                $sid = (int) ($entry['species_id'] ?? 0);
                $slug = (string) ($entry['name'] ?? '');
                $display = $slug !== '' ? ucfirst(str_replace('-', ' ', $slug)) : '';
                if ($sid > 0)
                {
                    try
                    {
                        $sp = $this->api->fetchJson(POKEAPI_BASE . '/pokemon-species/' . $sid);
                        $picked = PokeLocalizedStrings::pickLocalizedName($sp['names'] ?? []);
                        if ($picked !== '')
                        {
                            $display = $picked;
                        }
                    }
                    catch (Throwable)
                    {
                        /* mantém display derivado do slug */
                    }
                }
                $stages[$gi][$i]['display_name'] = $display;
            }
        }
        return $stages;
    }

    /**
     * @return list<list<array{name:string,species_id:int,display_name:string}>>
     */
    private function buildEvolutionStages(array $rootNode): array
    {
        $byDepth = [];
        $queue = [['node' => $rootNode, 'depth' => 0]];

        while ($queue !== [])
        {
            $item = array_shift($queue);
            $depth = $item['depth'];
            $node = $item['node'];
            if (!isset($byDepth[$depth]))
            {
                $byDepth[$depth] = [];
            }
            $species = $node['species'] ?? [];
            $name = isset($species['name']) ? (string) $species['name'] : '';
            $url = isset($species['url']) ? (string) $species['url'] : '';
            $byDepth[$depth][] = [
                'name' => $name,
                'species_id' => PokeApiService::extractIdFromUrl($url),
                'display_name' => '',
            ];
            foreach ($node['evolves_to'] ?? [] as $child)
            {
                if (is_array($child))
                {
                    $queue[] = ['node' => $child, 'depth' => $depth + 1];
                }
            }
        }

        ksort($byDepth);
        return array_values($byDepth);
    }

    /**
     * @param array<string,mixed> $pokemon
     * @param array<string,mixed>|null $species
     * @return array<string,mixed>
     */
    private function mapPokemonRich(array $pokemon, ?array $species): array
    {
        $id = (int) ($pokemon['id'] ?? 0);
        $slug = strtolower((string) ($pokemon['name'] ?? ''));

        $sprites = $pokemon['sprites'] ?? [];
        $other = $sprites['other'] ?? [];
        $official = $other['official-artwork'] ?? [];
        $img = $official['front_default'] ?? ($sprites['front_default'] ?? null);

        $typesOut = [];
        foreach ($pokemon['types'] ?? [] as $t)
        {
            if (!is_array($t) || !isset($t['type']['name']))
            {
                continue;
            }
            $tslug = strtolower((string) $t['type']['name']);
            $typesOut[] = [
                'slug' => $tslug,
                'label' => PokeLocalizedStrings::typeLabelPt($tslug),
            ];
        }

        $abilitiesOut = [];
        foreach ($pokemon['abilities'] ?? [] as $a)
        {
            if (!is_array($a) || !isset($a['ability']['name']))
            {
                continue;
            }
            $aslug = strtolower((string) $a['ability']['name']);
            $url = (string) ($a['ability']['url'] ?? '');
            $apiName = '';
            if ($url !== '')
            {
                try
                {
                    $ab = $this->api->fetchJson($url);
                    $apiName = PokeLocalizedStrings::pickLocalizedName($ab['names'] ?? []);
                }
                catch (Throwable)
                {
                    $apiName = '';
                }
            }
            $abilitiesOut[] = [
                'slug' => $aslug,
                'label' => PokeLocalizedStrings::abilityLabelPt($aslug, $apiName),
                'is_hidden' => !empty($a['is_hidden']),
            ];
        }

        $nameDisplay = $slug !== '' ? ucfirst(str_replace('-', ' ', $slug)) : '';
        if (is_array($species))
        {
            $picked = PokeLocalizedStrings::pickLocalizedName($species['names'] ?? []);
            if ($picked !== '')
            {
                $nameDisplay = $picked;
            }
        }

        $genus = is_array($species) ? PokeLocalizedStrings::pickGenus($species['genera'] ?? []) : '';
        $flavor = is_array($species) ? PokeLocalizedStrings::pickFlavorText($species['flavor_text_entries'] ?? []) : null;
        $flavorText = $flavor['text'] ?? '';
        $flavorLang = $flavor['language'] ?? '';

        $statsOut = [];
        foreach ($pokemon['stats'] ?? [] as $s)
        {
            if (!is_array($s) || !isset($s['stat']['name']))
            {
                continue;
            }
            $sn = strtolower((string) $s['stat']['name']);
            $statsOut[] = [
                'id' => $sn,
                'label' => PokeLocalizedStrings::STAT_LABEL_PT[$sn] ?? ucfirst(str_replace('-', ' ', $sn)),
                'base' => isset($s['base_stat']) ? (int) $s['base_stat'] : 0,
            ];
        }

        return [
            'id' => $id,
            'name' => $slug,
            'name_display' => $nameDisplay,
            'image' => $img,
            'types' => $typesOut,
            'height' => isset($pokemon['height']) ? (int) $pokemon['height'] : 0,
            'weight' => isset($pokemon['weight']) ? (int) $pokemon['weight'] : 0,
            'abilities' => $abilitiesOut,
            'genus' => $genus,
            'flavor_text' => $flavorText,
            'flavor_language' => $flavorLang,
            'stats' => $statsOut,
        ];
    }
}
