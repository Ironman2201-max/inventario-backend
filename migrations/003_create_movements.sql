CREATE TABLE IF NOT EXISTS `movements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `container_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `movement_type` ENUM('ENTRY','EXIT') NOT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `container_id` (`container_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_movements_containers` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_movements_users` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
