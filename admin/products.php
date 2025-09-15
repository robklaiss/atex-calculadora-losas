<?php
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$db->migrate();
$pdo = $db->pdo();

// Handle availability updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $producto_id = $_POST['producto_id'] ?? '';
    $pais = $_POST['pais'] ?? '';
    // If called via fetch (AJAX), respond with JSON to avoid page reload
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch' || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if ($producto_id && $pais && in_array($action, ['add','remove'], true)) {
        if ($action === 'add') {
            // Validate that the country exists in paises
            $chk = $pdo->prepare('SELECT 1 FROM paises WHERE nombre = ?');
            $chk->execute([$pais]);
            $exists = (bool)$chk->fetchColumn();
            if (!$exists) {
                if ($xhr) {
                    header('Content-Type: application/json; charset=UTF-8', true, 400);
                    echo json_encode(['ok' => false, 'error' => 'País inválido']);
                    exit;
                }
                // Fall-through for non-XHR: ignore invalid add
            } else {
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO disponibilidad (pais, producto_id) VALUES (?, ?)');
                $stmt->execute([$pais, $producto_id]);
            }
        } else if ($action === 'remove') {
            $stmt = $pdo->prepare('DELETE FROM disponibilidad WHERE pais = ? AND producto_id = ?');
            $stmt->execute([$pais, $producto_id]);
        }
    }
    if ($xhr) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'action' => $action, 'producto_id' => $producto_id, 'pais' => $pais]);
        exit;
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filters
$q = trim($_GET['q'] ?? '');
$dir = $_GET['dir'] ?? '';
$tipo = $_GET['tipo'] ?? '';

// Build query
$sql = 'SELECT p.*, COALESCE(REPLACE(GROUP_CONCAT(DISTINCT ps.nombre), ",", ", "), "") AS paises
        FROM productos p
        LEFT JOIN disponibilidad d ON d.producto_id = p.id
        LEFT JOIN paises ps ON ps.nombre = d.pais';
