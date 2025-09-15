<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/Calculo.php';

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET request - use query parameters
    $producto_id = $_GET['producto_id'] ?? '';
    $ejeX_m = (float)($_GET['ejeX'] ?? 0);
    $ejeY_m = (float)($_GET['ejeY'] ?? 0);
    $uso = $_GET['uso'] ?? '';
    $cargaViva = $_GET['cargaViva'] ?? '';
    $losa_pct = (float)($_GET['losa_pct'] ?? 100);
    $tipo_opcional = $_GET['tipo_opcional'] ?? null;
    $densidad = isset($_GET['densidad_kN_m3']) ? (float)$_GET['densidad_kN_m3'] : null;
} else {
    // POST request - use JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { Response::error('JSON inválido'); exit; }
    
    $producto_id = $input['producto_id'] ?? '';
    $ejeX_m = (float)($input['ejeX_m'] ?? $input['ejeX'] ?? 0);
    $ejeY_m = (float)($input['ejeY_m'] ?? $input['ejeY'] ?? 0);
    $uso = $input['uso'] ?? '';
    $cargaViva = $input['cargaViva'] ?? '';
    $losa_pct = (float)($input['losa_pct'] ?? 100);
    $tipo_opcional = $input['tipo_opcional'] ?? null;
    $densidad = isset($input['densidad_kN_m3']) ? (float)$input['densidad_kN_m3'] : null;
}

if (!$producto_id || !$ejeX_m || !$ejeY_m) {
    Response::error('Faltan parámetros requeridos');
    exit;
}

