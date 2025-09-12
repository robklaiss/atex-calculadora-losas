<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';

$cli = php_sapi_name() === 'cli';

function parseZipToData(string $zipPath): array {
    // Espera un ZIP con JSONs de productos y PDFs/PNGs; aquí leemos carpeta `pre-produccion/json/*.json` si existe
    $data = [
        'productos' => [],
        'disponibilidad' => []
    ];

    // Si el ZIP no se procesa, como fallback leer JSONs locales ya presentes
    $jsonDir = __DIR__ . '/../pre-produccion/json';
    if (is_dir($jsonDir)) {
        foreach (glob($jsonDir . '/*.json') as $file) {
            $contentRaw = file_get_contents($file);
            $content = json_decode($contentRaw, true);
            if (!$content || !is_array($content)) continue;

            $filenameBase = pathinfo($file, PATHINFO_FILENAME);
            $baseId = (string)($content['id'] ?? $filenameBase);

            // Inferir direccionalidad desde el nombre del archivo ("U" => uni)
            $direc = ($content['direccionalidad'] ?? null);
            if (!$direc) { $direc = (stripos($filenameBase, 'U') !== false) ? 'uni' : 'bi'; }

            $familia = $content['familia'] ?? 'casetonada';
            $tipo = $content['tipo'] ?? 'convencional';

            // Detectar nombre base (clave de nivel superior con arreglo)
            $nombreBase = $content['nombre'] ?? null;
            if (!$nombreBase) {
                foreach ($content as $maybeName => $val) {
                    if (is_array($val)) { $nombreBase = $maybeName; break; }
                }
            }
            if (!$nombreBase) { $nombreBase = $baseId; }

            // Buscar tabla y crear variantes por cada fila con números
            $table = null; foreach ($content as $k=>$v){ if (is_array($v)) { $table=$v; break; } }
            $rowsCreated = 0;
            if (is_array($table) && count($table) >= 3) {
                for ($i = 2; $i < count($table); $i++) {
                    $row = $table[$i]; if (!is_array($row)) continue;
                    $rowN = normalizeRowKeys($row);
                    $D_cm = parseLocaleNumber(getField($rowN, ['altura total','d']));
                    $heq_cm = parseLocaleNumber(getField($rowN, ['col11','altura equivalente','heq']));
                    if ($D_cm === null && $heq_cm === null) continue; // fila no válida

                    $D_cm_int = ($D_cm !== null) ? (int)round($D_cm) : null;
                    $altura_mm = ($D_cm !== null) ? (int)round($D_cm * 10.0) : (int)($content['altura_mm'] ?? 250);
                    $heq_mm = ($heq_cm !== null) ? ($heq_cm * 10.0) : (isset($content['heq_mm']) ? (float)$content['heq_mm'] : null);

                    // ID y nombre con sufijo Dxx
                    $suffix = $D_cm_int !== null ? ('-D' . $D_cm_int) : '';
                    $id = $baseId . $suffix;
                    $nombre = $nombreBase . ($suffix ? (' ' . substr($suffix,1)) : '');

                    $producto = [
                        'id' => $id,
                        'nombre' => $nombre,
                        'familia' => $familia,
                        'tipo' => $tipo,
                        'direccionalidad' => $direc,
                        'altura_mm' => (int)$altura_mm,
                        'requiere_anulador_nervio' => (int)($content['requiere_anulador_nervio'] ?? 0),
                        'heq_mm' => $heq_mm,
                        'densidad_kN_m3' => isset($content['densidad_kN_m3']) ? (float)$content['densidad_kN_m3'] : null,
                        'metadata_json' => json_encode($content, JSON_UNESCAPED_UNICODE)
                    ];
                    $data['productos'][] = $producto;
                    $rowsCreated++;

                    $paises = $content['paises'] ?? ['Paraguay'];
                    foreach ($paises as $pais) { $data['disponibilidad'][] = [$pais, $id]; }
                }
            }

            // Si no se creó ninguna variante, crear un único producto fallback
            if ($rowsCreated === 0) {
                $altura_mm = isset($content['altura_mm']) ? (int)$content['altura_mm'] : 250;
                $heq_mm = isset($content['heq_mm']) ? (float)$content['heq_mm'] : null;
                $producto = [
                    'id' => $baseId,
                    'nombre' => $nombreBase,
                    'familia' => $familia,
                    'tipo' => $tipo,
                    'direccionalidad' => $direc,
                    'altura_mm' => (int)$altura_mm,
                    'requiere_anulador_nervio' => (int)($content['requiere_anulador_nervio'] ?? 0),
                    'heq_mm' => $heq_mm,
                    'densidad_kN_m3' => isset($content['densidad_kN_m3']) ? (float)$content['densidad_kN_m3'] : null,
                    'metadata_json' => json_encode($content, JSON_UNESCAPED_UNICODE)
                ];
                $data['productos'][] = $producto;
                $paises = $content['paises'] ?? ['Paraguay'];
                foreach ($paises as $pais) { $data['disponibilidad'][] = [$pais, $baseId]; }
            }
        }
    }

    // TODO: Parse real ZIP if necesario
    return $data;
}

