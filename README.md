# Pokédex (PHP + Vanilla JS + Bootstrap)

Aplicação web para explorar Pokémon usando a [PokeAPI](https://pokeapi.co/), com backend PHP em **MVC**, cache simples em disco, **paginação** na lista, modal de detalhes com cadeia de evolução e suporte opcional a **favoritos** e **histórico de buscas** via MySQL ou PostgreSQL.

## Arquitetura MVC (backend)

| Camada | Pasta | Papel |
|--------|--------|--------|
| **Model** | `models/` | Regras e dados: `PokemonModel` (PokeAPI + lista nacional ou por região), `RegionModel` (lista de regiões), `FavoriteModel`, `SearchHistoryModel` (PDO). |
| **View** | `views/` | `JsonView` — serialização e resposta HTTP JSON padronizada (`success` / `error`). |
| **Controller** | `controllers/` | Recebe a requisição HTTP, chama o Model e devolve via `JsonView`: `PokemonController`, `FavoriteController`, `HistoryController`. |
| **Services** | `services/` | Infraestrutura reutilizável: `PokeApiService`, `CacheService`, `DatabaseService` (usados pelos Models). |

Os arquivos em `api/*.php` são **pontos de entrada** finos: carregam `_bootstrap.php` e instanciam o controller adequado (`(new PokemonController())->index()` etc.).

Fluxo: `api/list.php` → `PokemonController::index()` → `PokemonModel::findListPage()` → `JsonView::success(...)`.  
Busca global: `api/search.php` → `PokemonController::search()` → `PokemonModel::searchGlobal()` (índice completo em cache via `PokeApiService::getFullPokemonIndex()`, ou espécies regionais quando `region` é passado).

## Frontend

- **`frontend/`** — `index.html`, `css/`, `js/`. Consome apenas `api/` com `fetch` (sem frameworks JS pesados).

## Requisitos

- PHP 8+ com **pdo_mysql** ou **pdo_pgsql** (se usar banco), **curl** recomendado.
- Apache (XAMPP) ou similar.
- MySQL/MariaDB ou PostgreSQL apenas para favoritos e histórico.

## Rodar no XAMPP (Windows)

1. Pasta em `htdocs/pokedex`, Apache ligado.
2. Abra `http://localhost/pokedex/frontend/` (a raiz redireciona para `frontend/`).
3. Opcional: importe `sql/mysql_schema.sql`, configure `config/config.php` (`DB_DSN`, usuário, senha).
4. Pasta `cache/` gravável.

PostgreSQL: `createdb pokedex`, rode `sql/postgres_schema.sql`, ajuste `DB_DSN` para `pgsql:...`.

## Endpoints da API local

| Método | URL | Descrição |
|--------|-----|-------------|
| GET | `api/pokemon.php?id=25` ou `?name=pikachu` | Detalhe + evoluções. Grava histórico se o banco estiver ativo. |
| GET | `api/list.php?page=1&limit=20&region=unova` | Lista paginada. Sem `region` = Pokédex Nacional. Com `region` = espécies das Pokédexes da região (API), mescladas. |
| GET | `api/search.php?q=pika&limit=80&region=kanto` | Busca global por substring no nome (mín. 2 letras) ou ID exato (só dígitos). Opcional `region` = mesmo conjunto da listagem regional. |
| GET | `api/regions.php` | Lista regiões (`name`, `label`) para o filtro. |
| GET | `api/favorites.php` | Lista favoritos (`db: false` se sem DSN). |
| POST | `api/favorites.php` JSON `{ "pokemon_id": 25, "nome": "pikachu" }` | Adiciona favorito. |
| DELETE | `api/favorites.php?id=1` ou `?pokemon_id=25` | Remove favorito. |
| GET | `api/history.php?limit=30` | Últimas buscas. |

## Funcionalidades

- Lista com **paginação** e **filtro por região** (Kanto, Unova, Kalos, … via `/region` + Pokédexes locais mescladas).
- Busca: filtra os cards da página atual; **Enter** abre detalhe por nome ou ID.
- Modal com tipos, altura/peso, habilidades e **evoluções** clicáveis.
- Spinner em overlay; **cache** em disco no backend.
- Favoritos e histórico (MySQL/PostgreSQL).

## Localização (português)

- **Tipos**: rótulos em PT-BR no backend (`PokeLocalizedStrings::TYPE_PT`).
- **Habilidades**: `names` da API (`pt-BR` → `pt` → `es` → …), mapa parcial de nomes BR comuns e fallback.
- **Nome, categoria (gênero), texto da Pokédex e rótulos na evolução**: via `pokemon-species`; a API muitas vezes **não** inclui `pt-BR` — usa-se fallback (`es`, `en`, etc.) e o modal pode indicar o idioma do texto da Pokédex.
- **Status base**: rótulos em PT-BR (`PS`, `Ataque`, …).

## Observações

- Rate limit da PokeAPI; cache local ajuda.
- *Fallback* de imagem no front se *official-artwork* falhar.
- Coluna de data: `data_registro` nos scripts SQL.

## Licença

Projeto educacional. Dados © Nintendo/Game Freak via PokeAPI.
