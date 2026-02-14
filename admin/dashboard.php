<?php
session_start();
require __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: /sgr-it/login.php");
    exit;
}

// Crear recurso
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["crear_recurso"])) {
    $nombre = trim($_POST["nombre"] ?? "");
    $tipo   = trim($_POST["tipo"] ?? "");
    $estado = $_POST["estado"] ?? "disponible";
    $categoria_id = (int)($_POST["categoria_id"] ?? 0);
    if ($categoria_id <= 0) $categoria_id = null;

    if ($nombre !== "") {
        $stmt = $conn->prepare("INSERT INTO recursos (nombre, tipo, estado, categoria_id) VALUES (?, ?, ?, ?)");
        // bind_param no acepta null bien con "i" si es null; lo resolvemos con variable auxiliar:
        if ($categoria_id === null) {
            $cid = null;
            $stmt->bind_param("sssi", $nombre, $tipo, $estado, $cid);
        } else {
            $cid = $categoria_id;
            $stmt->bind_param("sssi", $nombre, $tipo, $estado, $cid);
        }
        $stmt->execute();
    }
    header("Location: dashboard.php?recurso_ok=1");
    exit;
}

// Cancelar reserva (admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancelar_reserva"])) {
    $reserva_id = (int)($_POST["reserva_id"] ?? 0);
    if ($reserva_id > 0) {
        $stmt = $conn->prepare("DELETE FROM reservas WHERE id = ?");
        $stmt->bind_param("i", $reserva_id);
        $stmt->execute();
    }
    header("Location: dashboard.php?cancel_ok=1");
    exit;
}

/* ======= CATEGORÍAS ======= */
$categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");

/* ======= MÉTRICAS ======= */
$stats = [
  "recursos_total" => 0,
  "recursos_reparacion" => 0,
  "reservas_activas" => 0,
  "usuarios_total" => 0,
];

$row = $conn->query("SELECT COUNT(*) AS c FROM recursos")->fetch_assoc();
$stats["recursos_total"] = (int)$row["c"];

$row = $conn->query("SELECT COUNT(*) AS c FROM recursos WHERE estado='en_reparacion'")->fetch_assoc();
$stats["recursos_reparacion"] = (int)$row["c"];

$row = $conn->query("SELECT COUNT(*) AS c FROM reservas WHERE fecha_fin >= NOW()")->fetch_assoc();
$stats["reservas_activas"] = (int)$row["c"];

$row = $conn->query("SELECT COUNT(*) AS c FROM usuarios")->fetch_assoc();
$stats["usuarios_total"] = (int)$row["c"];

/* ======= LISTADOS ======= */
$recursos = $conn->query("
  SELECT r.id, r.nombre, r.tipo, r.estado, c.nombre AS categoria
  FROM recursos r
  LEFT JOIN categorias c ON c.id = r.categoria_id
  ORDER BY r.id DESC
");

$reservas = $conn->query("
    SELECT r.id, r.fecha_inicio, r.fecha_fin,
           u.nombre AS usuario_nombre,
           rec.nombre AS recurso_nombre
    FROM reservas r
    JOIN usuarios u ON u.id = r.usuario_id
    JOIN recursos rec ON rec.id = r.recurso_id
    ORDER BY r.fecha_inicio DESC
");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | SGR-IT</title>
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
      <a class="active" href="/sgr-it/admin/dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
      <a href="/sgr-it/admin/recursos.php"><i class="bi bi-box-seam"></i>Recursos</a>
    </nav>
  </aside>

  <main class="sgr-content">
    <div class="container-fluid p-3 p-lg-4">

      <?php if (isset($_GET["recurso_ok"])): ?>
        <div class="alert alert-success">Recurso creado ✅</div>
      <?php endif; ?>
      <?php if (isset($_GET["cancel_ok"])): ?>
        <div class="alert alert-success">Reserva cancelada ✅</div>
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Recursos</div>
            <div class="sgr-stat-value"><?php echo $stats["recursos_total"]; ?></div>
            <div class="text-muted small">Total inventario</div>
            <div class="sgr-stat-icon"><i class="bi bi-laptop"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">En reparación</div>
            <div class="sgr-stat-value"><?php echo $stats["recursos_reparacion"]; ?></div>
            <div class="text-muted small">No reservables</div>
            <div class="sgr-stat-icon"><i class="bi bi-tools"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Reservas activas</div>
            <div class="sgr-stat-value"><?php echo $stats["reservas_activas"]; ?></div>
            <div class="text-muted small">En curso o futuras</div>
            <div class="sgr-stat-icon"><i class="bi bi-calendar-check"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Usuarios</div>
            <div class="sgr-stat-value"><?php echo $stats["usuarios_total"]; ?></div>
            <div class="text-muted small">Registrados</div>
            <div class="sgr-stat-icon"><i class="bi bi-people"></i></div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-5">
          <div class="card p-3 p-lg-4" id="crear-recurso">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Crear recurso</h5>
              <span class="badge badge-soft">Inventario</span>
            </div>

            <form method="post">
              <input type="hidden" name="crear_recurso" value="1">

              <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input class="form-control" type="text" name="nombre" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Tipo</label>
                <input class="form-control" type="text" name="tipo" placeholder="laptop_steam, sala, webcam...">
              </div>

              <div class="mb-3">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="categoria_id">
                  <option value="0">— Sin categoría —</option>
                  <?php while($c = $categorias->fetch_assoc()): ?>
                    <option value="<?php echo (int)$c["id"]; ?>"><?php echo htmlspecialchars($c["nombre"]); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                  <option value="disponible">Disponible</option>
                  <option value="en_reparacion">En reparación</option>
                </select>
              </div>

              <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle me-1"></i>Crear
              </button>
            </form>
          </div>

          <div class="card p-3 p-lg-4 mt-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Recursos</h5>
              <a class="btn btn-outline-secondary btn-sm" href="/sgr-it/admin/recursos.php">
                <i class="bi bi-eye me-1"></i>Ver por categorías + historial
              </a>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>ID</th><th>Nombre</th><th>Categoría</th><th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $recursos->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo (int)$row["id"]; ?></td>
                      <td class="fw-semibold"><?php echo htmlspecialchars($row["nombre"]); ?></td>
                      <td><?php echo htmlspecialchars($row["categoria"] ?? ""); ?></td>
                      <td>
                        <?php if ($row["estado"] === "disponible"): ?>
                          <span class="badge text-bg-success">disponible</span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary">en_reparación</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

        <div class="col-12 col-lg-7">
          <div class="card p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Todas las reservas</h5>
              <span class="badge badge-soft">Control</span>
            </div>

            <?php if ($reservas->num_rows === 0): ?>
              <div class="text-muted">No hay reservas registradas.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Usuario</th><th>Recurso</th><th>Inicio</th><th>Fin</th><th class="text-end">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($r = $reservas->fetch_assoc()): ?>
                      <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($r["usuario_nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($r["recurso_nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($r["fecha_inicio"]); ?></td>
                        <td><?php echo htmlspecialchars($r["fecha_fin"]); ?></td>
                        <td class="text-end">
                          <form method="post" class="d-inline">
                            <input type="hidden" name="cancelar_reserva" value="1">
                            <input type="hidden" name="reserva_id" value="<?php echo (int)$r["id"]; ?>">
                            <button class="btn btn-outline-danger btn-sm" type="submit"
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
      </div>

    </div>
  </main>
</div>

</body>
</html>
