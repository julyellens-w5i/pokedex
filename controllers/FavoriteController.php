<?php

/**
 * Controller (MVC) — favoritos (GET/POST/DELETE).
 */

declare(strict_types=1);

class FavoriteController
{
    private FavoriteModel $model;

    public function __construct(?FavoriteModel $model = null)
    {
        $this->model = $model ?? new FavoriteModel();
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!DatabaseService::available())
        {
            if ($method === 'GET')
            {
                JsonView::json(['success' => true, 'data' => [], 'db' => false]);
            }
            JsonView::json(['success' => false, 'error' => 'Banco de dados não configurado.', 'db' => false], 503);
        }

        switch ($method)
        {
            case 'GET':
                JsonView::json(['success' => true, 'data' => $this->model->all(), 'db' => true]);
                break;

            case 'POST':
                $raw = file_get_contents('php://input');
                $body = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($body))
                {
                    JsonView::error('JSON inválido.', 400);
                }
                $pid = isset($body['pokemon_id']) ? (int) $body['pokemon_id'] : 0;
                $nome = isset($body['nome']) ? (string) $body['nome'] : '';
                if ($this->model->create($pid, $nome))
                {
                    JsonView::json(['success' => true]);
                }
                JsonView::error('Não foi possível favoritar (duplicado ou inválido).', 400);
                break;

            case 'DELETE':
                $fid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                $pokemonId = isset($_GET['pokemon_id']) ? (int) $_GET['pokemon_id'] : 0;
                if ($fid > 0 && $this->model->deleteById($fid))
                {
                    JsonView::json(['success' => true]);
                }
                if ($pokemonId > 0 && $this->model->deleteByPokemonId($pokemonId))
                {
                    JsonView::json(['success' => true]);
                }
                JsonView::error('Favorito não encontrado.', 404);
                break;

            default:
                JsonView::error('Método não suportado.', 405);
        }
    }
}
