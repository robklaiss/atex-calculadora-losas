<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';

try {
    $db = new Database();
    $pdo = $db->pdo();

    $usos = $pdo->query('SELECT nombre, carga_viva_kN_m2 FROM usos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    $ratios = $pdo->query('SELECT tipo, direccion, valor FROM ratios ORDER BY tipo, direccion')->fetchAll(PDO::FETCH_ASSOC);
    $paises = $pdo->query('SELECT nombre FROM paises ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);

    Response::json([
        'ok' => true,
        'usos' => $usos,
        'ratios' => $ratios,
        'paises' => $paises,
    ]);
} catch (Throwable $e) {
    Response::error('No se pudo obtener la configuración', 500, ['detalle' => $e->getMessage()]);
}
