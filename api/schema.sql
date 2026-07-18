CREATE DATABASE IF NOT EXISTS `checador`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `checador`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modules_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_module_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 0,
  `can_create` TINYINT(1) NOT NULL DEFAULT 0,
  `can_update` TINYINT(1) NOT NULL DEFAULT 0,
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_module_unique` (`user_id`, `module_id`),
  CONSTRAINT `permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permissions_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `modules` (`code`, `name`)
VALUES
  ('dashboard', 'Inicio'),
  ('usuarios', 'Usuarios'),
  ('nomina', 'Nomina'),
  ('checador', 'Checador')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `is_active`)
VALUES
  ('admin', '$2y$10$Ir1F37kUxoX9cb1LQuHmiekmKuidqyS1dRoIQY9RHl6gM.sOpHBSy', 'Administrador', NULL, 1)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

INSERT INTO `user_module_permissions` (`user_id`, `module_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, m.id, 1, 1, 1, 1
FROM `users` u
CROSS JOIN `modules` m
WHERE u.username = 'admin'
ON DUPLICATE KEY UPDATE
  `can_view` = VALUES(`can_view`),
  `can_create` = VALUES(`can_create`),
  `can_update` = VALUES(`can_update`),
  `can_delete` = VALUES(`can_delete`);
