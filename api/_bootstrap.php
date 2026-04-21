<?php

/**
 * Autoload simples (ordem importa): config → services → models → views → controllers.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once $root . '/services/CacheService.php';
require_once $root . '/services/PokeApiService.php';
require_once $root . '/services/PokeLocalizedStrings.php';
require_once $root . '/services/DatabaseService.php';
require_once $root . '/models/RegionModel.php';
require_once $root . '/models/PokemonModel.php';
require_once $root . '/models/FavoriteModel.php';
require_once $root . '/models/SearchHistoryModel.php';
require_once $root . '/views/JsonView.php';
require_once $root . '/controllers/PokemonController.php';
require_once $root . '/controllers/RegionController.php';
require_once $root . '/controllers/FavoriteController.php';
require_once $root . '/controllers/HistoryController.php';
