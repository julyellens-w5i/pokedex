-- Migração PostgreSQL: FK favorites.pokemon_id → pokemon.id
-- psql -U postgres -d pokedex -f sql/migrate_favorites_fk_postgres.sql

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

INSERT INTO pokemon (id, name, image)
SELECT
  f.pokemon_id,
  LOWER(TRIM(f.nome)),
  'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/'
    || f.pokemon_id::text || '.png'
FROM favorites f
WHERE NOT EXISTS (SELECT 1 FROM pokemon p WHERE p.id = f.pokemon_id)
ON CONFLICT (id) DO NOTHING;

-- Se a FK já existir, ajuste o nome conforme \d favorites
ALTER TABLE favorites DROP CONSTRAINT IF EXISTS favorites_pokemon_id_fkey;
ALTER TABLE favorites DROP CONSTRAINT IF EXISTS fk_favorites_pokemon;

ALTER TABLE favorites
  ADD CONSTRAINT fk_favorites_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (id)
    ON DELETE RESTRICT ON UPDATE CASCADE;
