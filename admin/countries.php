<?php
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$db->migrate();
$pdo = $db->pdo();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['nombre'] ?? '');
    $isFetch = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch' || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    try {
        if ($action === 'add' && $name !== '') {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO paises (nombre) VALUES (?)');
            $stmt->execute([$name]);
            if ($isFetch) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'nombre' => $name]); exit; }
        } elseif ($action === 'remove' && $name !== '') {
            $stmt = $pdo->prepare('DELETE FROM paises WHERE nombre = ?');
            $stmt->execute([$name]);
            if ($isFetch) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
        } elseif ($action === 'reset') {
            $defaults = [
                'Colombia',
                'Paraguay',
                'Panamá',
                'República Dominicana',
            ];
            $pdo->beginTransaction();
            try {
                $pdo->exec('DELETE FROM paises');
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO paises (nombre) VALUES (?)');
                foreach ($defaults as $c) { $stmt->execute([$c]); }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            if ($isFetch) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'defaults' => $defaults]); exit; }
        } elseif ($action === 'cleanup') {
            // Remove orphan availability entries that reference non-existing countries
            $pdo->exec('DELETE FROM disponibilidad WHERE pais NOT IN (SELECT nombre FROM paises)');
            if ($isFetch) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
        }
    } catch (Throwable $e) {
        if ($isFetch) { header('Content-Type: application/json', true, 500); echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit; }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$countries = $pdo->query('SELECT nombre FROM paises ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Países</title>
  <style>
    :root { --bg:#fff; --text:#222; --muted:#666; --border:#ddd; --accent:#F48120; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 1.5rem; color: var(--text); background: var(--bg); }
    header { display:flex; align-items:center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: 20px 0; }
    .brand { display:flex; align-items:center; gap:.6rem }
    .brand img.logo { height: 34px; width:auto; display:block; margin-right:25px }
    .brand-title { font-size: 1.2rem; font-weight: 600; }
    .nav a { margin-right: .75rem; color: var(--accent); text-decoration: none; }
    .card { border: 1px solid var(--border); border-radius: 10px; padding: 1rem; background: #fff; }
    .row { display:flex; align-items:center; gap:.5rem; margin:.4rem 0; }
    input[type=text] { padding:.5rem; border:1px solid var(--border); border-radius:6px }
    button { padding:.45rem .7rem; border:0; border-radius:6px; background: var(--accent); color:#fff; cursor:pointer; }
    .danger { background:#c0392b }
    .muted { color: var(--muted); }
    ul { list-style: none; padding:0; margin:.5rem 0 0 0 }
    li { display:flex; align-items:center; justify-content: space-between; border-bottom:1px solid var(--border); padding:.45rem 0 }
    .toast { position:fixed; right:16px; bottom:16px; background:#333; color:#fff; padding:.6rem .8rem; border-radius:6px; opacity:0; pointer-events:none; transition:opacity .2s }
    .danger.armed { background:#a93226 }
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
      .row { flex-direction: column; align-items: stretch; gap: .5rem; }
      form.row > * { width: 100%; }
      ul { margin-top: .75rem; }
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <a href="/admin/"><img src="/images/atex_latam_logo.png?v=1" alt="Atex" class="logo" /></a>
      <span class="brand-title">Admin · Países</span>
    </div>
    <button class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="adminNav"><span></span></button>
    <nav class="nav" id="adminNav">
      <a href="/admin/products.php">Productos</a>
      <a href="/admin/availability.php">Disponibilidad</a>
      <a href="/admin/countries.php"><strong>Países</strong></a>
      <a href="/admin/leads.php">Leads</a>
      <a href="/">Inicio</a>
    </nav>
  </header>

  <div class="card">
    <div class="row">
      <form id="addForm" method="post" class="row" onsubmit="return false;">
        <input type="hidden" name="action" value="add" />
        <input type="text" name="nombre" id="nombre" placeholder="Nuevo país (ej: Colombia)" required />
        <button type="submit">Agregar</button>
      </form>
      <form id="resetForm" method="post" class="row" onsubmit="return false;">
        <input type="hidden" name="action" value="reset" />
        <button type="submit" class="danger" title="Reemplaza la lista actual por la lista estándar">Reset a LATAM</button>
      </form>
      <form id="cleanupForm" method="post" class="row" onsubmit="return false;">
        <input type="hidden" name="action" value="cleanup" />
        <button type="submit" title="Elimina disponibilidades de países inexistentes">Limpiar disponibilidades obsoletas</button>
      </form>
    </div>

    <div class="muted" style="margin:.5rem 0">Estos países se muestran en: Paso 1 del wizard, Productos · Disponibilidad.</div>

    <ul id="list">
      <?php foreach ($countries as $c): ?>
        <li data-nombre="<?= h($c) ?>">
          <span><?= h($c) ?></span>
          <form class="frm-remove" method="post" onsubmit="return false;">
            <input type="hidden" name="action" value="remove" />
            <input type="hidden" name="nombre" value="<?= h($c) ?>" />
            <button type="submit" class="danger">Eliminar</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div id="toast" class="toast"></div>
  <script>
    (function(){
      function toast(msg, err){ const t=document.getElementById('toast'); t.textContent=msg; t.style.background=err?'#c0392b':'#333'; t.style.opacity='1'; setTimeout(()=>t.style.opacity='0',1600); }

      const list = document.getElementById('list');
      const addForm = document.getElementById('addForm');
      const resetForm = document.getElementById('resetForm');

      addForm.addEventListener('submit', async () => {
        const fd = new FormData(addForm);
        if (!fd.get('nombre')) return;
        try{
          const res = await fetch(location.pathname, { method:'POST', body: fd, headers:{'X-Requested-With':'fetch'} });
          if (!res.ok) throw new Error('HTTP '+res.status);
          const name = fd.get('nombre');
          const li = document.createElement('li');
          li.dataset.nombre = name;
          li.innerHTML = `<span>${name}</span><form class="frm-remove" method="post" onsubmit="return false;"><input type="hidden" name="action" value="remove" /><input type="hidden" name="nombre" value="${name}" /><button type="submit" class="danger">Eliminar</button></form>`;
          list.appendChild(li);
          addForm.reset();
          toast('País agregado');
        }catch(e){ toast('Error al agregar', true); }
      });

      list.addEventListener('submit', async (ev) => {
        const form = ev.target.closest('.frm-remove');
        if (!form) return;
        ev.preventDefault();
        const name = form.querySelector('input[name="nombre"]').value;
        const btn = form.querySelector('button[type="submit"]');
        const armed = btn?.dataset.confirm === '1';
        if (!armed) {
          if (btn){
            btn.dataset.confirm = '1';
            btn.textContent = 'Confirmar';
            btn.classList.add('armed');
            setTimeout(() => {
              btn.dataset.confirm = '0';
              btn.textContent = 'Eliminar';
              btn.classList.remove('armed');
            }, 2000);
          }
          return; // wait for second click
        }
        try{
          const fd = new FormData(form);
          const res = await fetch(location.pathname, { method:'POST', body: fd, headers:{'X-Requested-With':'fetch'} });
          if (!res.ok) throw new Error('HTTP '+res.status);
          form.closest('li').remove();
          toast('País eliminado');
        }catch(e){ toast('Error al eliminar', true); }
      });

      resetForm.addEventListener('submit', async () => {
        if (!confirm('Reemplazar la lista por: Colombia, Paraguay, Panamá, República Dominicana?')) return;
        try{
          const fd = new FormData(resetForm);
          const res = await fetch(location.pathname, { method:'POST', body: fd, headers:{'X-Requested-With':'fetch'} });
          if (!res.ok) throw new Error('HTTP '+res.status);
          // Reload list
          location.reload();
        }catch(e){ toast('Error al resetear', true); }
      });

      const cleanupForm = document.getElementById('cleanupForm');
      cleanupForm.addEventListener('submit', async () => {
        if (!confirm('Eliminar las disponibilidades de países que ya no existen en la lista?')) return;
        try{
          const fd = new FormData(cleanupForm);
          const res = await fetch(location.pathname, { method:'POST', body: fd, headers:{'X-Requested-With':'fetch'} });
          if (!res.ok) throw new Error('HTTP '+res.status);
          toast('Disponibilidades obsoletas eliminadas');
        }catch(e){ toast('Error al limpiar', true); }
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
