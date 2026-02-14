<?php
// config/db.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = "127.0.0.1";
$DB_USER = "root";
$DB_PASS = "";          // En XAMPP suele estar vacío
$DB_NAME = "sgr_it";

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "Error de conexión a la BBDD: " . htmlspecialchars($e->getMessage());
    exit;
}
