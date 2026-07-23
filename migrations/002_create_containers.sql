CREATE TABLE IF NOT EXISTS `containers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(11) NOT NULL,
  `type` ENUM('Dry Van','Reefer','High Cube','Vacío','Tránsito') NOT NULL,
  `status` ENUM('Operativo','Mantenimiento','Dañado') DEFAULT 'Operativo',
  `customs_status` TINYINT(1) NOT NULL DEFAULT 0,
  `warehouse` VARCHAR(100) NOT NULL,
  `slot` VARCHAR(50) NOT NULL,
  `entry_date` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  `exit_date` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
