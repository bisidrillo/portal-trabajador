<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $invite = trim($_POST["invite"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $pass = $_POST["pass"] ?? "";
    $pass2 = $_POST["pass2"] ?? "";

    if ($invite === "") {
        $error = "Código de invitación inválido.";
    } elseif ($username === "" || $email === "" || $full_name === "" || $pass === "") {
        $error = "Completa todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido.";
    } elseif ($pass !== $pass2) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($pass) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } else {
        try {
            $pdo = require __DIR__ . "/db.php";
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, active, max_uses, used_count FROM invite_codes WHERE code = ? LIMIT 1");
            $stmt->execute([$invite]);
            $invite_row = $stmt->fetch();

            if (!$invite_row || (int)$invite_row["active"] !== 1 || (int)$invite_row["used_count"] >= (int)$invite_row["max_uses"]) {
                $pdo->rollBack();
                $error = "Código de invitación inválido.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $pdo->rollBack();
                    $error = "Usuario o email ya existen.";
                } else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $full_name, $hash]);

                    $stmt = $pdo->prepare("UPDATE invite_codes SET used_count = used_count + 1 WHERE id = ?");
                    $stmt->execute([(int)$invite_row["id"]]);

                    $pdo->commit();
                    $success = "Usuario creado. Ya puedes iniciar sesión.";
                }
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error de conexión. Inténtalo más tarde.";
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro - Portal del trabajador</title>
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
      max-width: 520px;
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

    .row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }

    @media (min-width: 640px) {
      .row { grid-template-columns: 1fr 1fr; }
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

    .success {
      background: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 12px;
    }

    .link {
      display: inline-block;
      margin-top: 10px;
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1 class="title">Crear cuenta</h1>
    <p class="subtitle">Registro con código de invitación</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Código de invitación</label>
        <input name="invite" required>
      </div>

      <div class="row">
        <div class="field">
          <label>Usuario</label>
          <input name="username" autocomplete="username" required>
        </div>
        <div class="field">
          <label>Nombre completo</label>
          <input name="full_name" required>
        </div>
      </div>

      <div class="field">
        <label>Email</label>
        <input name="email" type="email" autocomplete="email" required>
      </div>

      <div class="row">
        <div class="field">
          <label>Contraseña</label>
          <input name="pass" type="password" autocomplete="new-password" required>
        </div>
        <div class="field">
          <label>Repite la contraseña</label>
          <input name="pass2" type="password" autocomplete="new-password" required>
        </div>
      </div>

      <button class="btn" type="submit">Crear cuenta</button>
    </form>

    <a class="link" href="login.php">Volver al login</a>
  </div>
</body>
</html>
