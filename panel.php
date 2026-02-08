<?php
require __DIR__ . '/auth.php';

require_login();

$DOCUMENT_ROOTS = require __DIR__ . "/config.php";

function normalize_path(string $path): string {
    $path = str_replace("\\", "/", $path);
    return rtrim($path, "/");
}

function search_documents(array $roots, string $query, int $limit = 200): array {
    $results = [];
    foreach ($roots as $label => $root) {
        if (!is_dir($root)) {
            continue;
        }
        $root_norm = normalize_path($root);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }
            $name = $fileinfo->getFilename();
            if (!preg_match('/\.pdf$/i', $name)) {
                continue;
            }
            if (stripos($name, $query) === false) {
                continue;
            }
            $full = normalize_path($fileinfo->getPathname());
            if (strpos($full, $root_norm) !== 0) {
                continue;
            }
            $rel = ltrim(substr($full, strlen($root_norm)), "/");
            $results[] = [
                "base" => $label,
                "rel" => $rel,
                "name" => $name,
            ];
            if (count($results) >= $limit) {
                break 2;
            }
        }
    }
    return $results;
}

$q = trim($_GET["q"] ?? "");
$errors = [];
$results = [];
$limit = 200;

if ($q !== "") {
    $len = function_exists("mb_strlen") ? mb_strlen($q) : strlen($q);
    if ($len < 2) {
        $errors[] = "Escribe al menos 2 caracteres para buscar.";
    } else {
        $results = search_documents($DOCUMENT_ROOTS, $q, $limit);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel - Portal del trabajador</title>
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

    .grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
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

    .search input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
      background: #fff;
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

    .link {
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
    }

    .tag {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 999px;
      background: #eef2ff;
      color: #3730a3;
      font-size: 12px;
      font-weight: 600;
    }

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
      <h1 class="title">Panel</h1>
      <div class="muted">Bienvenido, <?= htmlspecialchars($_SESSION["user"]) ?></div>
    </div>
    <a class="btn btn-ghost" href="logout.php">Salir</a>
  </div>

  <div class="card">
    <h2 style="margin:0 0 6px 0;">Buscar contratos</h2>
    <div class="muted" style="margin-bottom:12px;">Busca por nombre de archivo. Límite: <?= $limit ?> resultados.</div>

    <form method="get" class="search">
      <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre del trabajador" required>
      <button class="btn" type="submit">Buscar</button>
    </form>
  </div>

  <?php if ($errors): ?>
    <div class="card">
      <?php foreach ($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($q !== "" && !$errors): ?>
    <div class="card">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px;">
        <div>Resultados: <span class="tag"><?= count($results) ?></span></div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Archivo</th>
              <th>Origen</th>
              <th>Ruta relativa</th>
              <th>PDF</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$results): ?>
              <tr><td colspan="4">Sin resultados</td></tr>
            <?php else: ?>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r["name"]) ?></td>
                  <td><?= htmlspecialchars($r["base"]) ?></td>
                  <td><?= htmlspecialchars($r["rel"]) ?></td>
                  <td>
                    <a class="link" href="view.php?base=<?= rawurlencode($r["base"]) ?>&file=<?= rawurlencode($r["rel"]) ?>" target="_blank" rel="noopener">Abrir</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</body>
</html>
