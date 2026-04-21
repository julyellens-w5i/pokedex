-- PostgreSQL 14+ — Pokédex (favoritos e histórico)
-- 1) createdb -U postgres pokedex
-- 2) psql -U postgres -d pokedex -f postgres_schema.sql

CREATE TABLE IF NOT EXISTS pokemon (
  id INTEGER PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  image VARCHAR(768) NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pokemon_name ON pokemon (name);

CREATE TABLE IF NOT EXISTS pokemon_cache_meta (
  meta_key VARCHAR(64) PRIMARY KEY,
  meta_value TEXT NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS pokemon_detail (
  id INTEGER PRIMARY KEY,
  payload TEXT NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS search_history (
  id BIGSERIAL PRIMARY KEY,
  termo VARCHAR(120) NOT NULL,
  data_registro TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_search_history_data ON search_history (data_registro DESC);

CREATE TABLE IF NOT EXISTS favorites (
  id BIGSERIAL PRIMARY KEY,
  pokemon_id INTEGER NOT NULL UNIQUE REFERENCES pokemon (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  nome VARCHAR(120) NOT NULL,
  data_registro TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_favorites_data ON favorites (data_registro DESC);

CREATE TABLE IF NOT EXISTS collections (
  id BIGSERIAL PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  data_registro TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_collections_data ON collections (data_registro DESC);

CREATE TABLE IF NOT EXISTS collection_items (
  id BIGSERIAL PRIMARY KEY,
  collection_id BIGINT NOT NULL REFERENCES collections (id) ON DELETE CASCADE ON UPDATE CASCADE,
  pokemon_id INTEGER NOT NULL,
  nome VARCHAR(120) NOT NULL,
  data_registro TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (collection_id, pokemon_id)
);

CREATE INDEX IF NOT EXISTS idx_collection_items_cid ON collection_items (collection_id);
