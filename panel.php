<?php
session_start();
if (empty($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

$DOCUMENT_ROOTS = require __DIR__ . "/config.php";

function normalize_path(string $path): string {
    $path = str_replace("\\", "/", $path);
    return rtrim($path, "/");
}

function parse_date_str(string $value): ?DateTimeImmutable {
    $value = trim($value);
    if ($value === "") {
        return null;
    }
    $formats = ["d-m-y", "d-m-Y", "Y-m-d", "Ymd", "dmY"];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $value);
        if ($dt && $dt->format($fmt) === $value) {
            return $dt;
        }
    }
    return null;
}

function extract_dates_from_name(string $name): array {
    $dates = [];
    if (preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/', $name, $m)) {
        foreach ($m[1] as $d) {
            $dt = parse_date_str($d);
            if ($dt) { $dates[] = $dt; }
        }
    }
    if (preg_match_all('/\b(\d{2}-\d{2}-\d{4})\b/', $name, $m)) {
        foreach ($m[1] as $d) {
            $dt = parse_date_str($d);
            if ($dt) { $dates[] = $dt; }
        }
    }
    if (preg_match_all('/\b(\d{2}-\d{2}-\d{2})\b/', $name, $m)) {
        foreach ($m[1] as $d) {
            $dt = parse_date_str($d);
            if ($dt) { $dates[] = $dt; }
        }
    }
    return $dates;
}

