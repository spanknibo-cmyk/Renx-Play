-- ========================================================================
-- Migração segura (incremental) para alinhar o banco ao código
-- Alvo: MySQL 8.0+ (compatível com MariaDB 10.4+, com possíveis ajustes)
-- Observação: Não remove nada; apenas cria/ajusta colunas/índices/FKs se faltarem
-- ========================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- Ajuste o banco se necessário
-- USE `u111823599_RenxplayGames`;

-- ========================================================================
-- USERS: colunas, índices e FK self-reference (created_by)
-- ========================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(191) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('USER','ADMIN','SUPER_ADMIN','DEV') NOT NULL DEFAULT 'USER',
  `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- password: se existir password_hash e não existir password, renomear
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns 
  WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password'
);
SET @col_old_exists := (
  SELECT COUNT(*) FROM information_schema.columns 
  WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password_hash'
);
SET @sql := IF(@col_exists = 0 AND @col_old_exists = 1,
  'ALTER TABLE `users` CHANGE COLUMN `password_hash` `password` VARCHAR(255) NOT NULL;',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Garantir colunas básicas
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(191) NULL AFTER `username`,
  ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) NOT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `role` ENUM('USER','ADMIN','SUPER_ADMIN','DEV') NOT NULL DEFAULT 'USER' AFTER `password`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active' AFTER `role`,
  ADD COLUMN IF NOT EXISTS `created_by` BIGINT UNSIGNED NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_by`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL AFTER `updated_at`;

