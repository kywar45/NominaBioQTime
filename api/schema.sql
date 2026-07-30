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

CREATE TABLE IF NOT EXISTS `nomina_reglas` (
  `id_regla` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `descripcion` TEXT NULL,
  `tipo` ENUM('sancion', 'bonificacion') NOT NULL,
  `alcance` ENUM('todos', 'departamento', 'turno', 'empleado') NOT NULL DEFAULT 'todos',
  `target_id` VARCHAR(60) NULL,
  `condicion` ENUM('retardo_minutos', 'falta', 'hora_extra_minutos', 'asistencia_perfecta', 'manual') NOT NULL,
  `operador` ENUM('=', '>=', '<=') NULL,
  `valor_condicion` DECIMAL(10,2) NULL,
  `tipo_valor` ENUM('monto', 'porcentaje', 'dias', 'minutos') NOT NULL DEFAULT 'monto',
  `valor` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `frecuencia` ENUM('por_evento', 'por_dia', 'por_periodo') NOT NULL DEFAULT 'por_evento',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_regla`),
  KEY `nomina_reglas_tipo_idx` (`tipo`),
  KEY `nomina_reglas_alcance_idx` (`alcance`, `target_id`),
  KEY `nomina_reglas_activo_idx` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vacaciones_asignaciones` (
  `id_vacacion` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empleado_id` VARCHAR(60) NOT NULL,
  `dias_vacaciones` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE NOT NULL,
  `notas` TEXT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_vacacion`),
  KEY `vacaciones_asignaciones_empleado_idx` (`empleado_id`),
  KEY `vacaciones_asignaciones_fechas_idx` (`fecha_inicio`, `fecha_fin`),
  KEY `vacaciones_asignaciones_activo_idx` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dias_festivos` (
  `id_festivo` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `fecha` DATE NOT NULL,
  `no_laborable` TINYINT(1) NOT NULL DEFAULT 1,
  `notas` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_festivo`),
  UNIQUE KEY `dias_festivos_fecha_unique` (`fecha`),
  KEY `dias_festivos_no_laborable_idx` (`no_laborable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prestamos` (
  `id_prestamo` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empleado_id` VARCHAR(60) NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `fecha_prestamo` DATE NOT NULL,
  `numero_pagos` INT UNSIGNED NOT NULL DEFAULT 1,
  `frecuencia_pago` ENUM('semanal', 'quincenal', 'mensual') NOT NULL DEFAULT 'mensual',
  `primer_pago` DATE NOT NULL,
  `plan_json` JSON NOT NULL,
  `notas` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_prestamo`),
  KEY `prestamos_empleado_idx` (`empleado_id`),
  KEY `prestamos_fecha_idx` (`fecha_prestamo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prestamos_pagos` (
  `id_pago` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prestamo_id` INT UNSIGNED NOT NULL,
  `fecha_pago` DATE NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `notas` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`),
  KEY `prestamos_pagos_prestamo_idx` (`prestamo_id`),
  KEY `prestamos_pagos_fecha_idx` (`fecha_pago`),
  CONSTRAINT `prestamos_pagos_prestamo_fk` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id_prestamo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `empresas` (
  `id_empresa` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(140) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_empresa`),
  UNIQUE KEY `empresas_nombre_unique` (`nombre`),
  KEY `empresas_activo_idx` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bancos` (
  `id_banco` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_banco`),
  UNIQUE KEY `bancos_nombre_unique` (`nombre`),
  KEY `bancos_activo_idx` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `empleado_imss_configuracion` (
  `id_imss` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empleado_id` VARCHAR(60) NOT NULL,
  `posee_imss` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_alta` DATE NULL,
  `numero_seguro_social` VARCHAR(30) NULL,
  `banco_id` INT UNSIGNED NULL,
  `cuenta_deposito` VARCHAR(40) NULL,
  `empresa_id` INT UNSIGNED NULL,
  `tipo_sueldo` ENUM('diario', 'semanal', 'quincenal', 'mensual') NULL,
  `notas` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_imss`),
  UNIQUE KEY `empleado_imss_empleado_unique` (`empleado_id`),
  KEY `empleado_imss_posee_idx` (`posee_imss`),
  KEY `empleado_imss_banco_idx` (`banco_id`),
  KEY `empleado_imss_empresa_idx` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `modules` (`code`, `name`)
VALUES
  ('dashboard', 'Inicio'),
  ('usuarios', 'Usuarios'),
  ('vacaciones', 'Vacaciones'),
  ('dias_festivos', 'Dias festivos'),
  ('prestamos', 'Prestamos'),
  ('imss', 'IMSS'),
  ('nomina', 'Nomina'),
  ('checador', 'Checador'),
  ('empleados', 'Empleados'),
  ('turnos', 'Turnos'),
  ('departamentos', 'Departamentos'),
  ('reglas', 'Reglas')
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