function split_around_date(string $name): array {
    $base = preg_replace('/\.pdf$/i', '', $name);
    if (preg_match('/\b(\d{2}-\d{2}-\d{2})\b/', $base, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        $len = strlen($m[0][0]);
        return [trim(substr($base, 0, $pos), "_- "), trim(substr($base, $pos + $len), "_- ")];
    }
    if (preg_match('/\b(\d{2}-\d{2}-\d{4})\b/', $base, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        $len = strlen($m[0][0]);
        return [trim(substr($base, 0, $pos), "_- "), trim(substr($base, $pos + $len), "_- ")];
    }
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $base, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        $len = strlen($m[0][0]);
        return [trim(substr($base, 0, $pos), "_- "), trim(substr($base, $pos + $len), "_- ")];
    }
    return ["", ""];
}

function normalize_text(string $value): string {
    if (function_exists("iconv")) {
        $converted = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }
    $value = function_exists("mb_strtolower")
        ? mb_strtolower($value, "UTF-8")
        : strtolower($value);
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/[^a-z0-9 ]+/i', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function text_match(string $haystack, string $needle): bool {
    $needle = normalize_text($needle);
    if ($needle === "") {
        return true;
    }
    $haystack = normalize_text($haystack);
    if ($haystack === "") {
        return false;
    }
    $parts = array_values(array_filter(explode(" ", $needle)));
    foreach ($parts as $part) {
        if (strpos($haystack, $part) === false) {
            return false;
        }
    }
    return true;
}

function extract_person_name(string $name): string {
    $base = preg_replace('/\.pdf$/i', '', $name);
    $parts = explode("_", $base);
    if (!$parts) {
        return trim($base);
    }
    return trim($parts[0]);
}

function parse_contract_metadata(string $name): array {
    $base = trim((string)preg_replace('/\.pdf$/i', '', $name));
    $tokens = array_values(array_filter(array_map("trim", explode("_", $base)), static function (string $t): bool {
        return $t !== "";
    }));

    $meta = [
        "person" => extract_person_name($name),
        "type" => null,
        "department" => null,
        "role" => null,
        "start" => null,
        "end" => null,
        "id" => null,
        "sustituto" => null,
        "sustituido" => null,
    ];

    if (!$tokens) {
        return $meta;
    }

    $type_idx = null;
    foreach ($tokens as $idx => $token) {
        if (preg_match('/^\d{3,4}$/', $token)) {
            $type_idx = $idx;
            break;
        }
    }
    if ($type_idx === null) {
        return $meta;
    }

    $meta["type"] = $tokens[$type_idx];
    $person_tokens = array_slice($tokens, 0, $type_idx);
    if ($person_tokens) {
        $meta["person"] = trim(implode(" ", $person_tokens));
    }

    $tail = array_slice($tokens, $type_idx + 1);
    if (!$tail) {
        return $meta;
    }

    $date_idx = null;
    foreach ($tail as $idx => $token) {
        if (parse_date_str($token)) {
            $date_idx = $idx;
            break;
        }
    }
    if ($date_idx === null) {
        return $meta;
    }

    if (isset($tail[0])) {
        $meta["department"] = $tail[0];
    }
    if ($date_idx > 1) {
        $meta["role"] = trim(implode(" ", array_slice($tail, 1, $date_idx - 1)));
    } elseif (isset($tail[1]) && $date_idx === 1) {
        $meta["role"] = $tail[1];
    }

    $start_dt = parse_date_str($tail[$date_idx]);
    if ($start_dt) {
        $meta["start"] = $start_dt;
    }

    $after_start = array_slice($tail, $date_idx + 1);
    if (!$after_start) {
        return $meta;
    }

    if ($meta["type"] === "410" || $meta["type"] === "510") {
        $meta["sustituto"] = $meta["person"];
        $meta["sustituido"] = trim(implode(" ", $after_start));
        return $meta;
    }

    $end_dt = parse_date_str($after_start[0] ?? "");
    if ($end_dt) {
        $meta["end"] = $end_dt;
        if (count($after_start) > 1) {
            $meta["id"] = trim(implode("_", array_slice($after_start, 1)));
        }
    } else {
        $meta["id"] = trim(implode("_", $after_start));
    }

    return $meta;
}

function person_key(string $name): string {
    return normalize_text($name);
}

function detect_contract_type(string $rel, string $name): ?string {
    $parts = array_filter(explode("/", str_replace("\\", "/", $rel)));
    foreach ($parts as $p) {
        if (preg_match('/^\d{3,4}$/', $p)) {
            return $p;
        }
    }
    if (preg_match('/(?:^|[_-])(\d{3,4})(?=[_-])/u', $name, $m)) {
        return $m[1];
    }
    return null;
}

function detect_section_from_rel(string $rel): array {
    $parts = array_values(array_filter(explode("/", str_replace("\\", "/", $rel))));
    $top = $parts[0] ?? "";
    $top_norm = normalize_text($top);
    if ($top === "") {
        return ["other", "Otros"];
    }
    if (preg_match('/^\d{3,4}$/', $top)) {
        return ["contracts", "Contratos"];
    }
    if ($top_norm === "cambio de contrato") {
        return ["change", "Cambio de contrato"];
    }
    if ($top_norm === "suspencion llamamientos" || $top_norm === "suspension llamamientos") {
        return ["calls", "Suspensiones y llamamientos"];
    }
    return [$top_norm, $top];
}

function find_people(array $roots, string $query, ?string $section, int $limit = 200): array {
    $people = [];
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) {
            continue;
        }
        $root_norm = normalize_path($root);
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
        } catch (Throwable $e) {
            continue;
        }
        foreach ($iterator as $fileinfo) {
            if (!($fileinfo instanceof SplFileInfo) || !$fileinfo->isFile()) {
                continue;
            }
            $name = $fileinfo->getFilename();
            if (!preg_match('/\.pdf$/i', $name)) {
                continue;
            }
            $full = normalize_path($fileinfo->getPathname());
            if (strpos($full, $root_norm) !== 0) {
                continue;
            }
            $rel = ltrim(substr($full, strlen($root_norm)), "/");
            [$section_key] = detect_section_from_rel($rel);
            if ($section && $section_key !== $section) {
                continue;
            }
            $person = extract_person_name($name);
            $meta = parse_contract_metadata($name);
            if (!empty($meta["person"])) {
                $person = (string)$meta["person"];
            }
            if ($person === "") {
                continue;
            }
            if (!text_match($person, $query)) {
                continue;
            }
            $key = person_key($person);
            if (!isset($people[$key])) {
                $people[$key] = [
                    "name" => $person,
                    "count" => 0,
                ];
            }
            $people[$key]["count"]++;
            if (count($people) >= $limit) {
                break 2;
            }
        }
    }
    uasort($people, static function (array $a, array $b): int {
        return strcasecmp($a["name"], $b["name"]);
    });
    return array_values($people);
}

