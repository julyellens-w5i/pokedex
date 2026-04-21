# Pokédex (PHP + JavaScript + Bootstrap)

Aplicação web para explorar Pokémon usando a [PokeAPI](https://pokeapi.co/), com backend PHP em **MVC**, **cache em disco** (respostas da API), cache opcional de **detalhe de Pokémon em JSON no banco** (`pokemon_detail`), **paginação** na lista, modal de detalhes com cadeia de evolução e suporte opcional a **favoritos** e **histórico de buscas** via MySQL ou PostgreSQL.

## Arquitetura MVC (backend)

| Camada | Pasta | Papel |
|--------|--------|--------|
| **Model** | `models/` | `PokemonModel` (lista nacional, por **região** ou por **tipo** na nacional, detalhe + evoluções), `PokemonStorageModel` (persistência em BD: intervalos da lista nacional e payload JSON de detalhe), `RegionModel`, `FavoriteModel`, `SearchHistoryModel` (PDO). |
| **View** | `views/` | `JsonView` — resposta JSON padronizada (`success` / `error`). |
| **Controller** | `controllers/` | `PokemonController` (lista, detalhe, busca), `RegionController`, `FavoriteController`, `HistoryController`. |
| **Services** | `services/` | `PokeApiService`, `CacheService`, `DatabaseService`, `PokeLocalizedStrings`. |

Os arquivos em `api/*.php` são **pontos de entrada** finos: carregam `_bootstrap.php` e instanciam o controller adequado.

Fluxo: `api/list.php` → `PokemonController::index()` → `PokemonModel::findListPage()` → `JsonView::success(...)`. Parâmetros: `page`, `limit`, `region` (opcional), **`type`** (opcional, **slug** do tipo: só na Pokédex **nacional**; com `region` definido o tipo é ignorado no backend).

Busca global: `api/search.php` → `PokemonController::search()` → `PokemonModel::searchGlobal()` (índice completo em cache via `PokeApiService::getFullPokemonIndex()`, ou espécies regionais quando `region` é passado).

Detalhe: `api/pokemon.php` → `PokemonModel::findDetail()` — tenta payload em `pokemon_detail` antes da API e grava após montar a resposta (se o banco estiver configurado).

## Frontend

- **`frontend/`** — `index.html`, `css/styles.css`, `js/app.js`. Consome só `api/` com `fetch` (sem framework SPA; **Bootstrap 5** para layout e componentes).
- Na raiz do projeto, `index.html` redireciona para `frontend/`.
- **PWA mínima**: `manifest.webmanifest` e `sw.js` (registo opcional do *service worker*).

## Requisitos

- PHP 8+ com **pdo_mysql** ou **pdo_pgsql** (se usar banco), **curl** recomendado.
- Apache (XAMPP) ou outro servidor estático + PHP para `api/`.
- MySQL/MariaDB ou PostgreSQL para favoritos, histórico de buscas e **tabelas de cache** (`pokemon`, `pokemon_cache_meta`, `pokemon_detail` — ver `sql/`).

## Rodar no XAMPP (Windows)

1. Coloque o projeto em `htdocs/pokedex` e ligue o Apache.
2. Abra `http://localhost/pokedex/frontend/` (ou `http://localhost/pokedex/` — redireciona para `frontend/`).
3. **Configuração**: copie `config/config.sample.php` para `config/config.php` e ajuste **ou** crie `config/.env` a partir de `config/.env.example` com `POKEDEX_DB_DSN`, `POKEDEX_DB_USER`, `POKEDEX_DB_PASS` (o `config.php` carrega `config/.env` se existir e a variável ainda não estiver no ambiente).
4. Opcional: importe `sql/mysql_schema.sql` ou `sql/postgres_schema.sql` (inclui `pokemon_detail` para cache de detalhe).
5. Pasta `cache/` gravável.

**PostgreSQL:** `createdb pokedex`, rode `sql/postgres_schema.sql`, ajuste o DSN para `pgsql:...`.

**Bancos já existentes:** se faltar só a tabela de detalhe, execute o `CREATE TABLE pokemon_detail` do script correspondente.

## Endpoints da API local

| Método | URL | Descrição |
|--------|-----|-------------|
| GET | `api/pokemon.php?id=25` ou `?name=pikachu` | Detalhe + evoluções. Grava histórico de busca se o banco estiver ativo. |
| GET | `api/list.php?page=1&limit=20&region=unova` | Lista paginada. Sem `region` = Pokédex Nacional. Com `region` = espécies das Pokédexes da região (API), mescladas. |
| GET | `api/list.php?...&type=fire` | Filtro por **tipo** (slug em inglês: `fire`, `water`, …). **Só** sem `region` (Pokédex nacional); a resposta pode incluir `type_label` em PT-BR. |
| GET | `api/search.php?q=pika&limit=80&region=kanto` | Busca global por substring no nome (mín. 2 letras) ou ID exato (só dígitos). Opcional `region` = mesmo conjunto da listagem regional. |
| GET | `api/regions.php` | Lista regiões (`name`, `label`) para o filtro. |
| GET | `api/favorites.php` | Lista favoritos (`db: false` se sem DSN). |
| POST | `api/favorites.php` JSON `{ "pokemon_id": 25, "nome": "pikachu" }` | Adiciona favorito. |
| DELETE | `api/favorites.php?id=1` ou `?pokemon_id=25` | Remove favorito. |
| GET | `api/history.php?limit=30` | Últimas buscas. |

## Funcionalidades

### Lista e busca

- **Paginação**, **filtro por região** (Kanto, Unova, Kalos, …) e **filtro por tipo** na Pokédex nacional.
- **Busca global**: substitui a grade por resultados da API (não é só “filtrar a página atual”); **Enter** abre o detalhe por nome ou ID; atalho **`/`** foca o campo de busca; **`Esc`** fecha modais.

### Detalhe e UX

- Modal com tipos, altura/peso, **curiosidades** (habitat, captura, lendário/mítico, etc., quando a API enviar), habilidades e **evoluções** clicáveis.
- **Tema claro/escuro**, link partilhável (`?pokemon=`), Pokémon aleatório, **comparador** de dois Pokémon (stats com realce de maior/menor), exportar/importar favoritos em JSON, som opcional ao favoritar, **vistos recentemente**, **mini conquistas**, *skeleton* ao carregar a lista.
- **Buscas recentes** na sidebar atrás de um acordeão (“Mostrar buscas recentes”).
- Spinner em overlay; cache em disco no backend e, com BD, cache de lista nacional e de detalhe.

### Dados persistidos (MySQL/PostgreSQL)

- Favoritos, histórico de buscas e tabelas auxiliares de cache (`PokemonStorageModel`).

## Localização (português)

- **Tipos**: rótulos em PT-BR no backend (`PokeLocalizedStrings::TYPE_PT`).
- **Habilidades**: `names` da API (`pt-BR` → `pt` → `es` → …), mapa parcial de nomes BR comuns e *fallback*.
- **Nome, categoria (gênero), texto da Pokédex e rótulos na evolução**: via `pokemon-species`; a API muitas vezes **não** inclui `pt-BR` — usa-se *fallback* (`es`, `en`, etc.) e o modal pode indicar o idioma do texto da Pokédex.
- **Status base**: rótulos em PT-BR (`PS`, `Ataque`, …).

## Observações

- Respeite o *rate limit* da PokeAPI; cache local e persistência de detalhe aliviam chamadas repetidas.
- *Fallback* de imagem no *front* se *official-artwork* falhar.
- Nos scripts SQL, favoritos/histórico usam colunas como `data_registro` onde aplicável.

## Licença

Projeto educacional. Dados © Nintendo/Game Freak via PokeAPI.
