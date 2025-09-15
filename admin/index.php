<?php
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin</title>
  <style>
    :root { --bg:#fff; --text:#222; --muted:#666; --border:#ddd; --accent:#F48120; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 1.5rem; color: var(--text); background: var(--bg); }
    header { display:flex; align-items:center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: 20px 0; }
    .brand { display:flex; align-items:center; gap:.6rem }
    .brand img.logo { height: 34px; width:auto; display:block; margin-right:25px }
    .brand-title { font-size: 1.2rem; font-weight: 600; }
    .tabs { display: flex; gap: .5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); }
    .tab { padding: .6rem .9rem; border: 1px solid var(--border); border-bottom: none; border-radius: 8px 8px 0 0; background: #fafafa; color: #333; text-decoration: none; }
    .tab.active { background: #fff; color: #000; border-bottom-color: #fff; }
    iframe { width: 100%; height: 80vh; border: 1px solid var(--border); border-radius: 0 8px 8px 8px; background: #fff; }
    .nav { margin-bottom: .75rem; }
    .nav a { color: var(--accent); text-decoration: none; margin-right: .75rem; }
    @media (max-width: 900px) { iframe { height: 75vh; } }
    /* Mobile adjustments */
    @media (max-width: 640px) {
      header { flex-direction: column; align-items: flex-start; gap: .5rem; }
      .brand img.logo { height: 28px; margin-right:16px }
      .brand-title { font-size: 1rem; }
      .tabs { overflow-x: auto; white-space: nowrap; padding-bottom: 6px; }
      .tab { font-size: .95rem; padding: .5rem .7rem; }
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <a href="/admin/"><img src="/images/atex_latam_logo.png" alt="Atex" class="logo" /></a>
      <span class="brand-title">Panel de Administración</span>
    </div>
    <div class="nav">
      <a href="/">← Volver al sitio</a>
    </div>
  </header>
  <div class="tabs">
    <a class="tab" id="tab-products" href="/admin/products.php">Productos</a>
    <a class="tab" id="tab-leads" href="/admin/leads.php">Leads</a>
    <a class="tab" id="tab-availability" href="/admin/availability.php">Disponibilidad</a>
    <a class="tab" id="tab-countries" href="/admin/countries.php">Países</a>
  </div>
</body>
</html>
