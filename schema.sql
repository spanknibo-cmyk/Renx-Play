-- =====================================================================
-- Schema base do banco de dados para o site Renxplay (MySQL 8+)
-- Objetivo: criação completa e documentada de tabelas, chaves e índices
-- Charset: utf8mb4, Collation: utf8mb4_unicode_ci, Engine: InnoDB
-- Este arquivo é idempotente apenas para CREATE DATABASE/TABLE (IF NOT EXISTS).
-- Para migrações em bases existentes, use migrate.sql.
-- =====================================================================

-- Ajuste o nome do banco conforme necessário em ambientes de teste/prod
CREATE DATABASE IF NOT EXISTS `u111823599_RenxplayGames`
  /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `u111823599_RenxplayGames`;

-- =====================================================================
-- Tabela: users
-- Armazena contas de usuários e controles de acesso
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('DEV','SUPER_ADMIN','ADMIN','USER') NOT NULL DEFAULT 'USER',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_users_username` (`username`),
  UNIQUE KEY `u_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_created_by` (`created_by`),
  CONSTRAINT `fk_users_created_by_users`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Tabela: games
-- Publicações de jogos e metadados associados
-- =====================================================================
CREATE TABLE IF NOT EXISTS `games` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `cover_image` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NULL,
  `language` VARCHAR(50) NULL,
  `version` VARCHAR(50) NULL,
  `engine` VARCHAR(100) NULL,
  `tags` TEXT NULL,
  `download_url` TEXT NULL,
  `download_url_windows` TEXT NULL,
  `download_url_android` TEXT NULL,
  `download_url_linux` TEXT NULL,
  `download_url_mac` TEXT NULL,
  `censored` TINYINT(1) NOT NULL DEFAULT 0,
  `os_windows` TINYINT(1) NOT NULL DEFAULT 0,
  `os_android` TINYINT(1) NOT NULL DEFAULT 0,
  `os_linux` TINYINT(1) NOT NULL DEFAULT 0,
  `os_mac` TINYINT(1) NOT NULL DEFAULT 0,
  `posted_by` BIGINT UNSIGNED NOT NULL,
  `developer_name` VARCHAR(255) NULL,
  `languages_multi` JSON NULL,
  `updated_at_custom` DATE NULL,
  `released_at_custom` DATE NULL,
  `patreon_url` TEXT NULL,
  `discord_url` TEXT NULL,
  `subscribestar_url` TEXT NULL,
  `itch_url` TEXT NULL,
  `kofi_url` TEXT NULL,
  `bmc_url` TEXT NULL,
  `steam_url` TEXT NULL,
  `screenshots` JSON NULL,
  `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_games_slug` (`slug`),
  KEY `idx_games_created_at` (`created_at`),
  KEY `idx_games_title` (`title`),
  KEY `idx_games_engine` (`engine`),
  KEY `idx_games_posted_by` (`posted_by`),
  CONSTRAINT `fk_games_posted_by_users`
    FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Tabela: comments
-- Comentários associados a jogos (com suporte a threads via parent_id)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `game_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_game_id` (`game_id`),
  KEY `idx_comments_user_id` (`user_id`),
  KEY `idx_comments_parent_id` (`parent_id`),
  KEY `idx_comments_created_at` (`created_at`),
  CONSTRAINT `fk_comments_game_id_games`
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_user_id_users`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_parent_id_comments`
    FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Observações
-- - Colunas JSON (languages_multi, screenshots) permitem arrays com idiomas
--   e lista de arquivos de screenshots, como usado na aplicação.
-- - Campo `category` é opcional e usado apenas em retornos da busca.
-- - Índices adicionados para melhorar ordenações, junções e buscas.
-- =====================================================================

