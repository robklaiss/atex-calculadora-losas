<?php
// api/lead.php — stores contact form submissions and wizard payloads into SQLite

declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../src/Database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
        exit;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    $asJson = stripos($contentType, 'application/json') !== false;

    $body = [];
    if ($asJson && $raw) {
        $body = json_decode($raw, true) ?: [];
    } else {
        $body = $_POST ?: [];
    }

    // Basic fields from the visible form
    $nombre   = trim((string)($body['nombre']   ?? '')) ?: null;
    $apellido = trim((string)($body['apellido'] ?? '')) ?: null;
    $empresa  = trim((string)($body['empresa']  ?? '')) ?: null;
    $email    = trim((string)($body['email']    ?? '')) ?: null;
    $telefono = trim((string)($body['telefono'] ?? '')) ?: null;
    $mensaje  = trim((string)($body['mensaje']  ?? '')) ?: null;
    $producto = trim((string)($body['producto'] ?? '')) ?: null; // UI convenience string

    // Full payload can come as a JSON object (body['payload']) or a JSON string
    $payload = $body['payload'] ?? ($body['contactPayload'] ?? null);
    if (is_string($payload)) {
        $payload = json_decode($payload, true);
    }

    if (!is_array($payload)) {
        $payload = [];
    }

    $wizard      = $payload['wizard']      ?? [];
    $productoObj = $payload['producto']    ?? [];
    $comparacion = $payload['comparacion'] ?? null;

    // Derived fields
    $producto_id     = $productoObj['id']            ?? null;
    $pais            = $wizard['pais']               ?? null;
    $uso             = $wizard['uso']                ?? null;
    $direccionalidad = $wizard['direccionalidad']    ?? null;
    $tipo            = $wizard['tipo']               ?? null;
    $ejeX            = isset($wizard['ejeX']) ? (float)$wizard['ejeX'] : null;
    $ejeY            = isset($wizard['ejeY']) ? (float)$wizard['ejeY'] : null;
    $losa_pct        = isset($wizard['losa_pct']) ? (float)$wizard['losa_pct'] : null;

    $db = new Database();
    $db->migrate(); // ensure leads table exists
    $pdo = $db->pdo();

    $stmt = $pdo->prepare(
        'INSERT INTO leads (
            created_at, nombre, apellido, empresa, email, telefono, mensaje,
            producto, producto_id, pais, uso, direccionalidad, tipo, ejeX, ejeY, losa_pct,
            wizard_json, producto_json, comparacion_json, payload_json
        ) VALUES (
            :created_at, :nombre, :apellido, :empresa, :email, :telefono, :mensaje,
            :producto, :producto_id, :pais, :uso, :direccionalidad, :tipo, :ejeX, :ejeY, :losa_pct,
            :wizard_json, :producto_json, :comparacion_json, :payload_json
        )'
    );

    // Store timestamps in UTC (ISO8601)
    $createdAt = gmdate('c');

    $stmt->execute([
        ':created_at'       => $createdAt,
        ':nombre'           => $nombre,
        ':apellido'         => $apellido,
        ':empresa'          => $empresa,
        ':email'            => $email,
        ':telefono'         => $telefono,
        ':mensaje'          => $mensaje,
        ':producto'         => $producto,
        ':producto_id'      => $producto_id,
        ':pais'             => $pais,
        ':uso'              => $uso,
        ':direccionalidad'  => $direccionalidad,
        ':tipo'             => $tipo,
        ':ejeX'             => $ejeX,
        ':ejeY'             => $ejeY,
        ':losa_pct'         => $losa_pct,
        ':wizard_json'      => json_encode($wizard, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':producto_json'    => json_encode($productoObj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':comparacion_json' => $comparacion ? json_encode($comparacion, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        ':payload_json'     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    ]);

    $id = (int)$pdo->lastInsertId();

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ]);
}
