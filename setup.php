<?php
require_once __DIR__ . '/src/Database.php';

$db = new Database();
$db->migrate();
$pdo = $db->pdo();

// Seed usos
$usos = [
    ['Residencial', 2.0],
    ['Hotelero', 2.0],
    ['Oficinas', 2.5],
    ['Comercial/Shopping', 4.0],
    ['Hospitalario', 3.0],
    ['Educativa', 3.0],
    ['Parking', 2.5],
];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO usos (nombre, carga_viva_kN_m2) VALUES (?, ?)');
foreach ($usos as $u) { $stmt->execute($u); }

// Seed ratios
$ratios = [
    // tipo: maciza | casetonada | post ; direccion: uni|bi
    ['maciza','uni',20],
    ['maciza','bi',28],
    ['casetonada','uni',18],
    ['casetonada','bi',24],
    ['post','uni',24],
    ['post','bi',32],
];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO ratios (tipo, direccion, valor) VALUES (?, ?, ?)');
foreach ($ratios as $r) { $stmt->execute($r); }

// Seed paises
$paises = ['Paraguay', 'Argentina', 'Brasil', 'Chile', 'Uruguay'];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO paises (nombre) VALUES (?)');
foreach ($paises as $p) { $stmt->execute([$p]); }

echo "DB creada y datos base sembrados en data/app.db\n";
