<?php
session_start();
require __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["rol"] ?? "") !== "empleado") {
    header("Location: /sgr-it/login.php");
    exit;
}

$usuario_id = (int)$_SESSION["user_id"];
$cat = (int)($_GET["cat"] ?? 0);

// Categorías
$categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");

// Recursos (FILTRADOS: solo disponibles) para el modo individual
if ($cat > 0) {
    $stmt = $conn->prepare("
        SELECT id, nombre, tipo, estado
        FROM recursos
        WHERE categoria_id = ?
          AND estado = 'disponible'
        ORDER BY nombre ASC
    ");
    $stmt->bind_param("i", $cat);
    $stmt->execute();
    $recursos = $stmt->get_result();
} else {
    $recursos = $conn->query("
        SELECT id, nombre, tipo, estado
        FROM recursos
        WHERE estado = 'disponible'
        ORDER BY nombre ASC
    ");
}

// Mis reservas
$stmt = $conn->prepare("
  SELECT r.id, r.fecha_inicio, r.fecha_fin,
         rec.nombre AS recurso_nombre, rec.tipo AS recurso_tipo
  FROM reservas r
  JOIN recursos rec ON rec.id = r.recurso_id
  WHERE r.usuario_id = ?
  ORDER BY r.fecha_inicio DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$mis_reservas = $stmt->get_result();

// Métricas empleado
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM reservas WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$my_total = (int)$stmt->get_result()->fetch_assoc()["c"];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM reservas WHERE usuario_id = ? AND fecha_fin >= NOW()");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$my_activas = (int)$stmt->get_result()->fetch_assoc()["c"];

$row = $conn->query("SELECT COUNT(*) AS c FROM recursos WHERE estado='disponible'")->fetch_assoc();
$recursos_disponibles = (int)$row["c"];

function alert_msg(): array {
  if (isset($_GET["ok"])) return ["success","Reserva creada ✅"];
  if (isset($_GET["ok_multi"])) return ["success","Reserva múltiple creada ✅"];
  if (isset($_GET["cancel_ok"])) return ["success","Reserva cancelada ✅"];
  if (!isset($_GET["err"])) return ["",""];
  $err = $_GET["err"];
  if ($err === "solape") return ["danger","Ese recurso ya está reservado en ese rango."];
  if ($err === "fechas") return ["warning","Fechas inválidas (fin debe ser posterior a inicio)."];
  if ($err === "recurso") return ["warning","Recurso no disponible (en reparación) o no existe."];
  if ($err === "cantidad") return ["warning","No hay suficientes recursos libres en esa categoría para ese rango."];
  return ["danger","Error al crear la reserva."];
}
[$atype,$amsg] = alert_msg();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Empleado | SGR-IT</title>
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
    <div class="sgr-brand">Empleado</div>
    <nav class="sgr-nav">
      <a class="active" href="/sgr-it/user/dashboard.php"><i class="bi bi-calendar2-check"></i>Reservas</a>
    </nav>
  </aside>

  <main class="sgr-content">
    <div class="container-fluid p-3 p-lg-4">

      <?php if ($amsg): ?>
        <div class="alert alert-<?php echo $atype; ?> mb-3"><?php echo htmlspecialchars($amsg); ?></div>
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Recursos disponibles</div>
            <div class="sgr-stat-value"><?php echo $recursos_disponibles; ?></div>
            <div class="text-muted small">Listos para reservar</div>
            <div class="sgr-stat-icon"><i class="bi bi-box-seam"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Mis reservas activas</div>
            <div class="sgr-stat-value"><?php echo $my_activas; ?></div>
            <div class="text-muted small">En curso o futuras</div>
            <div class="sgr-stat-icon"><i class="bi bi-calendar-check"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card p-3 sgr-stat">
            <div class="sgr-stat-label">Historial</div>
            <div class="sgr-stat-value"><?php echo $my_total; ?></div>
            <div class="text-muted small">Total creadas</div>
            <div class="sgr-stat-icon"><i class="bi bi-clock-history"></i></div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="card p-3 p-lg-4">
            <h5 class="mb-3"><i class="bi bi-plus-circle me-2"></i>Reservar</h5>

            <form method="post" action="/sgr-it/user/crear_reserva.php">
              <input type="hidden" name="cat_return" value="<?php echo $cat; ?>">

              <div class="mb-3">
                <label class="form-label">Modo de reserva</label>
                <select class="form-select" name="modo" id="modo">
                  <option value="individual">Un recurso</option>
                  <option value="categoria">Por categoría (cantidad)</option>
                </select>
                <div class="text-muted small mt-1">En individual solo se muestran recursos disponibles.</div>
              </div>

              <!-- Filtro para el selector individual -->
              <div class="mb-3" id="bloque_filtro">
                <label class="form-label">Filtrar recursos (solo modo individual)</label>
                <select class="form-select" name="cat" onchange="window.location='?cat='+this.value">
                  <option value="0" <?php echo ($cat===0 ? "selected" : ""); ?>>Todas</option>
                  <?php
                    $cats2 = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
                    while($c2 = $cats2->fetch_assoc()):
                  ?>
                    <option value="<?php echo (int)$c2["id"]; ?>" <?php echo ($cat===(int)$c2["id"] ? "selected" : ""); ?>>
                      <?php echo htmlspecialchars($c2["nombre"]); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <!-- Individual (solo disponibles) -->
              <div class="mb-3" id="bloque_individual">
                <label class="form-label">Recurso</label>
                <select class="form-select" name="recurso_id">
                  <?php if ($recursos->num_rows === 0): ?>
                    <option value="">(No hay recursos disponibles en este filtro)</option>
                  <?php else: ?>
                    <?php while ($r = $recursos->fetch_assoc()): ?>
                      <option value="<?php echo (int)$r["id"]; ?>">
                        <?php echo htmlspecialchars($r["nombre"] . " (" . ($r["tipo"] ?? "") . ")"); ?>
                      </option>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </select>
              </div>

              <!-- Por categoría -->
              <div class="row g-2 mb-3" id="bloque_categoria" style="display:none;">
                <div class="col-12 col-md-7">
                  <label class="form-label">Categoría</label>
                  <select class="form-select" name="categoria_id">
                    <option value="0">— Selecciona —</option>
                    <?php
                      $cats3 = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
                      while($c3 = $cats3->fetch_assoc()):
                    ?>
                      <option value="<?php echo (int)$c3["id"]; ?>">
                        <?php echo htmlspecialchars($c3["nombre"]); ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-12 col-md-5">
                  <label class="form-label">Cantidad</label>
                  <input class="form-control" type="number" name="cantidad" min="2" max="50" value="10">
                </div>
              </div>

              <div class="row g-2">
                <div class="col-12 col-md-6">
                  <label class="form-label">Inicio</label>
                  <input class="form-control" type="datetime-local" name="inicio" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Fin</label>
                  <input class="form-control" type="datetime-local" name="fin" required>
                </div>
              </div>

              <button class="btn btn-primary mt-3" type="submit">
                <i class="bi bi-check2-circle me-1"></i>Crear reserva
              </button>
            </form>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card p-3 p-lg-4">
            <h5 class="mb-3"><i class="bi bi-list-check me-2"></i>Mis reservas</h5>

            <?php if ($mis_reservas->num_rows === 0): ?>
              <div class="text-muted">No tienes reservas todavía.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Recurso</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($row = $mis_reservas->fetch_assoc()): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?php echo htmlspecialchars($row["recurso_nombre"]); ?></div>
                          <div class="text-muted small"><?php echo htmlspecialchars($row["recurso_tipo"] ?? ""); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($row["fecha_inicio"]); ?></td>
                        <td><?php echo htmlspecialchars($row["fecha_fin"]); ?></td>
                        <td class="text-end">
                          <form method="post" action="/sgr-it/user/cancelar_reserva.php" class="d-inline">
                            <input type="hidden" name="reserva_id" value="<?php echo (int)$row["id"]; ?>">
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

<script>
  const modo = document.getElementById("modo");
  const bInd = document.getElementById("bloque_individual");
  const bCat = document.getElementById("bloque_categoria");
  const bFil = document.getElementById("bloque_filtro");

  function toggle() {
    if (modo.value === "categoria") {
      bCat.style.display = "";
      bInd.style.display = "none";
      bFil.style.display = "none";
    } else {
      bCat.style.display = "none";
      bInd.style.display = "";
      bFil.style.display = "";
    }
  }
  modo.addEventListener("change", toggle);
  toggle();
</script>

</body>
</html>
