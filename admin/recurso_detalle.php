<?php
session_start();
require __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: /sgr-it/login.php");
    exit;
}

$recurso_id = (int)($_GET["id"] ?? 0);
$cat = (int)($_GET["cat"] ?? 0);

if ($recurso_id <= 0) {
    header("Location: /sgr-it/admin/recursos.php" . ($cat>0 ? "?cat=".$cat : ""));
    exit;
}

// Cancelación desde detalle
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancelar_reserva"])) {
    $reserva_id = (int)($_POST["reserva_id"] ?? 0);
    if ($reserva_id > 0) {
        $stmt = $conn->prepare("DELETE FROM reservas WHERE id = ?");
        $stmt->bind_param("i", $reserva_id);
        $stmt->execute();
    }
    header("Location: /sgr-it/admin/recurso_detalle.php?id=".$recurso_id.($cat>0 ? "&cat=".$cat : "")."&cancel_ok=1");
    exit;
}

// Cargar recurso + categoría
$stmt = $conn->prepare("
  SELECT r.id, r.nombre, r.tipo, r.estado, c.nombre AS categoria
  FROM recursos r
  LEFT JOIN categorias c ON c.id = r.categoria_id
  WHERE r.id = ?
  LIMIT 1
");
$stmt->bind_param("i", $recurso_id);
$stmt->execute();
$recurso = $stmt->get_result()->fetch_assoc();

if (!$recurso) {
    header("Location: /sgr-it/admin/recursos.php" . ($cat>0 ? "?cat=".$cat : ""));
    exit;
}

// Historial de reservas del recurso con usuario
$stmt = $conn->prepare("
  SELECT r.id, r.fecha_inicio, r.fecha_fin,
         u.nombre AS usuario_nombre, u.email AS usuario_email
  FROM reservas r
  JOIN usuarios u ON u.id = r.usuario_id
  WHERE r.recurso_id = ?
  ORDER BY r.fecha_inicio DESC
");
$stmt->bind_param("i", $recurso_id);
$stmt->execute();
$historial = $stmt->get_result();

// Métricas rápidas
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM reservas WHERE recurso_id = ?");
$stmt->bind_param("i", $recurso_id);
$stmt->execute();
$total_res = (int)$stmt->get_result()->fetch_assoc()["c"];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM reservas WHERE recurso_id = ? AND fecha_fin >= NOW()");
$stmt->bind_param("i", $recurso_id);
$stmt->execute();
$activas_res = (int)$stmt->get_result()->fetch_assoc()["c"];

$volver = "/sgr-it/admin/recursos.php" . ($cat>0 ? "?cat=".$cat : "");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Detalle recurso</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/sgr-it/assets/css/sgr.css" rel="stylesheet">
</head>
<body>

<nav class="navbar sgr-topbar">
  <div class="container-fluid px-3">
    <span class="navbar-brand text-white fw-bold">SGR-IT</span>
    <div class="text-white-50 small">
      <?php echo htmlspecialchars($_SESSION["nombre"]); ?> ·
      <a class="text-white" href="/sgr-it/logout.php">Cerrar sesión</a>
    </div>
  </div>
</nav>

<div class="sgr-layout">
  <aside class="sgr-sidebar">
    <div class="sgr-brand">Admin</div>
    <nav class="sgr-nav">
      <a href="/sgr-it/admin/dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
      <a class="active" href="/sgr-it/admin/recursos.php"><i class="bi bi-box-seam"></i>Recursos</a>
    </nav>
  </aside>

  <main class="sgr-content">
    <div class="container-fluid p-3 p-lg-4">

      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h3 class="mb-1"><?php echo htmlspecialchars($recurso["nombre"]); ?></h3>
          <div class="text-muted">
            Categoría: <span class="fw-semibold"><?php echo htmlspecialchars($recurso["categoria"] ?? ""); ?></span> ·
            Tipo: <span class="fw-semibold"><?php echo htmlspecialchars($recurso["tipo"] ?? ""); ?></span> ·
            Estado:
            <?php if (($recurso["estado"] ?? "") === "disponible"): ?>
              <span class="badge text-bg-success">disponible</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">en_reparación</span>
            <?php endif; ?>
          </div>
        </div>

        <a class="btn btn-outline-secondary" href="<?php echo $volver; ?>">
          <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
      </div>

      <?php if (isset($_GET["cancel_ok"])): ?>
        <div class="alert alert-success">Reserva cancelada ✅</div>
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Reservas</div>
            <div class="sgr-stat-value"><?php echo $total_res; ?></div>
            <div class="text-muted small">Total historial</div>
            <div class="sgr-stat-icon"><i class="bi bi-journal-text"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Activas</div>
            <div class="sgr-stat-value"><?php echo $activas_res; ?></div>
            <div class="text-muted small">En curso o futuras</div>
            <div class="sgr-stat-icon"><i class="bi bi-calendar-check"></i></div>
          </div>
        </div>
      </div>

      <div class="card p-3 p-lg-4">
        <h5 class="mb-3"><i class="bi bi-clock-history me-2"></i>Historial de reservas</h5>

        <?php if ($historial->num_rows === 0): ?>
          <div class="text-muted">Este recurso no tiene reservas todavía.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Empleado</th>
                  <th>Inicio</th>
                  <th>Fin</th>
                  <th class="text-end">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($h = $historial->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars($h["usuario_nombre"]); ?></div>
                      <div class="text-muted small"><?php echo htmlspecialchars($h["usuario_email"]); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($h["fecha_inicio"]); ?></td>
                    <td><?php echo htmlspecialchars($h["fecha_fin"]); ?></td>
                    <td class="text-end">
                      <form method="post" class="d-inline">
                        <input type="hidden" name="cancelar_reserva" value="1">
                        <input type="hidden" name="reserva_id" value="<?php echo (int)$h["id"]; ?>">
                        <button class="btn btn-outline-danger btn-sm"
                                type="submit"
                                onclick="return confirm('¿Cancelar esta reserva?');">
                          <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>

    </div>
  </main>
</div>

</body>
</html>
