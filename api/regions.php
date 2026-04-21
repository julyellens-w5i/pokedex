<?php

/**
 * Lista regiões (MVC): GET — para filtro / agrupamento por região.
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new RegionController())->index();
