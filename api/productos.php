<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';

$pais = $_GET['pais'] ?? '';
$direccionalidad = $_GET['direccionalidad'] ?? '';
$tipo = $_GET['tipo'] ?? '';

if (!$pais || !$direccionalidad || !$tipo) {
    Response::error('Parámetros requeridos: pais, direccionalidad, tipo');
    exit;
}

try {
    $db = new Database();
    $pdo = $db->pdo();

    // Mapear códigos a nombres si viene en formato corto
    $map = [
        'PY' => 'Paraguay',
        'AR' => 'Argentina',
        'BR' => 'Brasil',
        'CL' => 'Chile',
        'UY' => 'Uruguay',
    ];
    $pais_param = $pais;
    if (isset($map[strtoupper($pais)])) {
        $pais_param = $map[strtoupper($pais)];
    }

    $sql = 'SELECT p.id, p.nombre, p.altura_mm, p.familia, p.direccionalidad, p.requiere_anulador_nervio, p.heq_mm
            FROM productos p
            JOIN disponibilidad d ON d.producto_id = p.id
            WHERE d.pais = :pais AND p.direccionalidad = :dir AND p.tipo = :tipo
            ORDER BY p.nombre';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pais' => $pais_param, ':dir' => $direccionalidad, ':tipo' => $tipo]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::json(['ok' => true, 'items' => $productos]);
} catch (Throwable $e) {
    Response::error('No se pudo obtener productos', 500, ['detalle' => $e->getMessage()]);
}
