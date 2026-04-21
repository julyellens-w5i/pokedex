-- Migração MySQL: FK favorites.pokemon_id → pokemon.id
-- Use se o banco já existia sem `pokemon` ou sem FK (ajuste o nome do banco se precisar).
-- mysql -u root -p pokedex < sql/migrate_favorites_fk_mysql.sql

USE pokedex;

CREATE TABLE IF NOT EXISTS pokemon (
  id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  image VARCHAR(768) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pokemon_name (name(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pokemon_cache_meta (
  meta_key VARCHAR(64) NOT NULL,
  meta_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (meta_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pokemon (id, name, image)
SELECT
  f.pokemon_id,
  LOWER(TRIM(f.nome)),
  CONCAT(
    'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/',
    f.pokemon_id,
    '.png'
  )
FROM favorites f
LEFT JOIN pokemon p ON p.id = f.pokemon_id
WHERE p.id IS NULL;

-- Se a FK já existir ou tiver outro nome, remova antes: SHOW CREATE TABLE favorites;
-- ALTER TABLE favorites DROP FOREIGN KEY fk_favorites_pokemon;

ALTER TABLE favorites
  ADD CONSTRAINT fk_favorites_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (id)
    ON DELETE RESTRICT ON UPDATE CASCADE;
