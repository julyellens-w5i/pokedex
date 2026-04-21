<?php

/**
 * Exemplo de configuração — copie para config.php
 */

declare(strict_types=1);

define('POKEAPI_BASE', 'https://pokeapi.co/api/v2');
define('CACHE_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache');
define('CACHE_TTL', 3600);

// MySQL (XAMPP)
define('DB_DSN', 'mysql:host=127.0.0.1;dbname=pokedex;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');

// PostgreSQL (exemplo)
// define('DB_DSN', 'pgsql:host=127.0.0.1;port=5432;dbname=pokedex');
// define('DB_USER', 'postgres');
// define('DB_PASS', 'sua_senha');
