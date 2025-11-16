<?php
// Espera $sol = ['id'=>..,'tipo'=>'RED', ...]
$pdo = DB::conn();
$sid = (int)$sol['id'];

$ex = $pdo->prepare("SELECT ASIGNATURA, PROGRAMA_EDUCATIVO, OFICIO_NO, LUGAR, FECHA_OFICIO
                     FROM dbo.DOC_DEP_RECURSO WHERE ID_SOLICITUD=:id");
$ex->execute([':id'=>$sid]);
$pref = $ex->fetch(PDO::FETCH_ASSOC) ?: ['ASIGNATURA'=>'','PROGRAMA_EDUCATIVO'=>'','OFICIO_NO'=>'','LUGAR'=>'','FECHA_OFICIO'=>null];
?>
<div class="card" style="padding:1rem;margin:.75rem 0">
  <h3 style="margin:0 0 .5rem">Datos para la Constancia (Departamento)</h3>
  <form method="post" action="/siged/public/index.php?action=dep_guardar">
    <input type="hidden" name="id" value="<?= (int)$sid ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <label>Asignatura
        <input type="text" name="asignatura" required value="<?= htmlspecialchars($pref['ASIGNATURA']) ?>">
      </label>
      <label>Programa educativo
        <input type="text" name="programa" required value="<?= htmlspecialchars($pref['PROGRAMA_EDUCATIVO']) ?>">
      </label>
      <label>No. de Oficio (opcional)
        <input type="text" name="oficio" value="<?= htmlspecialchars($pref['OFICIO_NO'] ?? '') ?>">
      </label>
      <label>Lugar (opcional)
        <input type="text" name="lugar" value="<?= htmlspecialchars($pref['LUGAR'] ?? '') ?>">
      </label>
      <label>Fecha del oficio (opcional)
        <input type="date" name="f_oficio" value="<?= $pref['FECHA_OFICIO'] ? substr($pref['FECHA_OFICIO'],0,10):'' ?>">
      </label>
    </div>
    <div style="margin-top:.5rem">
      <button>Guardar datos</button>
    </div>
  </form>
</div>
