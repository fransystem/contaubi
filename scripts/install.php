<?php
/**
 * ContaUBI — instalador CLI/Web.
 * Crea la base, schema y carga el PUCT (Plan Único de Cuentas Tributario)
 * de Bolivia, fijado por el SIN.
 *
 * Uso:
 *   php scripts/install.php
 *   o navegar a /scripts/install.php en el navegador (sólo en desarrollo).
 */
declare(strict_types=1);

$DB_HOST = getenv('CONTAUBI_DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('CONTAUBI_DB_USER') ?: 'root';
$DB_PASS = getenv('CONTAUBI_DB_PASS') ?: '';

$is_cli = (PHP_SAPI === 'cli');
$line = function(string $s) use ($is_cli) {
    echo $is_cli ? "$s\n" : nl2br(htmlspecialchars($s) . "\n");
};

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
    $conn->set_charset('utf8mb4');
    $line("→ Conectado a MySQL en {$DB_HOST}");
} catch (Throwable $e) {
    $line("✗ Error: " . $e->getMessage());
    exit(1);
}

$schema = file_get_contents(__DIR__ . '/../db/schema.sql');
$seed   = file_get_contents(__DIR__ . '/../db/seed_puc.sql');
if ($schema === false || $seed === false) {
    $line("✗ No se encontraron los archivos SQL en db/");
    exit(1);
}

if ($conn->multi_query($schema)) {
    do { /* drain results */ } while ($conn->more_results() && $conn->next_result());
    $line("✓ Schema aplicado (db/schema.sql)");
} else {
    $line("✗ Error al ejecutar schema: " . $conn->error);
    exit(1);
}

if ($conn->multi_query($seed)) {
    do { /* drain */ } while ($conn->more_results() && $conn->next_result());
    $line("✓ PUCT Bolivia cargado (db/seed_puc.sql)");
} else {
    $line("✗ Error al ejecutar seed: " . $conn->error);
    exit(1);
}

$conn->select_db('contaubi');
$count = (int)$conn->query("SELECT COUNT(*) c FROM cuentas")->fetch_assoc()['c'];
$line("→ Total de cuentas en el plan: {$count}");
$line("");
$line("Instalación completa. Abrí la app en tu navegador:");
$line("    php -S 0.0.0.0:8000 -t .");
