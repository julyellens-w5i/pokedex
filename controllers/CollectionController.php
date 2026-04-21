<?php

/**
 * Coleções (CRUD + itens).
 */

declare(strict_types=1);

class CollectionController
{
    private CollectionModel $model;

    public function __construct(?CollectionModel $model = null)
    {
        $this->model = $model ?? new CollectionModel();
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!DatabaseService::available())
        {
            if ($method === 'GET')
            {
                JsonView::success([], 200);
            }
            JsonView::error('Banco de dados não configurado.', 503);
        }

        switch ($method)
        {
            case 'GET':
                $itemsCol = isset($_GET['items']) ? (int) $_GET['items'] : 0;
                if ($itemsCol > 0)
                {
                    JsonView::success($this->model->items($itemsCol));
                    break;
                }
                JsonView::success($this->model->allWithCounts());
                break;

            case 'POST':
                $raw = file_get_contents('php://input');
                $body = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($body))
                {
                    JsonView::error('JSON inválido.', 400);
                }
                $action = isset($body['action']) ? (string) $body['action'] : '';
                if ($action === 'create')
                {
                    $nome = isset($body['nome']) ? (string) $body['nome'] : '';
                    $id = $this->model->create($nome);
                    if ($id > 0)
                    {
                        JsonView::success(['id' => $id]);
                    }
                    JsonView::error('Não foi possível criar a coleção.', 400);
                }
                if ($action === 'add')
                {
                    $cid = isset($body['collection_id']) ? (int) $body['collection_id'] : 0;
                    $pid = isset($body['pokemon_id']) ? (int) $body['pokemon_id'] : 0;
                    $nome = isset($body['nome']) ? (string) $body['nome'] : '';
                    if ($this->model->addItem($cid, $pid, $nome))
                    {
                        JsonView::success(['ok' => true]);
                    }
                    JsonView::error('Não foi possível adicionar (duplicado ou coleção inválida).', 400);
                }
                JsonView::error('Ação inválida.', 400);
                break;

            case 'DELETE':
                $fid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                if ($fid > 0 && $this->model->deleteCollection($fid))
                {
                    JsonView::json(['success' => true]);
                }
                $cid = isset($_GET['collection_id']) ? (int) $_GET['collection_id'] : 0;
                $pid = isset($_GET['pokemon_id']) ? (int) $_GET['pokemon_id'] : 0;
                if ($cid > 0 && $pid > 0 && $this->model->removeItem($cid, $pid))
                {
                    JsonView::json(['success' => true]);
                }
                JsonView::error('Item ou coleção não encontrado.', 404);
                break;

            default:
                JsonView::error('Método não suportado.', 405);
        }
    }
}
