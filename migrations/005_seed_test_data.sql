-- ⚠️ SEEDS DE PRUEBA — Opcional.
-- Solo corre esto en un ambiente NUEVO/vacío (ej. otra máquina de desarrollo).
-- Si tu base de datos actual ya tiene estos registros (o el id=2 ya existe
-- con otro contenido), esta migración fallará por la UNIQUE KEY `correo`
-- o por PRIMARY KEY duplicada — eso es intencional, para no pisar datos reales.

INSERT INTO `usuarios` (`id`, `nombre`, `correo`, `password`, `rol`, `created_at`) VALUES
(2, 'Jose Alberto', 'admin@correo.com', '$2y$10$ZO4OJi4IZkbkUxx/GzgpZeR3K90hN2kgZBs5s1qqkyxc64iZ1yGCK', 'admin', '2026-06-29 17:37:49'),
(3, 'Operario Patio', 'jose@admin.com', '$2y$10$2ppZmxqMNAyz.yxqTZPprOvZN/JCNrNpK3itYa7PKyoif.oJ.a3lO', 'user', '2026-06-29 17:43:57');

INSERT INTO `containers` (`id`, `code`, `type`, `status`, `customs_status`, `warehouse`, `slot`, `entry_date`, `exit_date`) VALUES
(1, 'SUDU2033845', 'Dry Van', 'Mantenimiento', 1, 'Lobo', '3 b2', '2026-06-29 17:46:02', NULL);

INSERT INTO `movements` (`id`, `container_id`, `user_id`, `movement_type`, `latitude`, `longitude`, `created_at`) VALUES
(1, 1, 3, 'ENTRY', 3.84324787, -77.00675377, '2026-06-29 17:46:02');
