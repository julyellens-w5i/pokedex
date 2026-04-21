<?php

/**
 * Rótulos em português (BR) e seleção de nomes/textos multilíngues da PokeAPI.
 * A API nem sempre inclui pt-BR; usamos fallback documentado.
 */

declare(strict_types=1);

class PokeLocalizedStrings
{
    /** Nomes oficiais dos tipos em PT-BR (jogos). */
    public const TYPE_PT = [
        'normal' => 'Normal',
        'fighting' => 'Lutador',
        'flying' => 'Voador',
        'poison' => 'Venenoso',
        'ground' => 'Terra',
        'rock' => 'Pedra',
        'bug' => 'Inseto',
        'ghost' => 'Fantasma',
        'steel' => 'Aço',
        'fire' => 'Fogo',
        'water' => 'Água',
        'grass' => 'Planta',
        'electric' => 'Elétrico',
        'psychic' => 'Psíquico',
        'ice' => 'Gelo',
        'dragon' => 'Dragão',
        'dark' => 'Noturno',
        'fairy' => 'Fada',
        'unknown' => '???',
        'shadow' => 'Sombra',
    ];

    /** Habilidades frequentes (API costuma não ter pt-BR em `names`). */
    private const ABILITY_PT = [
        'overgrow' => 'Supercrescimento',
        'chlorophyll' => 'Clorofila',
        'blaze' => 'Chama',
        'torrent' => 'Torrente',
        'swarm' => 'Enxame',
        'shed-skin' => 'Muda',
        'intimidate' => 'Intimidação',
        'static' => 'Estático',
        'lightning-rod' => 'Para-raios',
        'run-away' => 'Fuga',
        'keen-eye' => 'Olhar agudo',
        'huge-power' => 'Força enorme',
        'thick-fat' => 'Gordura',
        'levitate' => 'Levitação',
        'speed-boost' => 'Impulso',
        'clear-body' => 'Corpo puro',
        'sturdy' => 'Robusto',
        'inner-focus' => 'Foco interno',
        'flash-fire' => 'Corpo flamejante',
        'drought' => 'Estiagem',
        'sand-stream' => 'Areia movediça',
        'pressure' => 'Pressão',
        'multitype' => 'Multitipo',
        'adaptability' => 'Adaptável',
        'technician' => 'Técnico',
        'magic-guard' => 'Mágico',
        'sheer-force' => 'Força bruta',
        'moxie' => 'Audácia',
        'regenerator' => 'Regeneração',
        'protean' => 'Mutatipo',
        'tough-claws' => 'Garra dura',
        'pixilate' => 'Pixilado',
        'competitive' => 'Competitivo',
        'prankster' => 'Travesso',
        'justified' => 'Justificado',
        'slush-rush' => 'Corrida na neve',
        'snow-warning' => 'Nevasca',
        'drizzle' => 'Chuva',
    ];

    /** Rótulos das estatísticas base em PT-BR. */
    public const STAT_LABEL_PT = [
        'hp' => 'PS',
        'attack' => 'Ataque',
        'defense' => 'Defesa',
        'special-attack' => 'At. Esp.',
        'special-defense' => 'Def. Esp.',
        'speed' => 'Velocidade',
        'accuracy' => 'Precisão',
        'evasion' => 'Evasão',
    ];

    /**
     * @param list<array<string,mixed>> $namesList campo `names` da API
     */
    public static function pickLocalizedName(array $namesList): string
    {
        foreach (['pt-BR', 'pt', 'es', 'it', 'fr', 'de', 'en'] as $code)
        {
            foreach ($namesList as $row)
            {
                if (!is_array($row))
                {
                    continue;
                }
                $lang = $row['language']['name'] ?? '';
                $nm = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($lang === $code && $nm !== '')
                {
                    return $nm;
                }
            }
        }
        if ($namesList !== [] && is_array($namesList[0]) && isset($namesList[0]['name']))
        {
            return trim((string) $namesList[0]['name']);
        }
        return '';
    }

    /**
     * @param list<array<string,mixed>> $entries campo `flavor_text_entries` da espécie
     */
    public static function pickFlavorText(array $entries): ?array
    {
        foreach (['pt-BR', 'pt', 'es', 'en'] as $code)
        {
            foreach ($entries as $row)
            {
                if (!is_array($row))
                {
                    continue;
                }
                $lang = $row['language']['name'] ?? '';
                $txt = isset($row['flavor_text']) ? (string) $row['flavor_text'] : '';
                if ($lang === $code && $txt !== '')
                {
                    $clean = preg_replace("/\s+/u", ' ', str_replace(["\f", "\n", "\r"], ' ', $txt));
                    return [
                        'text' => trim((string) $clean),
                        'language' => $code,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $genera campo `genera` da espécie
     */
    public static function pickGenus(array $genera): string
    {
        foreach (['pt-BR', 'pt', 'es', 'en'] as $code)
        {
            foreach ($genera as $row)
            {
                if (!is_array($row))
                {
                    continue;
                }
                $lang = $row['language']['name'] ?? '';
                $g = isset($row['genus']) ? trim((string) $row['genus']) : '';
                if ($lang === $code && $g !== '')
                {
                    return $g;
                }
            }
        }
        return '';
    }

    public static function typeLabelPt(string $slug): string
    {
        $s = strtolower(trim($slug));

        return self::TYPE_PT[$s] ?? ucfirst($s);
    }

    public static function abilityLabelPt(string $slug, string $apiFallbackName): string
    {
        $s = strtolower(trim($slug));
        if (isset(self::ABILITY_PT[$s]))
        {
            return self::ABILITY_PT[$s];
        }
        $fromApi = trim($apiFallbackName);
        if ($fromApi !== '')
        {
            return $fromApi;
        }

        return ucfirst(str_replace('-', ' ', $s));
    }
}