-- Índices e unicidades
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'ux_users_username');
SET @sql := IF(@exists = 0, 'CREATE UNIQUE INDEX `ux_users_username` ON `users`(`username`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'ux_users_email');
SET @sql := IF(@exists = 0, 'CREATE UNIQUE INDEX `ux_users_email` ON `users`(`email`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'ix_users_role');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_users_role` ON `users`(`role`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'ix_users_status');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_users_status` ON `users`(`status`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'ix_users_created_at');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_users_created_at` ON `users`(`created_at`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'ix_users_created_by');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_users_created_by` ON `users`(`created_by`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK users.created_by -> users.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema = DATABASE() AND table_name = 'users' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_users_created_by'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- GAMES: colunas, índices e FK posted_by
-- ========================================================================
CREATE TABLE IF NOT EXISTS `games` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `cover_image` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) NULL AFTER `cover_image`,
  ADD COLUMN IF NOT EXISTS `language` VARCHAR(50) NULL AFTER `category`,
  ADD COLUMN IF NOT EXISTS `languages_multi` TEXT NULL AFTER `language`,
  ADD COLUMN IF NOT EXISTS `version` VARCHAR(50) NULL AFTER `languages_multi`,
  ADD COLUMN IF NOT EXISTS `engine` ENUM('REN''PY','UNITY','RPG_MAKER','OTHER') NOT NULL DEFAULT 'REN''PY' AFTER `version`,
  ADD COLUMN IF NOT EXISTS `tags` VARCHAR(255) NULL AFTER `engine`,
  ADD COLUMN IF NOT EXISTS `download_url` VARCHAR(512) NULL AFTER `tags`,
  ADD COLUMN IF NOT EXISTS `download_url_windows` VARCHAR(512) NULL AFTER `download_url`,
  ADD COLUMN IF NOT EXISTS `download_url_android` VARCHAR(512) NULL AFTER `download_url_windows`,
  ADD COLUMN IF NOT EXISTS `download_url_linux` VARCHAR(512) NULL AFTER `download_url_android`,
  ADD COLUMN IF NOT EXISTS `download_url_mac` VARCHAR(512) NULL AFTER `download_url_linux`,
  ADD COLUMN IF NOT EXISTS `censored` TINYINT(1) NOT NULL DEFAULT 0 AFTER `download_url_mac`,
  ADD COLUMN IF NOT EXISTS `os_windows` TINYINT(1) NOT NULL DEFAULT 0 AFTER `censored`,
  ADD COLUMN IF NOT EXISTS `os_android` TINYINT(1) NOT NULL DEFAULT 0 AFTER `os_windows`,
  ADD COLUMN IF NOT EXISTS `os_linux` TINYINT(1) NOT NULL DEFAULT 0 AFTER `os_android`,
  ADD COLUMN IF NOT EXISTS `os_mac` TINYINT(1) NOT NULL DEFAULT 0 AFTER `os_linux`,
  ADD COLUMN IF NOT EXISTS `posted_by` BIGINT UNSIGNED NULL AFTER `os_mac`,
  ADD COLUMN IF NOT EXISTS `developer_name` VARCHAR(255) NULL AFTER `posted_by`,
  ADD COLUMN IF NOT EXISTS `updated_at_custom` DATE NULL AFTER `developer_name`,
  ADD COLUMN IF NOT EXISTS `released_at_custom` DATE NULL AFTER `updated_at_custom`,
  ADD COLUMN IF NOT EXISTS `patreon_url` VARCHAR(512) NULL AFTER `released_at_custom`,
  ADD COLUMN IF NOT EXISTS `discord_url` VARCHAR(512) NULL AFTER `patreon_url`,
  ADD COLUMN IF NOT EXISTS `subscribestar_url` VARCHAR(512) NULL AFTER `discord_url`,
  ADD COLUMN IF NOT EXISTS `itch_url` VARCHAR(512) NULL AFTER `subscribestar_url`,
  ADD COLUMN IF NOT EXISTS `kofi_url` VARCHAR(512) NULL AFTER `itch_url`,
  ADD COLUMN IF NOT EXISTS `bmc_url` VARCHAR(512) NULL AFTER `kofi_url`,
  ADD COLUMN IF NOT EXISTS `steam_url` VARCHAR(512) NULL AFTER `bmc_url`,
  ADD COLUMN IF NOT EXISTS `screenshots` TEXT NULL AFTER `steam_url`,
  ADD COLUMN IF NOT EXISTS `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `screenshots`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published' AFTER `downloads_count`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `status`;

-- Unicidade e índices
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'ux_games_slug');
SET @sql := IF(@exists = 0, 'CREATE UNIQUE INDEX `ux_games_slug` ON `games`(`slug`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'ix_games_posted_by');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_games_posted_by` ON `games`(`posted_by`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'ix_games_created_at');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_games_created_at` ON `games`(`created_at`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'ix_games_posted_by_created_at');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_games_posted_by_created_at` ON `games`(`posted_by`,`created_at`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FULLTEXT (opcional; remova se não suportado)
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'games' AND index_name = 'ft_games_title_description_tags');
SET @sql := IF(@exists = 0, 'CREATE FULLTEXT INDEX `ft_games_title_description_tags` ON `games`(`title`,`description`,`tags`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK games.posted_by -> users.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema = DATABASE() AND table_name = 'games' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_games_posted_by'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `games` ADD CONSTRAINT `fk_games_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT;',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- COMMENTS: criação condicional e FKs
-- ========================================================================
CREATE TABLE IF NOT EXISTS `comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `game_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'comments' AND index_name = 'ix_comments_game_id');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_comments_game_id` ON `comments`(`game_id`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'comments' AND index_name = 'ix_comments_user_id');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_comments_user_id` ON `comments`(`user_id`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'comments' AND index_name = 'ix_comments_parent_id');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_comments_parent_id` ON `comments`(`parent_id`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'comments' AND index_name = 'ix_comments_created_at');
SET @sql := IF(@exists = 0, 'CREATE INDEX `ix_comments_created_at` ON `comments`(`created_at`);', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FKs
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema = DATABASE() AND table_name = 'comments' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_comments_game'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_game` FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema = DATABASE() AND table_name = 'comments' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_comments_user'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema = DATABASE() AND table_name = 'comments' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_comments_parent'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- Ajustes finais
-- ========================================================================
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Notas:
-- - O tipo ENUM de games.engine é definido aqui; caso haja valores fora do conjunto,
--   ajuste os dados antes de alterar o tipo (ou remova a MODIFICAÇÃO de tipo).
-- - Caso queira tornar games.posted_by NOT NULL, primeiro corrija/atribua donos
--   e então: ALTER TABLE games MODIFY posted_by BIGINT UNSIGNED NOT NULL;
-- - FULLTEXT pode não existir em algumas versões; remova o bloco se necessário.