function search_documents(
    array $roots,
    string $query,
    ?string $person,
    ?string $type,
    ?string $section,
    ?DateTimeImmutable $from,
    ?DateTimeImmutable $to,
    ?string $sustituto,
    ?string $sustituido,
    int $limit = 200
): array {
    $results = [];
    foreach ($roots as $label => $root) {
        if (!is_dir($root) || !is_readable($root)) {
            continue;
        }
        $root_norm = normalize_path($root);
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
        } catch (Throwable $e) {
            continue;
        }
        foreach ($iterator as $fileinfo) {
            if (!($fileinfo instanceof SplFileInfo) || !$fileinfo->isFile()) {
                continue;
            }
            $name = $fileinfo->getFilename();
            if (!preg_match('/\.pdf$/i', $name)) {
                continue;
            }
            $meta = parse_contract_metadata($name);
            $person_name = (string)$meta["person"];
            $person_match_key = person_key($person_name);
            if ($person !== null && $person !== "" && $person_match_key !== person_key($person)) {
                continue;
            }
            if ($person === null && $query !== "" && !text_match($person_name . " " . $name, $query)) {
                continue;
            }
            $contract_type = $meta["type"] ?: detect_contract_type($fileinfo->getPathname(), $name);
            if ($type && $contract_type !== $type) {
                continue;
            }

            if ($sustituto || $sustituido) {
                $meta_sustituto = $meta["sustituto"] ?: $person_name;
                $meta_sustituido = $meta["sustituido"] ?: "";
                if ($sustituto && !text_match($meta_sustituto, $sustituto)) {
                    continue;
                }
                if ($sustituido && !text_match($meta_sustituido, $sustituido)) {
                    continue;
                }
            }

            if ($from || $to) {
                $keep = false;
                if ($meta["start"] instanceof DateTimeImmutable && $meta["end"] instanceof DateTimeImmutable) {
                    $start = $meta["start"];
                    $end = $meta["end"];
                    if (($from === null || $end >= $from) && ($to === null || $start <= $to)) {
                        $keep = true;
                    }
                } elseif ($meta["start"] instanceof DateTimeImmutable) {
                    $d = $meta["start"];
                    if (($from === null || $d >= $from) && ($to === null || $d <= $to)) {
                        $keep = true;
                    }
                } else {
                    $dates = extract_dates_from_name($name);
                    if (count($dates) >= 2) {
                        $start = $dates[0];
                        $end = $dates[1];
                        if (($from === null || $end >= $from) && ($to === null || $start <= $to)) {
                            $keep = true;
                        }
                    } elseif (count($dates) === 1) {
                        $d = $dates[0];
                        if (($from === null || $d >= $from) && ($to === null || $d <= $to)) {
                            $keep = true;
                        }
                    }
                }
                if (!$keep) {
                    continue;
                }
            }
            $full = normalize_path($fileinfo->getPathname());
            if (strpos($full, $root_norm) !== 0) {
                continue;
            }
            $rel = ltrim(substr($full, strlen($root_norm)), "/");
            [$section_key, $section_label] = detect_section_from_rel($rel);
            if ($section && $section_key !== $section) {
                continue;
            }
            $results[] = [
                "base" => $label,
                "rel" => $rel,
                "name" => $name,
                "type" => $contract_type,
                "person" => $person_name,
                "section_key" => $section_key,
                "section" => $section_label,
            ];
            if (count($results) >= $limit) {
                break 2;
            }
        }
    }
    return $results;
}

$q = trim($_GET["q"] ?? "");
$person = trim($_GET["person"] ?? "");
$type = trim($_GET["type"] ?? "");
$section = trim($_GET["section"] ?? "");
$date_from = trim($_GET["from"] ?? "");
$date_to = trim($_GET["to"] ?? "");
$sustituto = trim($_GET["sustituto"] ?? "");
$sustituido = trim($_GET["sustituido"] ?? "");
$errors = [];
$results = [];
$people = [];
$limit = 200;
$has_filter = $type !== "" || $section !== "" || $date_from !== "" || $date_to !== "" || $sustituto !== "" || $sustituido !== "";
$available_roots = [];

foreach ($DOCUMENT_ROOTS as $label => $root) {
    if (is_dir($root) && is_readable($root)) {
        $available_roots[$label] = $root;
    }
}

