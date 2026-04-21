<?php

/**
 * Detalhe do Pokémon (MVC): ?id=25 ou ?name=pikachu
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new PokemonController())->show();
