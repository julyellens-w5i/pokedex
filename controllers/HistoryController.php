<?php

/**
 * Controller (MVC) — histórico de buscas (GET).
 */

declare(strict_types=1);

class HistoryController
{
    private SearchHistoryModel $model;

    public function __construct(?SearchHistoryModel $model = null)
    {
        $this->model = $model ?? new SearchHistoryModel();
    }

    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET')
        {
            JsonView::error('Use GET.', 405);
        }

        if (!DatabaseService::available())
        {
            JsonView::json(['success' => true, 'data' => [], 'db' => false]);
        }

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
        JsonView::json(['success' => true, 'data' => $this->model->recent($limit), 'db' => true]);
    }
}
