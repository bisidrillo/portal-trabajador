<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
if (empty($_SESSION["is_admin"])) {
    header("Location: panel.php");
    exit;
}

$invite_error = "";
$invite_success = "";
$invite_codes = [];

try {
    $pdo = require __DIR__ . "/db.php";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = $_POST["action"] ?? "";
        if ($action === "create_invite") {
            $code = trim($_POST["code"] ?? "");
            $max_uses = (int)($_POST["max_uses"] ?? 1);
            if ($code === "") {
                $code = strtoupper(bin2hex(random_bytes(4)));
            }
            if ($max_uses < 1) {
                $max_uses = 1;
            }
            $stmt = $pdo->prepare("INSERT INTO invite_codes (code, max_uses) VALUES (?, ?)");
            $stmt->execute([$code, $max_uses]);
            $invite_success = "Código creado: " . $code;
        } elseif ($action === "toggle_invite") {
            $id = (int)($_POST["id"] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE invite_codes SET active = IF(active=1,0,1) WHERE id = ?");
                $stmt->execute([$id]);
                $invite_success = "Código actualizado.";
            }
        }
    }

    $stmt = $pdo->query("SELECT id, code, active, max_uses, used_count, created_at FROM invite_codes ORDER BY id DESC LIMIT 50");
    $invite_codes = $stmt->fetchAll();
} catch (Throwable $e) {
    $invite_error = "No se pudo conectar a la base de datos de invitaciones.";
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invitaciones - Portal del trabajador</title>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;600;700&display=swap");

    :root {
      --bg1: #f4f7fc;
      --bg2: #e8eef8;
      --card: #ffffff;
      --text: #0f172a;
      --muted: #6b7280;
      --accent: #2563eb;
      --accent-2: #0ea5e9;
      --border: #e5e7eb;
      --shadow: 0 12px 30px rgba(15, 23, 42, 0.10);
      --radius: 18px;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: "Manrope", sans-serif;
      color: var(--text);
      background: radial-gradient(1200px 600px at 10% -10%, #dbe7ff 0%, transparent 60%),
                  radial-gradient(1200px 600px at 90% -10%, #c7f0ff 0%, transparent 60%),
                  linear-gradient(180deg, var(--bg1), var(--bg2));
      min-height: 100vh;
      padding: 24px;
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
    }

    .title {
      font-size: 24px;
      margin: 0;
      letter-spacing: -0.02em;
    }

    .muted { color: var(--muted); }

    .btn {
      border: none;
      padding: 10px 14px;
      border-radius: 12px;
      font-weight: 600;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: white;
      cursor: pointer;
      box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
    }

    .btn-ghost {
      background: #ffffff;
      color: var(--text);
      border: 1px solid var(--border);
      box-shadow: none;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 20px;
      margin-bottom: 18px;
    }

    .search {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .search input {
      flex: 1;
      min-width: 220px;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #f9fafb;
      outline: none;
      transition: border .2s, box-shadow .2s;
    }

    .table-wrap {
      width: 100%;
      overflow-x: auto;
      border-radius: 12px;
      border: 1px solid var(--border);
    }

    table {
      border-collapse: collapse;
      width: 100%;
      min-width: 720px;
    }

    th, td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      font-size: 14px;
    }

    th { background: #f5f7fb; }

    .error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div>
      <h1 class="title">Invitaciones</h1>
      <div class="muted">Solo administrador</div>
    </div>
    <div style="display:flex; gap:10px;">
      <a class="btn btn-ghost" href="panel.php">Volver al panel</a>
      <a class="btn btn-ghost" href="logout.php">Salir</a>
    </div>
  </div>

  <div class="card">
    <h2 style="margin:0 0 6px 0;">Códigos de invitación</h2>
    <div class="muted" style="margin-bottom:12px;">Crea y administra códigos de registro.</div>

    <?php if ($invite_error): ?>
      <div class="error" style="margin-bottom:12px;"><?= htmlspecialchars($invite_error) ?></div>
    <?php endif; ?>
    <?php if ($invite_success): ?>
      <div class="error" style="background:#dcfce7; color:#166534; border-color:#bbf7d0; margin-bottom:12px;">
        <?= htmlspecialchars($invite_success) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="search" style="margin-bottom:12px;">
      <input type="hidden" name="action" value="create_invite">
      <input name="code" placeholder="Código (opcional)">
      <input name="max_uses" type="number" min="1" value="1" style="max-width:140px;">
      <button class="btn" type="submit">Crear código</button>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Activo</th>
            <th>Usos</th>
            <th>Creado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$invite_codes): ?>
            <tr><td colspan="5">Sin códigos</td></tr>
          <?php else: ?>
            <?php foreach ($invite_codes as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c["code"]) ?></td>
                <td><?= (int)$c["active"] === 1 ? "Sí" : "No" ?></td>
                <td><?= (int)$c["used_count"] ?> / <?= (int)$c["max_uses"] ?></td>
                <td><?= htmlspecialchars($c["created_at"]) ?></td>
                <td>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_invite">
                    <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
                    <button class="btn btn-ghost" type="submit">
                      <?= (int)$c["active"] === 1 ? "Desactivar" : "Activar" ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
