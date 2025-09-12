# Atex Calculadora de Losas

Aplicación PHP + SQLite para calcular equivalencia de losas y estimar ahorros de concreto y acero con productos Atex.

## Requisitos
- Apache/Nginx con PHP 8.0+
- Extensión `pdo_sqlite` habilitada

## Estructura
- `data/app.db` — Base de datos SQLite (WAL)
- `src/` — Código backend (DB, cálculos, helpers)
- `api/` — Endpoints
- `admin/` — Utilidades administrativas (importación desde ZIP)
- `public/` — UI (wizard + resultados)

## Pasos rápidos
1) Crear y sembrar la base de datos:

```
php setup.php
```

2) Importar productos y disponibilidad desde el ZIP (ruta local o subir archivo):

- Opción A (ruta local, recomendado en desarrollo):
```
php admin/import.php --zip "pre-produccion/Atex Productos.zip"
```

- Opción B (via navegador): abrir `admin/import.php` y subir el ZIP.

3) Abrir la UI en el navegador:

```
http://localhost/atex-calculadora-losas/public/
```

## Endpoints
- `GET /api/config.php` ⇒ `{ usos, ratios, paises }`
- `GET /api/productos.php?pais=PY&direccionalidad=bi|uni&tipo=convencional|post`
- `POST /api/calcular.php` ⇒ ver cuerpo en `api/calcular.php`
- `POST /api/resumen-pdf.php` ⇒ genera PDF resumen

## Notas
- Donde no se pudo inferir un dato concreto del ZIP/PDF, se usan estimaciones conservadoras y se devuelve un `mensaje` de advertencia.
- Cálculos en `src/Calculo.php` documentan supuestos y puntos a validar con el PDF oficial.
