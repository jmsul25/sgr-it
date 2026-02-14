<?php
session_start();
if (isset($_SESSION["user_id"])) {
    if (($_SESSION["rol"] ?? "") === "admin") {
        header("Location: /sgr-it/admin/dashboard.php");
    } else {
        header("Location: /sgr-it/user/dashboard.php");
    }
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SGR-IT | Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/sgr-it/assets/css/sgr.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center" style="min-height:100vh;">

  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-bold fs-4">SGR-IT</div>
            <span class="badge badge-soft">Intranet</span>
          </div>

          <?php if (isset($_GET["e"])): ?>
            <div class="alert alert-danger mb-3">Credenciales incorrectas.</div>
          <?php endif; ?>

          <form method="post" action="/sgr-it/login_process.php" autocomplete="on">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <input class="form-control" type="password" name="password" required>
            </div>

            <button class="btn btn-primary w-100" type="submit">Entrar</button>
          </form>

          <div class="text-muted small mt-3">
            Panel de gestión de recursos y reservas
          </div>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
