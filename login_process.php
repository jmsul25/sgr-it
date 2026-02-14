<?php
session_start();
require __DIR__ . "/config/db.php";

$email = trim($_POST["email"] ?? "");
$pass  = $_POST["password"] ?? "";

if ($email === "" || $pass === "") {
    header("Location: /sgr-it/login.php?e=1");
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || !password_verify($pass, $user["password_hash"])) {
    header("Location: /sgr-it/login.php?e=1");
    exit;
}

// Login OK
$_SESSION["user_id"] = (int)$user["id"];
$_SESSION["nombre"]  = $user["nombre"];
$_SESSION["email"]   = $user["email"];
$_SESSION["rol"]     = $user["rol"];

if ($user["rol"] === "admin") {
    header("Location: /sgr-it/admin/dashboard.php");
} else {
    header("Location: /sgr-it/user/dashboard.php");
}
exit;
