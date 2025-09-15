<?php
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$db->migrate();
$pdo = $db->pdo();

// Countries list
$countries = $pdo->query('SELECT nombre FROM paises ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
$defaultPais = $countries ? $countries[0] : 'Paraguay';
$pais = $_GET['pais'] ?? $defaultPais;

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isFetch = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch' || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $body = [];
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
    } else {
        $body = $_POST ?: [];
    }
    $action = $body['action'] ?? '';
    $paisReq = $body['pais'] ?? $pais;

    try {
        // Validate target country exists in paises
        $chk = $pdo->prepare('SELECT 1 FROM paises WHERE nombre = ?');
        $chk->execute([$paisReq]);
        $validPais = (bool)$chk->fetchColumn();
        if (!$validPais) {
            if ($isFetch) { header('Content-Type: application/json', true, 400); echo json_encode(['ok' => false, 'error' => 'País inválido']); exit; }
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?pais=' . urlencode($pais));
            exit;
        }
        if ($action === 'add' || $action === 'remove') {
            $producto_id = (string)($body['producto_id'] ?? '');
            if ($producto_id && $paisReq) {
                if ($action === 'add') {
                    $stmt = $pdo->prepare('INSERT OR IGNORE INTO disponibilidad (pais, producto_id) VALUES (?, ?)');
                    $stmt->execute([$paisReq, $producto_id]);
                } else {
                    $stmt = $pdo->prepare('DELETE FROM disponibilidad WHERE pais = ? AND producto_id = ?');
                    $stmt->execute([$paisReq, $producto_id]);
                }
            }
            if ($isFetch) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
        } elseif ($action === 'bulk') {
            $producto_ids = $body['producto_ids'] ?? [];
            $mode = $body['mode'] ?? 'add'; // add|remove
            if (!is_array($producto_ids)) $producto_ids = [];
            $pdo->beginTransaction();
            try {
                if ($mode === 'add') {
                    $stmt = $pdo->prepare('INSERT OR IGNORE INTO disponibilidad (pais, producto_id) VALUES (?, ?)');
                    foreach ($producto_ids as $pid) { $stmt->execute([$paisReq, $pid]); }
                } else {
                    $stmt = $pdo->prepare('DELETE FROM disponibilidad WHERE pais = ? AND producto_id = ?');
                    foreach ($producto_ids as $pid) { $stmt->execute([$paisReq, $pid]); }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            if ($isFetch) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
        }
    } catch (Throwable $e) {
        if ($isFetch) { header('Content-Type: application/json', true, 500); echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit; }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?pais=' . urlencode($paisReq));
    exit;
}

// Fetch availability for selected country
$stmt = $pdo->prepare('SELECT producto_id FROM disponibilidad WHERE pais = ?');
$stmt->execute([$pais]);
$availableIds = array_fill_keys(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'producto_id'), true);

// Fetch all products
$prods = $pdo->query('SELECT id, nombre, altura_mm, direccionalidad, tipo FROM productos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);

// Group by series (base id without -Dxx suffix)
$groups = [];
foreach ($prods as $p) {
    $id = $p['id'];
    $baseId = preg_replace('/-D\d+$/', '', $id);
    $baseName = preg_replace('/\sD\d+$/', '', $p['nombre']);
    if (!isset($groups[$baseId])) {
        $groups[$baseId] = [
            'base_id' => $baseId,
            'base_name' => $baseName,
            'tipo' => $p['tipo'],
            'direccionalidad' => $p['direccionalidad'],
            'variants' => []
        ];
    }
    $variantLabel = ($p['altura_mm'] ? ('D' . (int)round($p['altura_mm'] / 10)) : $p['nombre']);
    $groups[$baseId]['variants'][] = [
        'id' => $id,
        'label' => $variantLabel,
        'altura_mm' => $p['altura_mm'],
        'checked' => isset($availableIds[$id])
    ];
}

// Sort variants by altura
foreach ($groups as &$g) {
    usort($g['variants'], function($a,$b){ return ($a['altura_mm'] <=> $b['altura_mm']); });
}
unset($g);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Disponibilidad por país</title>
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
    select, input[type=text] { padding:.5rem; border:1px solid var(--border); border-radius:6px }
    .series { border:1px solid var(--border); border-radius:8px; margin:.6rem 0; padding:.2rem .6rem .6rem; }
    .series-head { display:flex; align-items:center; justify-content: space-between; gap:.75rem; list-style:none; }
    .series-title { font-weight:600; }
    .series-meta { color: var(--muted); font-size:.9em }
    .variants { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.6rem }
    .variant { display:flex; align-items:center; gap:.35rem; padding:.35rem .55rem; border:1px solid #eee; border-radius:999px; }
    .muted { color: var(--muted); }
    .badge { display:inline-block; background:#f7f7f7; border:1px solid #eee; border-radius:999px; padding:.1rem .4rem; font-size:.85em; color:#555 }
    .toast { position:fixed; right:16px; bottom:16px; background:#333; color:#fff; padding:.6rem .8rem; border-radius:6px; opacity:0; pointer-events:none; transition:opacity .2s }
    .caret { display:inline-block; transform: rotate(0deg); transition: transform .2s; margin-right:.4rem; color:#666 }
    details[open] > summary .caret { transform: rotate(90deg); }
    summary { cursor: pointer; }
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
      .filters select, .filters input[type=text], .filters button { width: 100%; }
      .series { padding: .2rem .5rem .5rem; }
      .series-head { flex-direction: column; align-items: flex-start; gap: .35rem; }
      .variants { gap: .35rem; }
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <a href="/admin/"><img src="/images/atex_latam_logo.png?v=1" alt="Atex" class="logo" /></a>
      <span class="brand-title">Admin · Disponibilidad</span>
    </div>
    <button class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="adminNav"><span></span></button>
    <nav class="nav" id="adminNav">
      <a href="/admin/products.php">Productos</a>
      <a href="/admin/availability.php"><strong>Disponibilidad</strong></a>
      <a href="/admin/countries.php">Países</a>
      <a href="/admin/leads.php">Leads</a>
      <a href="/">Inicio</a>
    </nav>
  </header>

  <div class="card">
    <form class="filters" method="get">
      <label>País
        <select name="pais" onchange="this.form.submit()">
          <?php foreach ($countries as $c): ?>
            <option value="<?=h($c)?>" <?= $c===$pais?'selected':'' ?>><?=h($c)?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <span class="muted">Marque/desmarque series completas o alturas específicas para el país seleccionado.</span>
      <button type="button" id="expandAll" class="btn">Expandir todo</button>
      <button type="button" id="collapseAll" class="btn" style="background:#888">Colapsar todo</button>
    </form>

    <?php foreach ($groups as $baseId => $g): 
      $checkedCount = 0; foreach ($g['variants'] as $v) { if ($v['checked']) $checkedCount++; }
      $all = $checkedCount === count($g['variants']);
    ?>
      <details class="series" data-series="<?= h($baseId) ?>" open>
        <summary class="series-head">
          <span class="caret">▶</span>
          <label class="series-title">
            <input type="checkbox" class="series-toggle" <?= $all ? 'checked' : '' ?> data-series="<?= h($baseId) ?>">
            <?= h($g['base_name']) ?>
          </label>
          <div class="series-meta">
            <span class="badge">Tipo: <?= h($g['tipo']) ?></span>
            <span class="badge">Dir: <?= h($g['direccionalidad']) ?></span>
            <span class="badge count-badge"><?= $checkedCount ?>/<?= count($g['variants']) ?> seleccionadas</span>
          </div>
        </summary>
        <div class="variants">
          <?php foreach ($g['variants'] as $v): ?>
            <label class="variant" title="<?= h($v['id']) ?>">
              <input type="checkbox" class="variant-toggle" data-product-id="<?= h($v['id']) ?>" data-series="<?= h($baseId) ?>" <?= $v['checked'] ? 'checked' : '' ?>>
              <span><?= h($v['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>

  <div id="toast" class="toast"></div>
  <script>
    (function(){
      const pais = <?= json_encode($pais) ?>;
      function toast(msg, err){ const t=document.getElementById('toast'); t.textContent=msg; t.style.background=err?'#c0392b':'#333'; t.style.opacity='1'; setTimeout(()=>t.style.opacity='0',1600); }

      // Helpers to refresh series meta and indeterminate state
      function refreshSeriesState(section){
        const toggles = section.querySelectorAll('.variant-toggle');
        const checked = Array.from(toggles).filter(i=>i.checked).length;
        const meta = section.querySelector('.series-meta .count-badge');
        if (meta) meta.textContent = checked + '/' + toggles.length + ' seleccionadas';
        const master = section.querySelector('.series-toggle');
        if (master) {
          master.indeterminate = checked>0 && checked<toggles.length;
          master.checked = checked===toggles.length;
        }
      }

      // Persist open/closed per country
      const storeKey = (pais)=> 'availCollapsed:'+pais;
      function readCollapsed(){ try { return JSON.parse(localStorage.getItem(storeKey(pais))||'[]'); } catch(e){ return []; } }
      function writeCollapsed(list){ localStorage.setItem(storeKey(pais), JSON.stringify(list)); }
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

      // Variant toggle
      document.querySelectorAll('.variant-toggle').forEach(cb => {
        cb.addEventListener('change', async ()=>{
          const productId = cb.dataset.productId;
          const action = cb.checked ? 'add' : 'remove';
          try{
            const res = await fetch(location.pathname + '?pais=' + encodeURIComponent(pais), {
              method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},
              body: JSON.stringify({ action, pais, producto_id: productId })
            });
            if (!res.ok) throw new Error('HTTP '+res.status);
            toast(cb.checked?'Altura agregada':'Altura eliminada');
            const section = cb.closest('.series');
            if (section) refreshSeriesState(section);
          }catch(e){ toast('Error al guardar', true); cb.checked = !cb.checked; }
        });
      });

      // Series toggle
      document.querySelectorAll('.series-toggle').forEach(cb => {
        cb.addEventListener('change', async ()=>{
          const section = cb.closest('.series');
          const boxes = Array.from(section.querySelectorAll('.variant-toggle'));
          const ids = boxes.map(b=>b.dataset.productId);
          const mode = cb.checked ? 'add' : 'remove';
          try{
            const res = await fetch(location.pathname + '?pais=' + encodeURIComponent(pais), {
              method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},
              body: JSON.stringify({ action:'bulk', pais, producto_ids: ids, mode })
            });
            if (!res.ok) throw new Error('HTTP '+res.status);
            boxes.forEach(b=>{ b.checked = cb.checked; });
            cb.indeterminate = false;
            refreshSeriesState(section);
            toast(cb.checked?'Serie habilitada':'Serie deshabilitada');
          }catch(e){ toast('Error al guardar serie', true); cb.checked = !cb.checked; }
        });
      });

      // Expand/Collapse all
      setOpenStateFromStorage();
      attachTogglePersistence();
      document.getElementById('expandAll').addEventListener('click', ()=>{
        document.querySelectorAll('details.series').forEach(d=> d.open = true);
        writeCollapsed([]);
      });
      document.getElementById('collapseAll').addEventListener('click', ()=>{
        const ids = Array.from(document.querySelectorAll('details.series')).map(d=>d.dataset.series);
        document.querySelectorAll('details.series').forEach(d=> d.open = false);
        writeCollapsed(ids);
      });

      // Initial indeterminate states
      document.querySelectorAll('details.series').forEach(section => refreshSeriesState(section));
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
