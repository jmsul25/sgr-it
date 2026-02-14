<?php
session_start();
require __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: /sgr-it/login.php");
    exit;
}

$nombre = trim($_POST["nombre"] ?? "");
$tipo   = trim($_POST["tipo"] ?? "");
$estado = $_POST["estado"] ?? "disponible";

if ($nombre === "") {
    header("Location: dashboard.php");
    exit;
}

$stmt = $conn->prepare("INSERT INTO recursos (nombre, tipo, estado) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $nombre, $tipo, $estado);
$stmt->execute();

header("Location: dashboard.php");
exit;