function parseLocaleNumber($s): ?float {
    if ($s === null || $s === '') return null;
    if (!is_string($s) && !is_numeric($s)) return null;
    $str = trim((string)$s);
    // Reemplazar coma decimal por punto (formato pt-BR)
    $str = str_replace(["\n", "\r", ' '], '', $str);
    $str = str_replace(',', '.', $str);
    // Debe ser un número
    if (!preg_match('/^-?\d+(?:\.\d+)?$/', $str)) return null;
    return (float)$str;
}

function fieldAny(array $row, array $candidates) {
    foreach ($candidates as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== '') return $row[$k];
    }
    return null;
}

// --- Helpers de mapeo robusto de claves ---
function normalizeKey(string $k): string {
    $k = strtolower($k);
    $k = str_replace(["\n","\r","\t"], ' ', $k);
    $k = preg_replace('/\s+/', ' ', $k);
    // quitar acentos comunes pt/es
    $k = str_replace(['á','é','í','ó','ú','à','è','ì','ò','ù','ã','õ','â','ê','î','ô','û','ç'],
                     ['a','e','i','o','u','a','e','i','o','u','a','o','a','e','i','o','u','c'], $k);
    return trim($k);
}

function normalizeRowKeys(array $row): array {
    $out = [];
    foreach ($row as $k=>$v) { $out[ normalizeKey((string)$k) ] = $v; }
    return $out;
}

function getField(array $rowN, array $candidates) {
    foreach ($candidates as $c) {
        $cN = normalizeKey($c);
        if (array_key_exists($cN, $rowN) && $rowN[$cN] !== '') return $rowN[$cN];
    }
    // Aliases frecuentes
    $aliases = [
        'altura total' => ['d','altura  total'],
        'altura equivalente' => ['heq','altura  equivalente','col11'],
        'volume de concreto' => ['volumen de concreto','volume de concreto','volumedeconcreto','volume de  concreto','volumen\nde \nconcreto','volume\nde \nconcreto']
    ];
    foreach ($candidates as $c) {
        $cN = normalizeKey($c);
        if (isset($aliases[$cN])) {
            foreach ($aliases[$cN] as $alt) {
                $altN = normalizeKey($alt);
                if (array_key_exists($altN, $rowN) && $rowN[$altN] !== '') return $rowN[$altN];
            }
        }
    }
    return null;
}

function importData(Database $db, array $data): void {
    $pdo = $db->pdo();
    $pdo->beginTransaction();
    try {
        $pstmt = $pdo->prepare('INSERT OR REPLACE INTO productos (id, nombre, familia, tipo, direccionalidad, altura_mm, requiere_anulador_nervio, heq_mm, densidad_kN_m3, metadata_json)
            VALUES (:id,:nombre,:familia,:tipo,:direccionalidad,:altura_mm,:requiere,:heq,:densidad,:meta)');
        foreach ($data['productos'] as $p) {
            $pstmt->execute([
                ':id' => $p['id'],
                ':nombre' => $p['nombre'],
                ':familia' => $p['familia'],
                ':tipo' => $p['tipo'],
                ':direccionalidad' => $p['direccionalidad'],
                ':altura_mm' => $p['altura_mm'],
                ':requiere' => $p['requiere_anulador_nervio'],
                ':heq' => $p['heq_mm'],
                ':densidad' => $p['densidad_kN_m3'],
                ':meta' => $p['metadata_json'],
            ]);
        }
        $dstmt = $pdo->prepare('INSERT OR REPLACE INTO disponibilidad (pais, producto_id) VALUES (?, ?)');
        foreach ($data['disponibilidad'] as $d) { $dstmt->execute($d); }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// CLI
if ($cli) {
    $zipArg = null;
    foreach ($argv as $i => $arg) {
        if ($arg === '--zip' && isset($argv[$i+1])) $zipArg = $argv[$i+1];
    }
    $db = new Database();
    $db->migrate();
    $data = parseZipToData($zipArg ?? '');
    importData($db, $data);
    echo "Importación finalizada. Productos: " . count($data['productos']) . "\n";
    exit(0);
}

// HTTP (subida de archivo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip'])) {
    $tmp = $_FILES['zip']['tmp_name'];
    $db = new Database();
    $db->migrate();
    $data = parseZipToData($tmp);
    importData($db, $data);
    Response::json(['ok' => true, 'importados' => count($data['productos'])]);
    exit;
}

// Formulario simple
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Importar productos</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:2rem}
.card{max-width:520px;border:1px solid #ddd;border-radius:8px;padding:1rem}
button{padding:.6rem 1rem;border:0;border-radius:6px;background:#0d6efd;color:#fff}
input[type=file]{margin:.5rem 0}
</style>
</head>
<body>
  <div class="card">
    <h1>Importar productos</h1>
    <p>Puede subir el ZIP o, si existen JSONs en <code>pre-produccion/json/</code>, se usarán como fallback.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="zip" accept=".zip" />
      <div><button type="submit">Importar</button></div>
    </form>
    <p style="color:#666;margin-top:1rem">También puede ejecutar por CLI: <code>php admin/import.php --zip \"pre-produccion/Atex Productos.zip\"</code></p>
  </div>
</body>
</html>
