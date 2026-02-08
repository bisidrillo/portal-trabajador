<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

configure_session();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    try {
        $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = :username AND is_active = 1 LIMIT 1');
        $stmt->execute(['username' => $user]);
        $row = $stmt->fetch();

        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $row['username'];
            $_SESSION['user_id'] = (int) $row['id'];
            header('Location: panel.php');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
    } catch (Throwable $e) {
        $error = 'No se pudo validar el acceso. Revisa la conexión a base de datos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso - Portal del trabajador</title>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;600;700&display=swap");

    :root {
      --bg1: #f3f6fb;
      --bg2: #e8eef8;
      --card: #ffffff;
      --text: #0f172a;
      --muted: #6b7280;
      --accent: #2563eb;
      --accent-2: #0ea5e9;
      --border: #e5e7eb;
      --shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
      --radius: 18px;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: "Manrope", sans-serif;
      color: var(--text);
      background: radial-gradient(1200px 600px at 20% -10%, #dbe7ff 0%, transparent 60%),
                  radial-gradient(1200px 600px at 90% -10%, #c7f0ff 0%, transparent 60%),
                  linear-gradient(180deg, var(--bg1), var(--bg2));
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
    }

    .card {
      width: 100%;
      max-width: 420px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 28px;
    }

    .title {
      font-size: 24px;
      margin: 0 0 6px 0;
      letter-spacing: -0.02em;
    }

    .subtitle {
      margin: 0 0 18px 0;
      color: var(--muted);
      font-size: 14px;
    }

    .field {
      margin: 14px 0;
    }

    .field label {
      display: block;
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 6px;
    }

    .field input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: 14px;
      outline: none;
      transition: border .2s, box-shadow .2s;
      background: #f9fafb;
    }

    .field input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
      background: #fff;
    }

    .btn {
      width: 100%;
      border: none;
      padding: 12px 16px;
      border-radius: 12px;
      font-weight: 600;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: white;
      cursor: pointer;
      transition: transform .06s ease, box-shadow .2s ease;
      box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
    }

    .btn:active { transform: translateY(1px); }

    .error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 12px;
    }

    .hint {
      margin-top: 12px;
      color: var(--muted);
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1 class="title">Acceso al portal</h1>
    <p class="subtitle">Solo personal autorizado</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Usuario</label>
        <input name="user" autocomplete="username" required>
      </div>

      <div class="field">
        <label>Contraseña</label>
        <input name="pass" type="password" autocomplete="current-password" required>
      </div>

      <button class="btn" type="submit">Entrar</button>
    </form>

    <div class="hint">Usuarios gestionados desde MariaDB (tabla <code>users</code>).</div>
  </div>
</body>
</html>
