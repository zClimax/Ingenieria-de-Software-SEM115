<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php'; Session::start();
require_once __DIR__ . '/../utils/roles.php'; requireRole(['DOCENTE']);

$sol = isset($_GET['sol']) ? (int)$_GET['sol'] : 0; // si viene desde una solicitud
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Crear Ticket | SIGED</title><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:#f8fafc}
.wrap{max-width:900px;margin:24px auto;padding:0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px}
label{display:block;margin-top:10px;font-weight:600}
input[type=text],select,textarea{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
textarea{min-height:120px}
.actions{margin-top:14px;display:flex;gap:10px}
.btn{background:#0b1a52;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
.small{color:#6b7280;font-size:12px}
optgroup[label]{font-weight:700}
</style></head><body>
<div class="wrap"><div class="card">
  <h1>Nuevo Ticket</h1>
  <form action="/SIGED/public/index.php?action=tk_guardar" method="post">
    <?php if ($sol>0): ?>
      <input type="hidden" name="sol" value="<?= (int)$sol ?>">
    <?php endif; ?>

    <label>Título</label>
    <input type="text" name="titulo" required>

    <label>Tipo</label>
    <select name="tipo">
      <option value="DOCUMENTO">Documento</option>
      <option value="FIRMA">Firma</option>
      <option value="PDF">PDF</option>
      <option value="OTRO" selected>Otro</option>
    </select>

    <label>Prioridad</label>
    <select name="prioridad">
      <option value="MEDIA" selected>Media</option>
      <option value="BAJA">Baja</option>
      <option value="ALTA">Alta</option>
    </select>

    <label>Responsable (Jefe de Departamento)</label>
    <select name="responsable" id="responsable" required>
      <option value="">Cargando jefes…</option>
    </select>
    <div class="small" id="sugerencia"></div>

    <label>Descripción</label>
    <textarea name="descripcion" required placeholder="Describe el problema o la solicitud..."></textarea>

    <div class="actions">
      <button class="btn" type="submit">Crear Ticket</button>
      <a class="btn" style="background:#6b7280" href="/SIGED/public/index.php?action=tk_list">Cancelar</a>
    </div>
  </form>
</div></div>

<script>
(async function(){
  const sel = document.getElementById('responsable');
  const sug = document.getElementById('sugerencia');
  const sol = <?= (int)$sol ?>;
  const url = '/SIGED/public/index.php?action=tk_resp_data' + (sol?('&sol='+sol):'');
  const r = await fetch(url,{credentials:'same-origin'});
  const j = await r.json();
  if(!j.ok){ sel.innerHTML='<option value="">No fue posible cargar jefes</option>'; return; }

  // agrupar por departamento
  const byDept = {};
  (j.items||[]).forEach(it=>{
    const depId = it.ID_DEPARTAMENTO || 0;
    const depName = (it.NOMBRE_DEPARTAMENTO||'Sin departamento');
    (byDept[depId] = byDept[depId] || {name:depName, users:[]}).users.push(it);
  });

  function render(filterDeptId=null){
    sel.innerHTML='';
    const entries = Object.entries(byDept)
      .filter(([depId,_]) => filterDeptId===null || Number(depId)===Number(filterDeptId));

    entries.forEach(([depId, group])=>{
      const og = document.createElement('optgroup');
      og.label = `${group.name} (#${depId})`;
      group.users.forEach(u=>{
        const opt = document.createElement('option');
        opt.value = u.ID_USUARIO;
        opt.textContent = `${u.NOMBRE_MOSTRAR} — ${u.CORREO || 'sin correo'}`;
        // marcar recomendado
        if (Number(u.ID_DEPARTAMENTO) === Number(j.depto_sugerido)) opt.dataset.recomendado = '1';
        og.appendChild(opt);
      });
      sel.appendChild(og);
    });

    const rec = sel.querySelector('option[data-recomendado="1"]');
    if (rec){ sel.value = rec.value; }
  }

  // Si viene desde ?sol=ID → filtra a ese departamento (sugerido)
  let filtrado = false;
  if (sol && j.depto_sugerido){ render(j.depto_sugerido); filtrado = true;
    sug.innerHTML = `Sugerido por la solicitud: departamento relacionado. <a href="#" id="verTodos">Ver todos</a>`;
  } else {
    render(null);
    sug.textContent = 'Seleccione el jefe responsable.';
  }

  // Toggle "ver todos"
  document.addEventListener('click', (e)=>{
    if(e.target && e.target.id === 'verTodos'){
      e.preventDefault();
      render(null);
      sug.textContent = 'Seleccione el jefe responsable (lista completa).';
    }
  });
})();
</script>

</body></html>
