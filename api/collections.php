<?php

/**
 * Coleções nomeadas: GET lista / ?items=id, POST {action}, DELETE.
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new CollectionController())->dispatch();
