<?php
session_start();
require __DIR__ . "/../config/db.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["user_id"]) || ($_SESSION["rol"] ?? "") !== "empleado") {
    header("Location: /sgr-it/login.php");
    exit;
}

$usuario_id = (int)($_SESSION["user_id"] ?? 0);
$modo = $_POST["modo"] ?? "individual";

$inicio_raw = $_POST["inicio"] ?? "";
$fin_raw    = $_POST["fin"] ?? "";

$cat_return = (int)($_POST["cat_return"] ?? 0);
$back = "/sgr-it/user/dashboard.php" . ($cat_return > 0 ? ("?cat=".$cat_return) : "");

// datetime-local -> MySQL
$inicio = str_replace("T", " ", $inicio_raw) . ":00";
$fin    = str_replace("T", " ", $fin_raw) . ":00";

if ($usuario_id <= 0 || $inicio_raw === "" || $fin_raw === "") {
    header("Location: {$back}&err=1");
    exit;
}
if (strtotime($fin) <= strtotime($inicio)) {
    header("Location: {$back}&err=fechas");
    exit;
}

function recursoDisponible(mysqli $conn, int $recurso_id): bool {
    $stmt = $conn->prepare("SELECT estado FROM recursos WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $recurso_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return ($r && $r["estado"] === "disponible");
}

function haySolape(mysqli $conn, int $recurso_id, string $inicio, string $fin): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reservas
        WHERE recurso_id = ?
          AND (? < fecha_fin)
          AND (? > fecha_inicio)
    ");
    $stmt->bind_param("iss", $recurso_id, $inicio, $fin);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ((int)$row["total"]) > 0;
}

try {
    $conn->begin_transaction();

    // ===== MODO INDIVIDUAL =====
    if ($modo === "individual") {
        $recurso_id = (int)($_POST["recurso_id"] ?? 0);

        if ($recurso_id <= 0) {
            $conn->rollback();
            header("Location: {$back}&err=recurso");
            exit;
        }

        if (!recursoDisponible($conn, $recurso_id)) {
            $conn->rollback();
            header("Location: {$back}&err=recurso");
            exit;
        }

        if (haySolape($conn, $recurso_id, $inicio, $fin)) {
            $conn->rollback();
            header("Location: {$back}&err=solape");
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO reservas (usuario_id, recurso_id, fecha_inicio, fecha_fin)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $usuario_id, $recurso_id, $inicio, $fin);
        $stmt->execute();

        $conn->commit();
        header("Location: {$back}&ok=1");
        exit;
    }

    // ===== MODO CATEGORÍA (CANTIDAD) =====
    $categoria_id = (int)($_POST["categoria_id"] ?? 0);
    $cantidad = (int)($_POST["cantidad"] ?? 0);

    if ($categoria_id <= 0 || $cantidad < 2) {
        $conn->rollback();
        header("Location: {$back}&err=cantidad");
        exit;
    }
    if ($cantidad > 50) $cantidad = 50;

    // 1) Obtener nombre de categoría para bloquear "Salas"
    $stmt = $conn->prepare("SELECT nombre FROM categorias WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $categoria_id);
    $stmt->execute();
    $catRow = $stmt->get_result()->fetch_assoc();

    if (!$catRow) {
        $conn->rollback();
        header("Location: {$back}&err=cantidad");
        exit;
    }

    $nombre_categoria = mb_strtolower(trim($catRow["nombre"]), "UTF-8");

    // Si es "Salas", NO permitir cantidad (forzar 1 => mejor obligar a usar individual)
    if ($nombre_categoria === "salas") {
        $conn->rollback();
        // Mensaje reutilizando err=cantidad (si quieres otro código, lo cambiamos)
        header("Location: {$back}&err=cantidad");
        exit;
    }

    // 2) Seleccionar N recursos disponibles de esa categoría sin solape
    $stmt = $conn->prepare("
        SELECT rec.id
        FROM recursos rec
        WHERE rec.categoria_id = ?
          AND rec.estado = 'disponible'
          AND NOT EXISTS (
            SELECT 1
            FROM reservas r
            WHERE r.recurso_id = rec.id
              AND (? < r.fecha_fin)
              AND (? > r.fecha_inicio)
          )
        ORDER BY rec.id ASC
        LIMIT {$cantidad}
    ");
    $stmt->bind_param("iss", $categoria_id, $inicio, $fin);
    $stmt->execute();
    $res = $stmt->get_result();

    $ids = [];
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row["id"];

    if (count($ids) < $cantidad) {
        $conn->rollback();
        header("Location: {$back}&err=cantidad");
        exit;
    }

    // 3) Insertar una reserva por recurso
    $ins = $conn->prepare("
        INSERT INTO reservas (usuario_id, recurso_id, fecha_inicio, fecha_fin)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($ids as $rid) {
        $ins->bind_param("iiss", $usuario_id, $rid, $inicio, $fin);
        $ins->execute();
    }

    $conn->commit();
    header("Location: {$back}&ok_multi=1");
    exit;

} catch (Throwable $e) {
    if ($conn->errno) $conn->rollback();
    header("Location: {$back}&err=1");
    exit;
}
