<?php

/**
 * Lista paginada (MVC): ?page=1&limit=20
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new PokemonController())->index();
