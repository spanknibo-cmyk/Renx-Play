-- =====================================================================
-- Migração para alinhar bases existentes ao schema esperado (MySQL 8+)
-- Execute em produção com cautela e backup prévio. Idempotente onde possível.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- Certificar base (ajuste o nome conforme ambiente)
CREATE DATABASE IF NOT EXISTS `u111823599_RenxplayGames`
  /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `u111823599_RenxplayGames`;

-- USERS
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
  KEY `idx_users_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK users.created_by -> users.id (criar depois da tabela existir)
SET @have_fk_users_created_by := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_users_created_by_users'
);
SET @sql := IF(@have_fk_users_created_by = 0,
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- GAMES
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
  `download_url` VARCHAR(2048) NULL,
  `download_url_windows` VARCHAR(2048) NULL,
  `download_url_android` VARCHAR(2048) NULL,
  `download_url_linux` VARCHAR(2048) NULL,
  `download_url_mac` VARCHAR(2048) NULL,
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
  `patreon_url` VARCHAR(2048) NULL,
  `discord_url` VARCHAR(2048) NULL,
  `subscribestar_url` VARCHAR(2048) NULL,
  `itch_url` VARCHAR(2048) NULL,
  `kofi_url` VARCHAR(2048) NULL,
  `bmc_url` VARCHAR(2048) NULL,
  `steam_url` VARCHAR(2048) NULL,
  `screenshots` JSON NULL,
  `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_games_slug` (`slug`),
  KEY `idx_games_created_at` (`created_at`),
  KEY `idx_games_title` (`title`),
  KEY `idx_games_engine` (`engine`),
  KEY `idx_games_posted_by` (`posted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrações incrementais de colunas para bases existentes (idempotentes)
