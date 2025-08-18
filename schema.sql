-- ========================================================================
-- Schema do banco de dados para o site Renxplay
-- Objetivo: permitir importação completa (criação do DB + tabelas + índices)
-- Banco alvo: MySQL 8.0+ (compatível com MariaDB 10.4+)
-- Charset/Collation: utf8mb4/utf8mb4_unicode_ci
-- ========================================================================

-- Observação:
-- 1) O arquivo cria o banco "u111823599_RenxplayGames" se não existir e aplica USE.
-- 2) Inclui FKs, índices e colunas utilizadas no código PHP atual.
-- 3) Reexecutável com segurança: usa DROP TABLE IF EXISTS em ordem correta.
-- 4) Para ambientes diferentes, altere o CREATE DATABASE/USE conforme necessário.

-- ========================================================================
-- Configurações de sessão
-- ========================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ========================================================================
-- Criação do banco de dados
-- ========================================================================
CREATE DATABASE IF NOT EXISTS `u111823599_RenxplayGames`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `u111823599_RenxplayGames`;

-- ========================================================================
-- Limpeza segura (ordem respeitando FKs)
-- ========================================================================
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `games`;
DROP TABLE IF EXISTS `users`;

-- ========================================================================
-- Tabela: users
-- Representa contas do sistema (login, papéis e status)
-- ========================================================================
CREATE TABLE `users` (
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_users_username` (`username`),
  UNIQUE KEY `ux_users_email` (`email`),
  KEY `ix_users_role` (`role`),
  KEY `ix_users_status` (`status`),
  KEY `ix_users_created_at` (`created_at`),
  KEY `ix_users_created_by` (`created_by`),
  CONSTRAINT `fk_users_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================================
-- Tabela: games
-- Conteúdo principal exibido no site. Inclui campos usados em config.php,
-- index.php, dashboard.php e game.php, bem como colunas adicionadas por
-- ensureGameColumns().
-- ========================================================================
CREATE TABLE `games` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `cover_image` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NULL,
  `language` VARCHAR(50) NULL,                -- legado (língua única)
  `languages_multi` TEXT NULL,               -- JSON em texto (compatível com código atual)
  `version` VARCHAR(50) NULL,
  `engine` ENUM('REN''PY','UNITY','RPG_MAKER','OTHER') NOT NULL DEFAULT 'REN''PY',
  `tags` VARCHAR(255) NULL,
  `download_url` VARCHAR(512) NULL,
  `download_url_windows` VARCHAR(512) NULL,
  `download_url_android` VARCHAR(512) NULL,
  `download_url_linux` VARCHAR(512) NULL,
  `download_url_mac` VARCHAR(512) NULL,
  `censored` TINYINT(1) NOT NULL DEFAULT 0,
  `os_windows` TINYINT(1) NOT NULL DEFAULT 0,
  `os_android` TINYINT(1) NOT NULL DEFAULT 0,
  `os_linux` TINYINT(1) NOT NULL DEFAULT 0,
  `os_mac` TINYINT(1) NOT NULL DEFAULT 0,
  `posted_by` BIGINT UNSIGNED NOT NULL,
  `developer_name` VARCHAR(255) NULL,
  `updated_at_custom` DATE NULL,
  `released_at_custom` DATE NULL,
  `patreon_url` VARCHAR(512) NULL,
  `discord_url` VARCHAR(512) NULL,
  `subscribestar_url` VARCHAR(512) NULL,
  `itch_url` VARCHAR(512) NULL,
  `kofi_url` VARCHAR(512) NULL,
  `bmc_url` VARCHAR(512) NULL,
  `steam_url` VARCHAR(512) NULL,
  `screenshots` TEXT NULL,                   -- JSON em texto com nomes de arquivos
  `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_games_slug` (`slug`),
  KEY `ix_games_posted_by` (`posted_by`),
  KEY `ix_games_created_at` (`created_at`),
  KEY `ix_games_status` (`status`),
  KEY `ix_games_posted_by_created_at` (`posted_by`, `created_at`),
  CONSTRAINT `fk_games_posted_by`
    FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice FULLTEXT para busca por título/descrição/tags (InnoDB 5.6+)
-- Observação: para MariaDB/MySQL antigos, remova esta seção se necessário.
CREATE FULLTEXT INDEX `ft_games_title_description_tags`
  ON `games` (`title`, `description`, `tags`);

-- ========================================================================
-- Tabela: comments
-- Comentários por jogo com suporte a threads (parent_id)
-- ========================================================================
CREATE TABLE `comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `game_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_comments_game_id` (`game_id`),
  KEY `ix_comments_user_id` (`user_id`),
  KEY `ix_comments_parent_id` (`parent_id`),
  KEY `ix_comments_created_at` (`created_at`),
  CONSTRAINT `fk_comments_game`
    FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================================
-- Dicas de operação
-- ========================================================================
-- 1) Usuário inicial (opcional): descomente e ajuste antes de executar em prod.
-- INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `status`)
-- VALUES ('admin', 'admin@example.com', '$2y$10$COLOQUE_AQUI_UM_BCRYPT', 'SUPER_ADMIN', 'active');
-- Gere o hash com: PHP password_hash('sua-senha', PASSWORD_BCRYPT)
--
-- 2) O campo `status` em `games` já inicia como 'published' para compatibilidade com
--    getTotalGames() (que conta WHERE status = 'published').
--
-- 3) As colunas adicionadas via ensureGameColumns() já estão presentes aqui:
--    developer_name, languages_multi, updated_at_custom, released_at_custom,
--    patreon_url, discord_url, subscribestar_url, itch_url, kofi_url, bmc_url,
--    steam_url, screenshots. Assim, a função não fará alterações adicionais.
--
-- 4) Índices:
--    - `ux_games_slug` acelera busca por slug e garante unicidade para URLs.
--    - `ix_games_posted_by_created_at` otimiza a listagem no dashboard por autores.
--    - FULLTEXT em (title, description, tags) facilita buscas (search_games.php).
--
-- 5) Regras de exclusão (FKs):
--    - Deletar um jogo remove seus comentários (ON DELETE CASCADE).
--    - Deletar um usuário remove seus comentários, mas impede deletar caso possua
--      jogos publicados (RESTRICT em games.posted_by). Ajuste para SET NULL se desejar.
--
-- 6) Compatibilidade:
--    - Caso seu MySQL não suporte FULLTEXT em InnoDB, remova o CREATE FULLTEXT INDEX.
--    - Caso prefira `languages_multi` como JSON nativo, altere para `JSON`.
--
-- 7) Performance & manutenção:
--    - Considere partição/arquivamento de `comments` se volume crescer muito.
--    - Considere logs de downloads em tabela separada se precisar de analytics.

