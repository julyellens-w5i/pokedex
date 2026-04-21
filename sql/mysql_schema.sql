-- MySQL 8+ / MariaDB — Pokédex (favoritos e histórico)
-- Crie o banco e importe: mysql -u root -p < mysql_schema.sql

CREATE DATABASE IF NOT EXISTS pokedex
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS search_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  termo VARCHAR(120) NOT NULL,
  data_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_search_history_data (data_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pokemon_id INT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  data_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_favorites_pokemon_id (pokemon_id),
  KEY idx_favorites_data (data_registro),
  CONSTRAINT fk_favorites_pokemon FOREIGN KEY (pokemon_id) REFERENCES pokemon (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