$where = [];
$params = [];
if ($q !== '') { $where[] = 'p.nombre LIKE :q'; $params[':q'] = '%' . $q . '%'; }
if ($dir !== '') { $where[] = 'p.direccionalidad = :dir'; $params[':dir'] = $dir; }
if ($tipo !== '') { $where[] = 'p.tipo = :tipo'; $params[':tipo'] = $tipo; }
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' GROUP BY p.id ORDER BY p.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Countries list
$countries = $pdo->query('SELECT nombre FROM paises ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);

// Build series groups (group by base id without -Dxx suffix)
$groups = [];
foreach ($productos as $p) {
    $id = $p['id'];
    $baseId = preg_replace('/-D\d+$/', '', (string)$id);
    // Base name without trailing " Dxx"
    $baseName = preg_replace('/\sD\d+$/', '', (string)$p['nombre']);
    if (!isset($groups[$baseId])) {
        $groups[$baseId] = [
            'base_id' => $baseId,
            'base_name' => $baseName,
            'tipo' => $p['tipo'],
            'direccionalidad' => $p['direccionalidad'],
            'variants' => []
        ];
    }
    $groups[$baseId]['variants'][] = $p;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Productos</title>
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
    .tag { display:inline-flex; align-items:center; gap:.35rem; background:#f7f7f7; border:1px solid #e3e3e3; padding:.15rem .5rem; border-radius:999px; margin:.15rem .25rem 0 0; font-size:.9em }
    .tag.confirm { background:#fdecea; border-color:#f5b7b1; }
    .chip-x { appearance:none; border:0; background:transparent; color:#999; cursor:pointer; padding:0 .1rem; line-height:1; border-radius:4px; font-size:.95em }
    .chip-x:hover { color:#c0392b; }
    .add-tag { background:#fff; border-style:dashed; color:#555 }
    .chip-plus { appearance:none; border:0; background:transparent; color:#F48120; cursor:pointer; font-weight:600; }
    .add-form { display:none; margin-top:.25rem }
    .add-form select { margin-right:.25rem }
    .row-actions { white-space: nowrap; }
    .inline-form { display:inline-block; margin: 0 .25rem; }
    /* Accordions */
    details.series { border:1px solid var(--border); border-radius:8px; margin:.6rem 0; padding:.2rem .6rem .6rem; }
    summary.series-head { display:flex; align-items:center; justify-content: space-between; gap:.75rem; list-style:none; cursor:pointer; }
    .caret { display:inline-block; transform: rotate(0deg); transition: transform .2s; margin-right:.4rem; color:#666 }
    details[open] > summary .caret { transform: rotate(90deg); }
    .series-meta { color: var(--muted); font-size:.9em }
    .badge { display:inline-block; background:#f7f7f7; border:1px solid #eee; border-radius:999px; padding:.1rem .4rem; font-size:.85em; color:#555 }
    .variants-table { width:100%; border-collapse: collapse; margin-top:.6rem }
    .variants-table th, .variants-table td { border-bottom:1px solid var(--border); padding:.4rem .3rem; text-align:left; vertical-align:top }
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
      table, .variants-table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
      th, td { white-space: nowrap; }
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <a href="/admin/"><img src="/images/atex_latam_logo.png?v=1" alt="Atex" class="logo" /></a>
      <span class="brand-title">Admin · Productos</span>
    </div>
    <button class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="adminNav"><span></span></button>
    <nav class="nav" id="adminNav">
      <a href="/admin/products.php"><strong>Productos</strong></a>
      <a href="/admin/availability.php">Disponibilidad</a>
      <a href="/admin/countries.php">Países</a>
      <a href="/admin/leads.php">Leads</a>
      <a href="/">Inicio</a>
    </nav>
  </header>

  <div class="card">
    <form class="filters" method="get">
      <input type="text" name="q" placeholder="Buscar nombre…" value="<?=h($q)?>" />
      <select name="dir">
        <option value="">Direccionalidad: todas</option>
        <option value="bi" <?= $dir==='bi'?'selected':'' ?>>Bidireccional</option>
        <option value="uni" <?= $dir==='uni'?'selected':'' ?>>Unidireccional</option>
      </select>
      <select name="tipo">
        <option value="">Tipo: todos</option>
        <option value="convencional" <?= $tipo==='convencional'?'selected':'' ?>>Convencional</option>
        <option value="post" <?= $tipo==='post'?'selected':'' ?>>Post-tensado</option>
      </select>
      <button type="submit" class="btn">Filtrar</button>
      <span class="muted">Series: <?= count($groups) ?> · Productos: <?= count($productos) ?></span>
      <button type="button" id="expandAll" class="btn">Expandir todo</button>
      <button type="button" id="collapseAll" class="btn" style="background:#888">Colapsar todo</button>
    </form>

    <?php foreach ($groups as $baseId => $g): ?>
      <?php
        // Compute count of variants
        $countVariants = count($g['variants']);
      ?>
      <details class="series" data-series="<?= h($baseId) ?>" open>
        <summary class="series-head">
          <span class="caret">▶</span>
          <div style="display:flex; align-items:center; gap:.6rem;">
            <strong><?= h($g['base_name']) ?></strong>
            <span class="badge">Tipo: <?= h($g['tipo']) ?></span>
            <span class="badge">Dir: <?= h($g['direccionalidad']) ?></span>
            <span class="badge"><?= $countVariants ?> variantes</span>
          </div>
        </summary>
        <table class="variants-table">
          <thead>
            <tr>
              <th class="muted">ID</th>
              <th>Nombre</th>
              <th>Altura (mm)</th>
              <th>Disponible en</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($g['variants'] as $p): ?>
              <?php 
                $paises = array_filter(array_map('trim', explode(',', $p['paises'] ?? '')));
                // Ensure only valid countries from paises table are displayed
                $paises = array_values(array_filter($paises, function($n) use ($countries){ return in_array($n, $countries, true); }));
              ?>
              <tr>
                <td class="muted"><?= h($p['id']) ?></td>
                <td><?= h($p['nombre']) ?></td>
                <td><?= h($p['altura_mm']) ?></td>
                <td>
                  <?php if ($paises): foreach ($paises as $pp): ?>
                    <span class="tag" data-pais="<?= h($pp) ?>" data-producto-id="<?= h($p['id']) ?>">
                      <?= h($pp) ?>
                      <button type="button" class="chip-x" title="Quitar <?= h($pp) ?>" aria-label="Quitar <?= h($pp) ?>">×</button>
                    </span>
                  <?php endforeach; else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                  <?php 
                    $available = array_values(array_diff($countries, $paises));
                    $hasAvail = count($available) > 0;
                  ?>
                  <?php if ($hasAvail): ?>
                    <span class="tag add-tag" data-producto-id="<?= h($p['id']) ?>">
                      <button type="button" class="chip-plus">+ Agregar</button>
                    </span>
                    <form class="inline-form add-form" method="post" data-producto-id="<?= h($p['id']) ?>">
                      <input type="hidden" name="producto_id" value="<?= h($p['id']) ?>" />
                      <select name="pais" class="add-select">
                        <?php foreach ($available as $c): ?>
                          <option value="<?= h($c) ?>"><?= h($c) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="hidden" name="action" value="add" />
                      <button type="button" class="btn btn-add">Agregar</button>
                      <button type="button" class="btn btn-cancel-add" style="background:#aaa">Cancelar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php endforeach; ?>
  </div>
  <div id="toast" style="position:fixed;right:16px;bottom:16px;background:#333;color:#fff;padding:.6rem .8rem;border-radius:6px;opacity:0;pointer-events:none;transition:opacity .2s"></div>
  <script>
    (function(){
      function showToast(msg, isError){
        const t = document.getElementById('toast');
        if (!t) return;
        t.textContent = msg;
        t.style.background = isError ? '#c0392b' : '#333';
        t.style.opacity = '1';
        setTimeout(()=>{ t.style.opacity = '0'; }, 1800);
      }

      // Persist accordion open/closed state across reloads
      const storeKey = 'prodCollapsedSeries';
      function readCollapsed(){ try { return JSON.parse(localStorage.getItem(storeKey) || '[]'); } catch(e){ return []; } }
      function writeCollapsed(list){ localStorage.setItem(storeKey, JSON.stringify(list)); }
      function setOpenStateFromStorage(){
        const collapsed = new Set(readCollapsed());
        document.querySelectorAll('details.series').forEach(d => {
          const id = d.dataset.series;
          d.open = !collapsed.has(id);
        });
      }
      function attachTogglePersistence(){
        document.querySelectorAll('details.series').forEach(d => {
          d.addEventListener('toggle', () => {
            const id = d.dataset.series;
            const list = new Set(readCollapsed());
            if (d.open) list.delete(id); else list.add(id);
            writeCollapsed(Array.from(list));
          });
        });
      }

      // Remove by clicking × on chip, with inline confirm (double-click behavior)
      document.querySelectorAll('.tag .chip-x').forEach(x => {
        x.addEventListener('click', async (e) => {
          const tag = x.closest('.tag');
          const pais = tag?.dataset.pais || '';
          const productoId = tag?.dataset.productoId || '';
          const confirmed = tag?.dataset.confirm === '1';
          if (!confirmed) {
            tag.dataset.confirm = '1';
            tag.classList.add('confirm');
            x.textContent = '×';
            x.title = 'Click de nuevo para confirmar';
            setTimeout(()=>{ // auto-cancel confirm after 2s
              if (tag.dataset.confirm === '1') {
                tag.dataset.confirm = '0';
                tag.classList.remove('confirm');
                x.title = 'Quitar ' + pais;
              }
            }, 2000);
            return;
          }
          try {
            const fd = new FormData();
            fd.append('producto_id', productoId);
            fd.append('pais', pais);
            fd.append('action', 'remove');
            const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            // Remove tag from UI
            const cell = tag.closest('td');
            tag.remove();
            // If "—" placeholder is needed
            if (!cell.querySelector('.tag') && !cell.querySelector('.add-tag')) {
              const dash = document.createElement('span');
              dash.className = 'muted';
              dash.textContent = '—';
              cell.appendChild(dash);
            }
            // Also add the removed country back into the add-select if present
            const addForm = cell.querySelector('.add-form');
            if (addForm) {
              const sel = addForm.querySelector('select.add-select');
              if (sel) {
                const opt = document.createElement('option');
                opt.value = pais; opt.textContent = pais;
                sel.appendChild(opt);
                // Ensure add UI exists
                const addTag = cell.querySelector('.add-tag');
                if (!addTag) {
                  const span = document.createElement('span');
                  span.className = 'tag add-tag';
                  span.innerHTML = '<button type="button" class="chip-plus">+ Agregar</button>';
                  span.dataset.productoId = productoId;
                  cell.appendChild(span);
                }
              }
            }
            showToast('Disponibilidad eliminada');
          } catch (err) {
            showToast('Error al eliminar', true);
          }
        });
      });

      // Toggle add UI
      document.querySelectorAll('.add-tag .chip-plus').forEach(btn => {
        btn.addEventListener('click', () => {
          const cell = btn.closest('td');
          const form = cell.querySelector('.add-form');
          if (form) form.style.display = 'inline-block';
        });
      });

      // Add country via AJAX
      document.querySelectorAll('.add-form .btn-add').forEach(btn => {
        btn.addEventListener('click', async () => {
          const form = btn.closest('.add-form');
          const cell = form.closest('td');
          const sel = form.querySelector('select.add-select');
          const pais = sel?.value || '';
          if (!pais) return;
          try {
            const fd = new FormData(form);
            const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            // Append new chip and remove option from select
            const productoId = form.dataset.productoId || form.querySelector('input[name="producto_id"]').value;
            const span = document.createElement('span');
            span.className = 'tag';
            span.dataset.pais = pais; span.dataset.productoId = productoId;
            span.innerHTML = pais + ' <button type="button" class="chip-x" title="Quitar '+pais+'" aria-label="Quitar '+pais+'">×</button>';
            // Bind removal to new chip
            span.querySelector('.chip-x').addEventListener('click', async (e) => { /* simple re-call: trigger initial setup by reload */ location.reload(); });
            // Remove placeholder dash
            const dash = cell.querySelector('.muted'); if (dash) dash.remove();
            // Insert before the add-tag
            const addTag = cell.querySelector('.add-tag');
            if (addTag) cell.insertBefore(span, addTag);
            else cell.appendChild(span);
            // Remove option
            sel.querySelector('option[value="'+CSS.escape(pais)+'"]').remove();
            if (!sel.querySelector('option')) {
              form.style.display = 'none';
              const addTagBtn = cell.querySelector('.add-tag');
              if (addTagBtn) addTagBtn.remove();
            }
            showToast('País agregado');
          } catch (err) {
            showToast('Error al agregar', true);
          }
        });
      });

      // Cancel add
      document.querySelectorAll('.add-form .btn-cancel-add').forEach(btn => {
        btn.addEventListener('click', () => {
          const form = btn.closest('.add-form');
          form.style.display = 'none';
        });
      });

      // Initialize accordion persistence and controls
      setOpenStateFromStorage();
      attachTogglePersistence();
      const exp = document.getElementById('expandAll');
      const col = document.getElementById('collapseAll');
      if (exp) exp.addEventListener('click', () => {
        document.querySelectorAll('details.series').forEach(d => d.open = true);
        writeCollapsed([]);
      });
      if (col) col.addEventListener('click', () => {
        const ids = Array.from(document.querySelectorAll('details.series')).map(d => d.dataset.series);
        document.querySelectorAll('details.series').forEach(d => d.open = false);
        writeCollapsed(ids);
      });
    })();
  </script>
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