try {
    $db = new Database();
    $pdo = $db->pdo();

    $p = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $p->execute([$producto_id]);
    $prod = $p->fetch(PDO::FETCH_ASSOC);
    if (!$prod) { Response::error('Producto no encontrado', 404); exit; }

    $mensajes = [];
    $heq = Calculo::calcularHeq($prod['heq_mm'] ? (float)$prod['heq_mm'] : null, (int)$prod['altura_mm'], $prod['familia'], $prod['direccionalidad'], $mensajes);

    // Ratio L/heq según familia/direccion. Si el tipo opcional es 'post', forzamos familia 'post' para ratio.
    $ratio_tipo = ($tipo_opcional === 'post') ? 'post' : $prod['familia'];
    $ratio_stmt = $pdo->prepare('SELECT valor FROM ratios WHERE tipo = :tipo AND direccion = :dir');
    $ratio_stmt->execute([':tipo' => $ratio_tipo, ':dir' => $prod['direccionalidad']]);
    $ratio_row = $ratio_stmt->fetch(PDO::FETCH_ASSOC);
    $ratio_valor = $ratio_row ? (int)$ratio_row['valor'] : 0;
    if (!$ratio_row) { $mensajes[] = 'No se encontró ratio L/heq para el tipo/dirección; se omitió verificación geométrica.'; }

    $heq_req = $ratio_valor ? Calculo::heqRequeridoPorLuces($ejeX_m, $ejeY_m, $ratio_valor, $mensajes) : 0.0;
    $tolerancia = 0.95; // permitir -5%
    $suficiente = $ratio_valor ? ($heq >= $heq_req * $tolerancia) : true;
    if ($ratio_valor) {
        $mensajes[] = 'Heq del producto: ' . round($heq,1) . ' mm';
        $mensajes[] = 'Suficiencia geométrica: ' . ($suficiente ? 'OK' : 'INSUFICIENTE');
    }

    // Get carga viva (live load) from uso or custom value
    $carga_viva_kN_m2 = null;
    if (!empty($uso)) {
        // Get live load from uso table
        $uso_stmt = $pdo->prepare('SELECT carga_viva_kN_m2 FROM usos WHERE nombre = ?');
        $uso_stmt->execute([$uso]);
        $uso_row = $uso_stmt->fetch(PDO::FETCH_ASSOC);
        if ($uso_row) {
            $carga_viva_kN_m2 = (float)$uso_row['carga_viva_kN_m2'];
        }
    } elseif (!empty($cargaViva)) {
        $carga_viva_kN_m2 = (float)$cargaViva;
    }
    
    if ($carga_viva_kN_m2 === null) {
        Response::error('Debe especificar un uso o una carga viva personalizada');
        exit;
    }

    // Intentar usar volumen Atex desde metadata_json con búsqueda mejorada
    $vol_atex_override = null;
    if (!empty($prod['metadata_json'])) {
        $vol_atex_override = buscarVolumenEnMetadata($prod['metadata_json'], (int)$prod['altura_mm']);
    }

    [$vol_maciza, $vol_atex] = Calculo::volumenes($heq, (int)$prod['altura_mm'], $prod['familia'], $prod['direccionalidad'], $vol_atex_override);
    [$acero_maciza, $acero_atex] = Calculo::acero($heq, $prod['familia'], $tipo_opcional ?? $prod['tipo']);

    // Debug helpers: source of volumen Atex and relative factors
    $volumen_fuente = ($vol_atex_override !== null) ? 'json' : 'estimacion';
    $volumen_atex_fraccion = ($vol_maciza > 0) ? ($vol_atex / $vol_maciza) : null; // e.g., 0.30 => 70% ahorro
    $factor_acero_relativo = ($acero_maciza > 0) ? ($acero_atex / $acero_maciza) : null; // e.g., 0.45 => 55% ahorro

    $ahorro_concreto_pct = ($vol_maciza > 0) ? (1.0 - ($vol_atex / $vol_maciza)) * 100.0 : 0.0;
    $ahorro_acero_pct = ($acero_maciza > 0) ? (1.0 - ($acero_atex / $acero_maciza)) * 100.0 : 0.0;

    $estado = Calculo::estado($ahorro_concreto_pct, $ahorro_acero_pct, $suficiente);

    // Calcular área de la losa
    $area_m2 = $ejeX_m * $ejeY_m;
    
    // Obtener inercia real del producto desde metadata
    $inercia_cm4 = buscarInerciaEnMetadata($prod['metadata_json'], (int)$prod['altura_mm']);
    if ($inercia_cm4 === null) {
        // Fallback: usar inercia estimada basada en Heq si no hay datos reales
        // Fórmula inversa: I = (Heq³ × 80) / 12 para obtener inercia aproximada
        $heq_cm = $heq / 10.0; // convertir mm a cm
        $inercia_cm4 = ($heq_cm * $heq_cm * $heq_cm * 80.0) / 12.0;
    }

    Response::json([
        'ok' => true,
        'heq_mm' => round($heq, 1),
        'heq_requerido_mm' => $ratio_valor ? round($heq_req, 1) : null,
        'volumen_maciza_m3_m2' => round($vol_maciza, 4),
        'volumen_atex_m3_m2' => round($vol_atex, 4),
        'ahorro_concreto_pct' => round($ahorro_concreto_pct, 1),
        'acero_maciza_kg_m2' => round($acero_maciza, 1),
        'acero_atex_kg_m2' => round($acero_atex, 1),
        'ahorro_acero_pct' => round($ahorro_acero_pct, 1),
        'estado' => $estado,
        'mensajes' => $mensajes,
        'requiere_anulador_nervio' => (bool)$prod['requiere_anulador_nervio'],
        'inercia_cm4' => round($inercia_cm4, 0),
        'area_m2' => round($area_m2, 2),
        'ejeX_m' => $ejeX_m,
        'ejeY_m' => $ejeY_m,
        // Debug info for frontend verification (can be hidden in UI)
        'volumen_fuente' => $volumen_fuente,
        'volumen_atex_fraccion' => $volumen_atex_fraccion !== null ? round($volumen_atex_fraccion, 3) : null,
        'factor_acero_relativo' => $factor_acero_relativo !== null ? round($factor_acero_relativo, 3) : null,
    ]);
} catch (Throwable $e) {
    Response::error('Error en cálculo', 500, ['detalle' => $e->getMessage()]);
}

