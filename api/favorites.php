<?php

/**
 * Favoritos (MVC): GET | POST JSON | DELETE ?id= ou ?pokemon_id=
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new FavoriteController())->dispatch();
