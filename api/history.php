<?php

/**
 * Histórico (MVC): GET ?limit=30
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

(new HistoryController())->index();