if ($q !== "" || $person !== "" || $has_filter) {
    if (!$available_roots) {
        $errors[] = "No se puede leer la carpeta de contratos desde PHP. Revisa la ruta/permisos en config.php.";
    }
    $len = function_exists("mb_strlen") ? mb_strlen($q) : strlen($q);
    if ($q !== "" && $len < 2 && $person === "") {
        $errors[] = "Escribe al menos 2 caracteres para buscar.";
    }
    $from_dt = parse_date_str($date_from);
    $to_dt = parse_date_str($date_to);
    if (($date_from !== "" && !$from_dt) || ($date_to !== "" && !$to_dt)) {
        $errors[] = "Formato de fecha inválido. Usa DD-MM-AA o DD-MM-AAAA.";
    }
    if (!$errors) {
        if ($person === "" && $q !== "") {
            $people = find_people($available_roots, $q, $section ?: null, $limit);
        } else {
            $results = search_documents(
                $available_roots,
                $q,
                $person !== "" ? $person : null,
                $type ?: null,
                $section ?: null,
                $from_dt,
                $to_dt,
                $sustituto ?: null,
                $sustituido ?: null,
                $limit
            );
        }
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
      padding: 32px 24px 56px;
    }

    .container {
      max-width: 980px;
      margin: 0 auto 18px auto;
    }

    .container.wide {
      max-width: 1260px;
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
  <div class="container">
    <div class="topbar">
      <div>
        <h1 class="title">Panel</h1>
        <div class="muted">Bienvenido, <?= htmlspecialchars($_SESSION["user"]) ?></div>
      </div>
      <div style="display:flex; gap:10px;">
        <?php if (!empty($_SESSION["is_admin"])): ?>
          <a class="btn btn-ghost" href="invites.php">Invitaciones</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="logout.php">Salir</a>
      </div>
    </div>

    <div class="card">
      <h2 style="margin:0 0 6px 0;">Buscar contratos</h2>
      <div class="muted" style="margin-bottom:12px;">Busca por nombre de archivo. Límite: <?= $limit ?> resultados.</div>

      <form method="get" class="search">
        <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre del trabajador">
        <input name="from" value="<?= htmlspecialchars($date_from) ?>" placeholder="Fecha inicio (DD-MM-AA)">
        <input name="to" value="<?= htmlspecialchars($date_to) ?>" placeholder="Fecha fin (DD-MM-AA)">
        <input name="type" value="<?= htmlspecialchars($type) ?>" placeholder="Tipo (389, 402, 410, 502, 510...)">
        <select name="section" style="flex:1; min-width:220px; padding:12px 14px; border:1px solid var(--border); border-radius:12px; background:#f9fafb;">
          <option value="">Sección: todas</option>
          <option value="contracts" <?= $section === "contracts" ? "selected" : "" ?>>Contratos</option>
          <option value="change" <?= $section === "change" ? "selected" : "" ?>>Cambio de contrato</option>
          <option value="calls" <?= $section === "calls" ? "selected" : "" ?>>Suspensiones y llamamientos</option>
        </select>
        <input name="sustituto" value="<?= htmlspecialchars($sustituto) ?>" placeholder="Nombre sustituto">
        <input name="sustituido" value="<?= htmlspecialchars($sustituido) ?>" placeholder="Nombre sustituido">
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
  </div>

  <?php if (($q !== "" || $person !== "" || $has_filter) && !$errors): ?>
    <div class="container wide">
      <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px;">
          <?php if ($person === "" && $q !== ""): ?>
            <div>Personas encontradas: <span class="tag"><?= count($people) ?></span></div>
          <?php elseif ($person !== ""): ?>
            <div>Documentos de <span class="tag"><?= htmlspecialchars($person) ?></span>: <span class="tag"><?= count($results) ?></span></div>
            <a class="btn btn-ghost" href="panel.php?q=<?= rawurlencode($q) ?>&section=<?= rawurlencode($section) ?>">Volver a personas</a>
          <?php else: ?>
            <div>Resultados: <span class="tag"><?= count($results) ?></span></div>
          <?php endif; ?>
        </div>

        <?php if ($person === "" && $q !== ""): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Persona</th>
                  <th>Coincidencias</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$people): ?>
                  <tr><td colspan="3">Sin resultados</td></tr>
                <?php else: ?>
                  <?php foreach ($people as $p): ?>
                    <tr>
                      <td><?= htmlspecialchars($p["name"]) ?></td>
                      <td><?= (int)$p["count"] ?></td>
                      <td><a class="link" href="panel.php?q=<?= rawurlencode($q) ?>&section=<?= rawurlencode($section) ?>&person=<?= rawurlencode($p["name"]) ?>">Ver documentos</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Archivo</th>
                  <th>Persona</th>
                  <th>Sección</th>
                  <th>Origen</th>
                  <th>Ruta relativa</th>
                  <th>PDF</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$results): ?>
                  <tr><td colspan="6">Sin resultados</td></tr>
                <?php else: ?>
                  <?php foreach ($results as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r["name"]) ?></td>
                      <td><?= htmlspecialchars($r["person"]) ?></td>
                      <td><?= htmlspecialchars($r["section"]) ?></td>
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
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</body>
</html>
