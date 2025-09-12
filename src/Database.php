<?php
class Database {
    private string $path;
    private ?PDO $pdo = null;

    public function __construct(string $path = __DIR__ . '/../data/app.db') {
        $this->path = $path;
    }

    public function pdo(): PDO {
        if ($this->pdo) return $this->pdo;
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dsn = 'sqlite:' . $this->path;
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Enable WAL for concurrency
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        return $this->pdo;
    }

    public function migrate(): void {
        $db = $this->pdo();
        // productos: esquema mínimo deducido. Ajustable por importador.
        $db->exec(
            'CREATE TABLE IF NOT EXISTS productos (
                id TEXT PRIMARY KEY,
                nombre TEXT NOT NULL,
                familia TEXT NOT NULL,               -- maciza | casetonada | post
                tipo TEXT NOT NULL,                  -- convencional | post
                direccionalidad TEXT NOT NULL,       -- uni | bi
                altura_mm INTEGER NOT NULL,
                requiere_anulador_nervio INTEGER NOT NULL DEFAULT 0,
                heq_mm REAL,                         -- opcional si el ZIP trae
                densidad_kN_m3 REAL,                 -- opcional
                metadata_json TEXT                   -- para campos adicionales del ZIP
            )'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS disponibilidad (
                pais TEXT NOT NULL,
                producto_id TEXT NOT NULL,
                PRIMARY KEY (pais, producto_id),
                FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
            )'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS usos (
                nombre TEXT PRIMARY KEY,
                carga_viva_kN_m2 REAL NOT NULL
            )'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS ratios (
                tipo TEXT NOT NULL,
                direccion TEXT NOT NULL,
                valor INTEGER NOT NULL,
                PRIMARY KEY (tipo, direccion)
            )'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS paises (
                nombre TEXT PRIMARY KEY
            )'
        );
    }
}
