-- PostgreSQL 14+ — Pokédex (favoritos e histórico)
-- 1) createdb -U postgres pokedex
-- 2) psql -U postgres -d pokedex -f postgres_schema.sql

CREATE TABLE IF NOT EXISTS favorites (
  id BIGSERIAL PRIMARY KEY,
  pokemon_id INTEGER NOT NULL UNIQUE,
  nome VARCHAR(120) NOT NULL,
  data_registro TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_favorites_data ON favorites (data_registro DESC);

CREATE TABLE IF NOT EXISTS search_history (
  id BIGSERIAL PRIMARY KEY,
  termo VARCHAR(120) NOT NULL,
  data_registro TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_search_history_data ON search_history (data_registro DESC);
