<?php
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/lib/fpdf.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { Response::error('JSON inválido'); exit; }

$candidatos = $input['candidatos'] ?? [];
$m2_totales = isset($input['m2_totales']) ? (float)$input['m2_totales'] : null;

try {
    $pdf = new FPDF('P','mm','A4');
    $pdf->Cell(0, 6, 'Resumen Atex - Candidatos seleccionados');
    $pdf->Ln(4);

    if ($m2_totales) {
        $pdf->Cell(0, 6, 'Superficie total: ' . number_format($m2_totales, 2, ',', '.') . ' m2');
        $pdf->Ln(4);
    }

    foreach ($candidatos as $c) {
        $line = sprintf('- %s | Heq: %s mm | Ahorro Concreto: %s%% | Ahorro Acero: %s%%',
            $c['nombre'] ?? $c['id'] ?? 'producto', $c['heq_mm'] ?? '-', $c['ahorro_concreto_pct'] ?? '-', $c['ahorro_acero_pct'] ?? '-'
        );
        $pdf->Cell(0, 6, $line);
        $pdf->Ln(2);
    }

    $pdf->Output('I', 'resumen.pdf');
} catch (Throwable $e) {
    Response::error('No se pudo generar el PDF', 500, ['detalle' => $e->getMessage()]);
}
