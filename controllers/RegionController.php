<?php

/**
 * Controller (MVC) — lista de regiões para filtro.
 */

declare(strict_types=1);

class RegionController
{
    private RegionModel $model;

    public function __construct(?RegionModel $model = null)
    {
        $this->model = $model ?? new RegionModel();
    }

    public function index(): void
    {
        try
        {
            JsonView::success($this->model->findAll());
        }
        catch (Throwable $e)
        {
            JsonView::error($e->getMessage(), 502);
        }
    }
}
