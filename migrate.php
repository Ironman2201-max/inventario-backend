<?php
/**
 * migrate.php
 * -----------
 * Sistema simple de migraciones automáticas para MySQL/PDO.
 *
 * Uso:
 *   php migrate.php            -> aplica todas las migraciones pendientes
 *   php migrate.php --status   -> muestra cuáles están aplicadas y cuáles faltan
 *
 * También se puede correr desde el navegador visitando migrate.php,
 * pero se recomienda protegerlo o eliminarlo en producción.
 *
 * Cómo agregar una migración nueva:
 *   1. Crea un archivo en /migrations con el formato:
 *      NNN_descripcion_corta.sql   (ej: 004_add_email_to_usuarios.sql)
 *   2. Escribe el SQL dentro (CREATE TABLE, ALTER TABLE, etc.)
 *   3. Vuelve a correr: php migrate.php
 *      Se aplicará solo esa, ya que las anteriores quedan registradas.
 */

require_once __DIR__ . '/conexion.php'; // Debe definir $pdo (PDO)

$migrationsDir = __DIR__ . '/migrations';
$showStatus = in_array('--status', $argv ?? []);
$isCli = (php_sapi_name() === 'cli');

/**
 * Imprime una línea, agregando <br> si se está ejecutando desde el navegador.
 */
function out(string $texto): void {
    global $isCli;
    echo $isCli ? $texto . "\n" : nl2br(htmlspecialchars($texto)) . "\n";
}

if (!$isCli) {
    echo "<pre style='font-family: monospace; font-size: 14px;'>";
}

/**
 * Termina la ejecución cerrando el <pre> si estamos en navegador.
 */
function finalizar(int $codigo = 0): void {
    global $isCli;
    if (!$isCli) {
        echo "</pre>";
    }
    exit($codigo);
}

function ensureMigrationsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getAppliedMigrations(PDO $pdo): array {
    $stmt = $pdo->query("SELECT migration FROM migrations");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getMigrationFiles(string $dir): array {
    $files = glob($dir . '/*.sql');
    sort($files); // El prefijo numérico (001_, 002_...) garantiza el orden correcto
    return $files;
}

try {
    ensureMigrationsTable($pdo);
    $applied = getAppliedMigrations($pdo);
    $files = getMigrationFiles($migrationsDir);

    if ($showStatus) {
        out("=== ESTADO DE MIGRACIONES ===");
        foreach ($files as $file) {
            $name = basename($file);
            $estado = in_array($name, $applied) ? '✅ aplicada' : '⏳ pendiente';
            out("  [$estado] $name");
        }
        finalizar(0);
    }

    $pendientes = array_filter($files, fn($f) => !in_array(basename($f), $applied));

    if (empty($pendientes)) {
        out("✅ No hay migraciones pendientes. Todo está al día.");
        finalizar(0);
    }

    out("Aplicando " . count($pendientes) . " migración(es) pendiente(s)...");

    foreach ($pendientes as $file) {
        $name = basename($file);
        $sql = file_get_contents($file);

        echo $isCli ? "-> Aplicando: $name ... " : "-> Aplicando: " . htmlspecialchars($name) . " ... ";

        try {
            $pdo->beginTransaction();

            // Permite múltiples sentencias separadas por ; dentro de un mismo archivo
            $pdo->exec($sql);

            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (:m)");
            $stmt->execute([':m' => $name]);

            $pdo->commit();
            out("OK ✅");
        } catch (PDOException $e) {
            $pdo->rollBack();
            out("FALLÓ ❌");
            out("   Error: " . $e->getMessage());
            out("   Se detiene la ejecución. Corrige el archivo $name y vuelve a correr.");
            finalizar(1);
        }
    }

    out("");
    out("🎉 Todas las migraciones se aplicaron correctamente.");

} catch (PDOException $e) {
    out("Error de conexión o al preparar el sistema de migraciones: " . $e->getMessage());
    finalizar(1);
}

finalizar(0);