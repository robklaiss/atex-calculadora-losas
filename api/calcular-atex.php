<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/CalculoAtex.php';

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET request - use query parameters
    $ejeX_m = (float)($_GET['ejeX'] ?? 6.0);
    $ejeY_m = (float)($_GET['ejeY'] ?? 8.0);
    $h_macizo = (float)($_GET['h_macizo'] ?? 32);
    $h_atex = (float)($_GET['h_atex'] ?? 47.5);
    $q_atex = (float)($_GET['q_atex'] ?? 0.225);
    $coef_carga = (float)($_GET['coef_carga'] ?? 1.0);
} else {
    // POST request - use JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { Response::error('JSON inválido'); exit; }
    
    $ejeX_m = (float)($input['ejeX'] ?? $input['ejeX_m'] ?? 6.0);
    $ejeY_m = (float)($input['ejeY'] ?? $input['ejeY_m'] ?? 8.0);
    $h_macizo = (float)($input['h_macizo'] ?? 32);
    $h_atex = (float)($input['h_atex'] ?? 47.5);
    $q_atex = (float)($input['q_atex'] ?? 0.225);
    $coef_carga = (float)($input['coef_carga'] ?? 1.0);
}

if (!$ejeX_m || !$ejeY_m || $ejeX_m <= 0 || $ejeY_m <= 0) {
    Response::error('Faltan parámetros requeridos: ejeX y ejeY deben ser mayores a 0');
    exit;
}

try {
    // Preparar parámetros para el cálculo
    $params = [
        'ejeX' => $ejeX_m,
        'ejeY' => $ejeY_m,
        'h_macizo' => $h_macizo,
        'h_atex' => $h_atex,
        'q_atex' => $q_atex,
        'coef_carga' => $coef_carga
    ];

    // Realizar el cálculo con la nueva fórmula
    $resultado = CalculoAtex::calcular($params);

    // Agregar información adicional para compatibilidad con el frontend
    $resultado['ok'] = true;
    $resultado['volumen_maciza_m3_m2'] = $resultado['hormigon']['macizo'];
    $resultado['volumen_atex_m3_m2'] = $resultado['hormigon']['atex'];
    $resultado['ahorro_concreto_pct'] = $resultado['hormigon']['ahorro_pct'];
    $resultado['acero_maciza_kg_m2'] = $resultado['acero']['macizo'];
    $resultado['acero_atex_kg_m2'] = $resultado['acero']['atex'];
    $resultado['ahorro_acero_pct'] = $resultado['acero']['ahorro_pct'];
    $resultado['ejeX_m'] = $ejeX_m;
    $resultado['ejeY_m'] = $ejeY_m;
    $resultado['area_m2'] = $resultado['dimensiones']['area_losa'];
    
    // Información adicional para el display
    $resultado['tipo_calculo'] = 'atex_vs_macizo';
    $resultado['resumen'] = CalculoAtex::generarResumen($resultado);

    Response::json($resultado);
    
} catch (Throwable $e) {
    Response::error('Error en cálculo Atex vs Macizo', 500, ['detalle' => $e->getMessage()]);
}
