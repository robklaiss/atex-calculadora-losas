<?php
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$db->migrate();
$pdo = $db->pdo();

// Filters
$q = trim($_GET['q'] ?? '');
$limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(COALESCE(nombre, "") || " " || COALESCE(apellido, "") || " " || COALESCE(email, "") || " " || COALESCE(telefono, "") || " " || COALESCE(producto, "")) LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$sql = 'SELECT id, created_at, nombre, apellido, empresa, email, telefono, mensaje, producto, producto_id,
               pais, uso, direccionalidad, tipo, ejeX, ejeY, losa_pct,
               wizard_json, producto_json, comparacion_json
        FROM leads';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Local timezone for display
$displayTz = new DateTimeZone('America/Asuncion');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Leads</title>
  <style>
    :root { --bg:#fff; --text:#222; --muted:#666; --border:#ddd; --accent:#F48120; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 1.5rem; color: var(--text); background: var(--bg); }
    header { display:flex; align-items:center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: 20px 0; }
    .brand { display:flex; align-items:center; gap:.6rem }
    .brand img.logo { height: 34px; width:auto; display:block; margin-right:25px }
    .brand-title { font-size: 1.2rem; font-weight: 600; }
    .nav a { margin-right: .75rem; color: var(--accent); text-decoration: none; }
    .card { border: 1px solid var(--border); border-radius: 10px; padding: 1rem; background: #fff; }
    .filters { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin-bottom: .75rem; }
    input[type=text], select { padding: .5rem; border:1px solid var(--border); border-radius: 6px; }
    button, .btn { padding: .45rem .7rem; border:0; border-radius:6px; background: var(--accent); color: #fff; cursor: pointer; }
    table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
    th, td { text-align: left; border-bottom: 1px solid var(--border); padding: .5rem .4rem; vertical-align: top; }
    th { position: sticky; top: 0; background: #fafafa; }
    .muted { color: var(--muted); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
    details { margin:.25rem 0; }
    summary { cursor:pointer; color:#333 }
    /* Hamburger menu */
    .menu-toggle { display:none; appearance:none; background:transparent; border:1px solid var(--border); border-radius:8px; cursor:pointer; width:55px; height:55px; align-items:center; justify-content:center }
    .menu-toggle span { display:block; width:28px; height:3px; background:#333; position:relative; border-radius:2px }
    .menu-toggle span::before, .menu-toggle span::after { content:""; position:absolute; left:0; width:28px; height:3px; background:#333; border-radius:2px }
    .menu-toggle span::before { top:-8px }
    .menu-toggle span::after { top:8px }
    /* Mobile adjustments */
    @media (max-width: 768px) {
      header { flex-direction: row; align-items: center; justify-content: flex-start; flex-wrap: wrap; gap: .5rem; }
      .brand img.logo { height: 28px; margin-right:16px }
      .brand-title { font-size: 1rem; }
      .menu-toggle { display:flex; margin-left:auto }
      .nav { display:none; width: 100%; overflow-x: auto; white-space: nowrap; padding-bottom: 6px; order: 2; }
      .nav.open { display:block }
      .nav a { margin-right: .6rem; font-size: .95rem; }
      .card { padding: .75rem; }
      .filters { flex-direction: column; align-items: stretch; gap: .5rem; }
      .filters input[type=text], .filters select, .filters button { width: 100%; }
      table { display:block; width:100%; overflow-x:auto; -webkit-overflow-scrolling: touch; }
      th, td { white-space: nowrap; }
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <a href="/admin/"><img src="/images/atex_latam_logo.png?v=1" alt="Atex" class="logo" /></a>
      <span class="brand-title">Admin · Leads</span>
    </div>
    <button class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="adminNav"><span></span></button>
    <nav class="nav" id="adminNav">
      <a href="/admin/products.php">Productos</a>
      <a href="/admin/availability.php">Disponibilidad</a>
      <a href="/admin/countries.php">Países</a>
      <a href="/admin/leads.php"><strong>Leads</strong></a>
      <a href="/">Inicio</a>
    </nav>
  </header>

  <div class="card">
    <form class="filters" method="get">
      <input type="text" name="q" placeholder="Buscar nombre, email, teléfono, producto…" value="<?=h($q)?>" />
      <select name="limit">
        <?php foreach ([50,100,200,300,500] as $opt): ?>
          <option value="<?=$opt?>" <?= $limit===$opt?'selected':'' ?>>Mostrar <?=$opt?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Aplicar</button>
      <span class="muted">Total en pantalla: <?= count($rows) ?></span>
    </form>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Contacto</th>
          <th>Producto</th>
          <th>Wizard</th>
          <th>Detalles</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            // Compute local time string from stored ISO8601 (assumed UTC)
            $localStr = $r['created_at'] ?? '';
            try {
                if (!empty($r['created_at'])) {
                    $dtUtc = new DateTimeImmutable($r['created_at'], new DateTimeZone('UTC'));
                    $localStr = $dtUtc->setTimezone($displayTz)->format('Y-m-d H:i');
                }
            } catch (Throwable $e) { /* keep original */ }
          ?>
          <tr>
            <td class="muted">#<?= h($r['id']) ?></td>
            <td>
              <div><?= h($localStr) ?> <span class="muted">(hora local)</span></div>
              <div class="muted mono"><?= h($r['created_at']) ?> UTC</div>
            </td>
            <td>
              <div><strong><?= h(trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? ''))) ?></strong></div>
              <div class="muted"><?= h($r['email'] ?? '') ?></div>
              <div class="muted"><?= h($r['telefono'] ?? '') ?></div>
              <?php if (!empty($r['mensaje'])): ?><div class="mono">"<?= h($r['mensaje']) ?>"</div><?php endif; ?>
            </td>
            <td>
              <div><?= h($r['producto'] ?? '') ?></div>
              <div class="muted mono">ID: <?= h($r['producto_id'] ?? '—') ?></div>
            </td>
            <td>
              <div class="mono">Pais: <?= h($r['pais'] ?? '—') ?> | Uso: <?= h($r['uso'] ?? '—') ?></div>
              <div class="mono">Dir: <?= h($r['direccionalidad'] ?? '—') ?> | Tipo: <?= h($r['tipo'] ?? '—') ?></div>
              <div class="mono">ejeX: <?= h($r['ejeX'] ?? '—') ?> | ejeY: <?= h($r['ejeY'] ?? '—') ?> | losa%: <?= h($r['losa_pct'] ?? '—') ?></div>
            </td>
            <td>
              <details>
                <summary>Ver JSON</summary>
                <div class="mono">wizard_json: <pre class="mono" style="white-space:pre-wrap;"><?= h($r['wizard_json']) ?></pre></div>
                <div class="mono">producto_json: <pre class="mono" style="white-space:pre-wrap;"><?= h($r['producto_json']) ?></pre></div>
                <?php if (!empty($r['comparacion_json'])): ?>
                  <div class="mono">comparacion_json: <pre class="mono" style="white-space:pre-wrap;"><?= h($r['comparacion_json']) ?></pre></div>
                <?php endif; ?>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <script>
    (function(){
      const btn = document.querySelector('.menu-toggle');
      const nav = document.getElementById('adminNav');
      if (!btn || !nav) return;
      btn.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        btn.setAttribute('aria-expanded', String(open));
      });
    })();
  </script>
</body>
</html>
