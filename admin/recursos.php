<?php
session_start();
require __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: /sgr-it/login.php");
    exit;
}

$cat = (int)($_GET["cat"] ?? 0);

/**
 * Helper para redirigir sin romper la URL (? / &)
 */
function redirect_recursos(int $cat_keep, array $extra = []) {
    $params = [];
    if ($cat_keep > 0) $params["cat"] = $cat_keep;
    foreach ($extra as $k => $v) $params[$k] = $v;

    $qs = http_build_query($params);
    $url = "/sgr-it/admin/recursos.php" . ($qs ? ("?".$qs) : "");
    header("Location: ".$url);
    exit;
}

// Cambiar estado (disponible <-> en_reparacion)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_estado"])) {
    $recurso_id = (int)($_POST["recurso_id"] ?? 0);
    $cat_keep = (int)($_POST["cat_keep"] ?? 0);

    if ($recurso_id > 0) {
        $stmt = $conn->prepare("
          UPDATE recursos
          SET estado = CASE
            WHEN estado='disponible' THEN 'en_reparacion'
            ELSE 'disponible'
          END
          WHERE id = ?
        ");
        $stmt->bind_param("i", $recurso_id);
        $stmt->execute();
    }

    // ✅ Redirección correcta siempre
    redirect_recursos($cat_keep, ["estado_ok" => 1]);
}

// Categorías
$categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");

// Recursos con contadores + categoría (filtrados si cat>0)
if ($cat > 0) {
    $stmt = $conn->prepare("
      SELECT
        rec.id, rec.nombre, rec.tipo, rec.estado,
        c.nombre AS categoria,
        COUNT(r.id) AS total_reservas,
        SUM(CASE WHEN r.fecha_fin >= NOW() THEN 1 ELSE 0 END) AS reservas_activas
      FROM recursos rec
      LEFT JOIN categorias c ON c.id = rec.categoria_id
      LEFT JOIN reservas r ON r.recurso_id = rec.id
      WHERE rec.categoria_id = ?
      GROUP BY rec.id, rec.nombre, rec.tipo, rec.estado, c.nombre
      ORDER BY rec.nombre ASC
    ");
    $stmt->bind_param("i", $cat);
    $stmt->execute();
    $recursos = $stmt->get_result();
} else {
    $recursos = $conn->query("
      SELECT
        rec.id, rec.nombre, rec.tipo, rec.estado,
        c.nombre AS categoria,
        COUNT(r.id) AS total_reservas,
        SUM(CASE WHEN r.fecha_fin >= NOW() THEN 1 ELSE 0 END) AS reservas_activas
      FROM recursos rec
      LEFT JOIN categorias c ON c.id = rec.categoria_id
      LEFT JOIN reservas r ON r.recurso_id = rec.id
      GROUP BY rec.id, rec.nombre, rec.tipo, rec.estado, c.nombre
      ORDER BY rec.nombre ASC
    ");
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Recursos</title>
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
        <h3 class="mb-0"><i class="bi bi-box-seam me-2"></i>Recursos</h3>
        <a class="btn btn-outline-primary btn-sm" href="/sgr-it/admin/dashboard.php#crear-recurso">
          <i class="bi bi-plus-circle me-1"></i>Nuevo recurso
        </a>
      </div>

      <?php if (isset($_GET["estado_ok"])): ?>
        <div class="alert alert-success">Estado actualizado ✅</div>
      <?php endif; ?>

      <!-- Filtro por categoría -->
      <div class="card p-3 p-lg-4 mb-3">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Filtrar por categoría</label>
            <select class="form-select" name="cat" onchange="this.form.submit()">
              <option value="0" <?php echo ($cat===0 ? "selected" : ""); ?>>Todas</option>
              <?php while($c = $categorias->fetch_assoc()): ?>
                <option value="<?php echo (int)$c["id"]; ?>" <?php echo ($cat===(int)$c["id"] ? "selected" : ""); ?>>
                  <?php echo htmlspecialchars($c["nombre"]); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a class="btn btn-outline-secondary" href="/sgr-it/admin/recursos.php">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Quitar filtro
            </a>
          </div>
        </form>
      </div>

      <div class="card p-3 p-lg-4">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="text-center">Reservas</th>
                <th class="text-center">Activas</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $recursos->fetch_assoc()): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($row["nombre"]); ?></td>
                  <td><?php echo htmlspecialchars($row["categoria"] ?? ""); ?></td>
                  <td><?php echo htmlspecialchars($row["tipo"] ?? ""); ?></td>
                  <td>
                    <?php if (($row["estado"] ?? "") === "disponible"): ?>
                      <span class="badge text-bg-success">disponible</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">en_reparación</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><?php echo (int)$row["total_reservas"]; ?></td>
                  <td class="text-center"><?php echo (int)$row["reservas_activas"]; ?></td>
                  <td class="text-end">

                    <a class="btn btn-outline-secondary btn-sm"
                       href="/sgr-it/admin/recurso_detalle.php?id=<?php echo (int)$row["id"]; ?><?php echo ($cat>0 ? "&cat=".$cat : ""); ?>">
                      <i class="bi bi-eye me-1"></i>Ver
                    </a>

                    <form method="post" class="d-inline">
                      <input type="hidden" name="toggle_estado" value="1">
                      <input type="hidden" name="recurso_id" value="<?php echo (int)$row["id"]; ?>">
                      <input type="hidden" name="cat_keep" value="<?php echo $cat; ?>">

                      <?php if (($row["estado"] ?? "") === "disponible"): ?>
                        <button class="btn btn-outline-warning btn-sm" type="submit"
                                onclick="return confirm('¿Poner este recurso en reparación? (dejará de ser reservable)');">
                          <i class="bi bi-tools me-1"></i>Reparación
                        </button>
                      <?php else: ?>
                        <button class="btn btn-outline-success btn-sm" type="submit"
                                onclick="return confirm('¿Marcar como disponible otra vez?');">
                          <i class="bi bi-check2-circle me-1"></i>Disponible
                        </button>
                      <?php endif; ?>
                    </form>

                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
