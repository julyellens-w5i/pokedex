<?php

/**
 * Busca global (MVC): ?q=pika&limit=80&region=unova
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new PokemonController())->search();