function buscarVolumenEnMetadata(string $metadata_json, int $altura_mm): ?float {
    $meta = json_decode($metadata_json, true);
    if (!is_array($meta)) return null;
    
    $altura_cm_target = $altura_mm / 10.0; // convertir mm a cm
    
    // Buscar la tabla de datos (primer array dentro del JSON)
    $table = null;
    foreach ($meta as $k => $v) {
        if (is_array($v)) {
            $table = $v;
            break;
        }
    }
    
    if (!is_array($table)) return null;
    
    // Buscar fila con altura coincidente
    foreach ($table as $row) {
        if (!is_array($row)) continue;
        
        // Buscar campo de altura - usar newlines reales, no literales
        $D_cm = null;
        if (isset($row["Altura \nTotal"])) {
            $D_cm = parseLocaleFloat($row["Altura \nTotal"]);
        }
        
        if ($D_cm !== null && abs($D_cm - $altura_cm_target) < 0.1) {
            // Buscar volumen - usar newlines reales
            if (isset($row["Volume\nde \nConcreto"])) {
                $vol = parseLocaleFloat($row["Volume\nde \nConcreto"]);
                if ($vol !== null && $vol > 0) {
                    return $vol; // m³/m²
                }
            }
        }
    }
    
    return null;
}

function buscarInerciaEnMetadata(string $metadata_json, int $altura_mm): ?float {
    $meta = json_decode($metadata_json, true);
    if (!is_array($meta)) return null;
    
    $altura_cm_target = $altura_mm / 10.0; // convertir mm a cm
    
    // Buscar la tabla de datos (primer array dentro del JSON)
    $table = null;
    foreach ($meta as $k => $v) {
        if (is_array($v)) {
            $table = $v;
            break;
        }
    }
    
    if (!is_array($table)) return null;
    
    // Buscar fila con altura coincidente
    // Primero buscar por "Altura do Molde" (más específico), luego por "Altura Total"
    $candidatos = [];
    
    foreach ($table as $row) {
        if (!is_array($row)) continue;
        
        // Buscar por "Altura do Molde" primero (más específico para el molde real)
        $altura_molde_cm = null;
        if (isset($row["Altura \ndo \nMolde"])) {
            $altura_molde_cm = parseLocaleFloat($row["Altura \ndo \nMolde"]);
        }
        
        // Buscar por "Altura Total" como alternativa
        $altura_total_cm = null;
        if (isset($row["Altura \nTotal"])) {
            $altura_total_cm = parseLocaleFloat($row["Altura \nTotal"]);
        }
        
        // Verificar si alguna altura coincide con el target
        $altura_encontrada = null;
        $prioridad = 0;
        
        if ($altura_molde_cm !== null && abs($altura_molde_cm - $altura_cm_target) < 0.1) {
            $altura_encontrada = $altura_molde_cm;
            $prioridad = 1; // Mayor prioridad para altura del molde
        } elseif ($altura_total_cm !== null && abs($altura_total_cm - $altura_cm_target) < 0.1) {
            $altura_encontrada = $altura_total_cm;
            $prioridad = 2; // Menor prioridad para altura total
        }
        
        if ($altura_encontrada !== null && isset($row["Inércia"])) {
            $inercia = parseLocaleFloat($row["Inércia"]);
            if ($inercia !== null && $inercia > 0) {
                $candidatos[] = [
                    'inercia' => $inercia,
                    'prioridad' => $prioridad,
                    'altura_molde' => $altura_molde_cm,
                    'altura_total' => $altura_total_cm
                ];
            }
        }
    }
    
    // Ordenar candidatos por prioridad (1 = mayor prioridad)
    if (!empty($candidatos)) {
        usort($candidatos, function($a, $b) {
            return $a['prioridad'] - $b['prioridad'];
        });
        
        return $candidatos[0]['inercia']; // Retornar el de mayor prioridad
    }
    
    return null;
}

function parseLocaleFloat($s): ?float {
    if ($s === null || $s === '') return null;
    if (!is_string($s) && !is_numeric($s)) return null;
    $str = trim((string)$s);
    $str = str_replace(["\n", ' '], '', $str);
    $str = str_replace(',', '.', $str);
    if (!preg_match('/^-?\d+(?:\.\d+)?$/', $str)) return null;
    return (float)$str;
}