-- Detecta colunas existentes via INFORMATION_SCHEMA antes de adicionar
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS add_column_if_missing(
  IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @ddl = p_ddl; PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END $$
DELIMITER ;

CALL add_column_if_missing('games','category',
  'ALTER TABLE `games` ADD COLUMN `category` VARCHAR(100) NULL AFTER `cover_image`');
CALL add_column_if_missing('games','language',
  'ALTER TABLE `games` ADD COLUMN `language` VARCHAR(50) NULL AFTER `category`');
CALL add_column_if_missing('games','version',
  'ALTER TABLE `games` ADD COLUMN `version` VARCHAR(50) NULL AFTER `language`');
CALL add_column_if_missing('games','engine',
  'ALTER TABLE `games` ADD COLUMN `engine` VARCHAR(100) NULL AFTER `version`');
CALL add_column_if_missing('games','tags',
  'ALTER TABLE `games` ADD COLUMN `tags` TEXT NULL AFTER `engine`');
CALL add_column_if_missing('games','download_url',
  'ALTER TABLE `games` ADD COLUMN `download_url` VARCHAR(2048) NULL AFTER `tags`');
CALL add_column_if_missing('games','download_url_windows',
  'ALTER TABLE `games` ADD COLUMN `download_url_windows` VARCHAR(2048) NULL AFTER `download_url`');
CALL add_column_if_missing('games','download_url_android',
  'ALTER TABLE `games` ADD COLUMN `download_url_android` VARCHAR(2048) NULL AFTER `download_url_windows`');
CALL add_column_if_missing('games','download_url_linux',
  'ALTER TABLE `games` ADD COLUMN `download_url_linux` VARCHAR(2048) NULL AFTER `download_url_android`');
CALL add_column_if_missing('games','download_url_mac',
  'ALTER TABLE `games` ADD COLUMN `download_url_mac` VARCHAR(2048) NULL AFTER `download_url_linux`');
CALL add_column_if_missing('games','censored',
  'ALTER TABLE `games` ADD COLUMN `censored` TINYINT(1) NOT NULL DEFAULT 0 AFTER `download_url_mac`');
CALL add_column_if_missing('games','os_windows',
  'ALTER TABLE `games` ADD COLUMN `os_windows` TINYINT(1) NOT NULL DEFAULT 0 AFTER `censored`');
CALL add_column_if_missing('games','os_android',
  'ALTER TABLE `games` ADD COLUMN `os_android` TINYINT(1) NOT NULL DEFAULT 0 AFTER `os_windows`');
CALL add_column_if_missing('games','os_linux',
  'ALTER TABLE `games` ADD COLUMN `os_linux` TINYINT(1) NOT NULL DEFAULT 0 AFTER `os_android`');
CALL add_column_if_missing('games','os_mac',
  'ALTER TABLE `games` ADD COLUMN `os_mac` TINYINT(1) NOT NULL DEFAULT 0 AFTER `os_linux`');
CALL add_column_if_missing('games','posted_by',
  'ALTER TABLE `games` ADD COLUMN `posted_by` BIGINT UNSIGNED NULL AFTER `os_mac`');
CALL add_column_if_missing('games','developer_name',
  'ALTER TABLE `games` ADD COLUMN `developer_name` VARCHAR(255) NULL AFTER `posted_by`');
CALL add_column_if_missing('games','languages_multi',
  'ALTER TABLE `games` ADD COLUMN `languages_multi` JSON NULL AFTER `developer_name`');
CALL add_column_if_missing('games','updated_at_custom',
  'ALTER TABLE `games` ADD COLUMN `updated_at_custom` DATE NULL AFTER `languages_multi`');
CALL add_column_if_missing('games','released_at_custom',
  'ALTER TABLE `games` ADD COLUMN `released_at_custom` DATE NULL AFTER `updated_at_custom`');
CALL add_column_if_missing('games','patreon_url',
  'ALTER TABLE `games` ADD COLUMN `patreon_url` VARCHAR(2048) NULL AFTER `released_at_custom`');
CALL add_column_if_missing('games','discord_url',
  'ALTER TABLE `games` ADD COLUMN `discord_url` VARCHAR(2048) NULL AFTER `patreon_url`');
CALL add_column_if_missing('games','subscribestar_url',
  'ALTER TABLE `games` ADD COLUMN `subscribestar_url` VARCHAR(2048) NULL AFTER `discord_url`');
CALL add_column_if_missing('games','itch_url',
  'ALTER TABLE `games` ADD COLUMN `itch_url` VARCHAR(2048) NULL AFTER `subscribestar_url`');
CALL add_column_if_missing('games','kofi_url',
  'ALTER TABLE `games` ADD COLUMN `kofi_url` VARCHAR(2048) NULL AFTER `itch_url`');
CALL add_column_if_missing('games','bmc_url',
  'ALTER TABLE `games` ADD COLUMN `bmc_url` VARCHAR(2048) NULL AFTER `kofi_url`');
CALL add_column_if_missing('games','steam_url',
  'ALTER TABLE `games` ADD COLUMN `steam_url` VARCHAR(2048) NULL AFTER `bmc_url`');
CALL add_column_if_missing('games','screenshots',
  'ALTER TABLE `games` ADD COLUMN `screenshots` JSON NULL AFTER `steam_url`');
CALL add_column_if_missing('games','downloads_count',
  'ALTER TABLE `games` ADD COLUMN `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `screenshots`');
CALL add_column_if_missing('games','created_at',
  'ALTER TABLE `games` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `downloads_count`');
CALL add_column_if_missing('games','updated_at',
  'ALTER TABLE `games` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

-- USERS <-> GAMES FK
SET @have_fk_games_posted_by := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_games_posted_by_users'
);
SET @sql := IF(@have_fk_games_posted_by = 0,
  'ALTER TABLE `games` ADD CONSTRAINT `fk_games_posted_by_users` FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices adicionais (condicionais, compatível com MySQL 8)
-- games.idx_games_created_at
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'idx_games_created_at'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_games_created_at` ON `games`(`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Conversão de colunas para JSON quando possível (seguro)
-- languages_multi
SET @invalid := (
  SELECT COUNT(*) FROM `games`
  WHERE `languages_multi` IS NOT NULL AND JSON_VALID(`languages_multi`) = 0
);
SET @is_json := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'games' AND COLUMN_NAME = 'languages_multi' AND DATA_TYPE = 'json'
);
SET @sql := IF(@invalid = 0 AND @is_json = 0,
  'ALTER TABLE `games` MODIFY `languages_multi` JSON NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- screenshots
UPDATE `games` SET `screenshots` = NULL WHERE `screenshots` = '';
SET @invalid := (
  SELECT COUNT(*) FROM `games`
  WHERE `screenshots` IS NOT NULL AND JSON_VALID(`screenshots`) = 0
);
SET @is_json := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'games' AND COLUMN_NAME = 'screenshots' AND DATA_TYPE = 'json'
);
SET @sql := IF(@invalid = 0 AND @is_json = 0,
  'ALTER TABLE `games` MODIFY `screenshots` JSON NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- games.idx_games_title
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'idx_games_title'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_games_title` ON `games`(`title`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- games.idx_games_engine
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'idx_games_engine'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_games_engine` ON `games`(`engine`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- games.idx_games_posted_by
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'idx_games_posted_by'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_games_posted_by` ON `games`(`posted_by`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- games.u_games_slug (unique)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'u_games_slug'
);
SET @sql := IF(@exists = 0, 'CREATE UNIQUE INDEX `u_games_slug` ON `games`(`slug`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.u_users_username (unique)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'u_users_username'
);
SET @sql := IF(@exists = 0, 'CREATE UNIQUE INDEX `u_users_username` ON `users`(`username`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.u_users_email (unique)
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'u_users_email'
);
SET @sql := IF(@exists = 0, 'CREATE UNIQUE INDEX `u_users_email` ON `users`(`email`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.idx_users_role
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_role'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_users_role` ON `users`(`role`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.idx_users_created_by
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_created_by'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_users_created_by` ON `users`(`created_by`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- COMMENTS
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
  KEY `idx_comments_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FKs de comments
SET @have_fk_comments_game := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_comments_game_id_games'
);
SET @sql := IF(@have_fk_comments_game = 0,
  'ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_game_id_games` FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @have_fk_comments_user := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_comments_user_id_users'
);
SET @sql := IF(@have_fk_comments_user = 0,
  'ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @have_fk_comments_parent := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_comments_parent_id_comments'
);
SET @sql := IF(@have_fk_comments_parent = 0,
  'ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_parent_id_comments` FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed mínimo opcional (mantido comentado)
-- INSERT IGNORE INTO users (id, username, email, password, role) VALUES (1,'admin','admin@example.com','$2y$10$hash','DEV');

