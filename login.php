<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = trim($_POST["user"] ?? "");
    $pass = trim($_POST["pass"] ?? "");

    // DEMO: cámbialo luego por BD / hash.
    if ($user === "admin" && $pass === "1234") {
        session_regenerate_id(true);
        $_SESSION["user"] = $user;
        header("Location: panel.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
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
  </div>
</body>
</html>
