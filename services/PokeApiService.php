<?php

/**
 * Consumo da PokeAPI com cache e tratamento de erros.
 */

declare(strict_types=1);

class PokeApiService
{
    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    /**
     * GET JSON da API (com cache).
     */
    public function fetchJson(string $url): array
    {
        $cached = $this->cache->get($url);
        if ($cached !== null)
        {
            return $cached;
        }

        $body = $this->httpGet($url);
        $data = json_decode($body, true);
        if (!is_array($data))
        {
            throw new RuntimeException('Resposta inválida da PokeAPI.');
        }
        $this->cache->set($url, $data);
        return $data;
    }

    private function httpGet(string $url): string
    {
        $maxAttempts = 4;
        $lastException = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++)
        {
            try
            {
                return $this->httpGetOnce($url);
            }
            catch (InvalidArgumentException $e)
            {
                throw $e;
            }
            catch (Throwable $e)
            {
                $lastException = $e;
                if ($attempt >= $maxAttempts)
                {
                    break;
                }
                $delayMs = (int) (300 * (2 ** ($attempt - 1)));
                usleep($delayMs * 1000);
            }
        }
        throw $lastException instanceof Throwable
            ? $lastException
            : new RuntimeException('Falha ao obter dados da PokeAPI.');
    }

    /**
     * Uma tentativa GET (sem retry).
     */
    private function httpGetOnce(string $url): string
    {
        if (function_exists('curl_init'))
        {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $out = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($out === false)
            {
                throw new RuntimeException('Falha de rede: ' . $err);
            }
            if ($code === 404)
            {
                throw new InvalidArgumentException('Recurso não encontrado.');
            }
            if ($code === 429 || $code === 502 || $code === 503 || $code === 504)
            {
                throw new RuntimeException('PokeAPI temporariamente indisponível (HTTP ' . $code . ').');
            }
            if ($code < 200 || $code >= 300)
            {
                throw new RuntimeException('PokeAPI retornou HTTP ' . $code);
            }
            return $out;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        if ($out === false)
        {
            throw new RuntimeException('Falha ao obter dados da PokeAPI.');
        }
        return $out;
    }

    public function getPokemonByIdOrName(string $idOrName): array
    {
        $q = rawurlencode(strtolower(trim($idOrName)));
        $url = POKEAPI_BASE . '/pokemon/' . $q;
        return $this->fetchJson($url);
    }

    public function getPokemonList(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = min(100, max(1, $limit));
        $url = POKEAPI_BASE . '/pokemon?offset=' . $offset . '&limit=' . $limit;
        return $this->fetchJson($url);
    }

    /**
     * Lista completa de Pokémon (nome + URL) — uma requisição, cacheada.
     * Usada pela busca global no backend.
     */
    public function getFullPokemonIndex(): array
    {
        $url = POKEAPI_BASE . '/pokemon?offset=0&limit=10000';
        return $this->fetchJson($url);
    }

    /** Lista de regiões (nomes + URLs). */
    public function getRegionList(int $limit = 50): array
    {
        $limit = min(100, max(1, $limit));
        $url = POKEAPI_BASE . '/region?limit=' . $limit;
        return $this->fetchJson($url);
    }

    /** Detalhe de uma região por id ou slug (ex: kanto, unova). */
    public function getRegionByIdOrName(string $idOrName): array
    {
        $q = rawurlencode(strtolower(trim($idOrName)));
        $url = POKEAPI_BASE . '/region/' . $q;
        return $this->fetchJson($url);
    }

    public function getSpeciesByUrl(string $speciesUrl): array
    {
        return $this->fetchJson($speciesUrl);
    }

    public function getEvolutionChainByUrl(string $chainUrl): array
    {
        return $this->fetchJson($chainUrl);
    }

    /** Pokémon que possuem o tipo (ex.: fire, water). */
    public function getTypeByName(string $slug): array
    {
        $q = rawurlencode(strtolower(trim($slug)));
        $url = POKEAPI_BASE . '/type/' . $q;

        return $this->fetchJson($url);
    }

    /**
     * Extrai ID numérico do final da URL da PokeAPI.
     */
    public static function extractIdFromUrl(string $url): int
    {
        if (preg_match('~/(\d+)/?\s*$~', $url, $m))
        {
            return (int) $m[1];
        }
        return 0;
    }
}
