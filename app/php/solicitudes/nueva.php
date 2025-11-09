<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

// 1) Cargar convocatorias activas (primero ventana vigente, luego todas activas)
$st = $pdo->query("
  SELECT ID_CONVOCATORIA, NOMBRE_CONVOCATORIA, ANIO
  FROM dbo.CONVOCATORIA
  WHERE ACTIVO = 1
  ORDER BY ANIO DESC, ID_CONVOCATORIA DESC
");
$convs = $st->fetchAll(PDO::FETCH_ASSOC);

// 2) Cargar tipos de documento disponibles (mapeados a evidencia activa)
$st2 = $pdo->query("
  SELECT DISTINCT M.TIPO_DOCUMENTO, ET.NOMBRE, ET.PUNTAJE
  FROM dbo.EDD_EVIDENCIA_MAP M
  INNER JOIN dbo.EDD_EVIDENCIA_TIPO ET
    ON ET.CODIGO = M.CODIGO_EVIDENCIA AND ET.ACTIVO = 1
  ORDER BY ET.NOMBRE
");
$tipos = $st2->fetchAll(PDO::FETCH_ASSOC);

// Si hay parámetro preseleccionado (?tipo=CNC)
$tipoPre = strtoupper(trim((string)($_GET['tipo'] ?? '')));
$convPre = (int)($_GET['conv'] ?? 0);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Nueva solicitud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/siged/app/css/styles.css">
  <style>
    .wrap{max-width:900px;margin:2rem auto}
    .card{border:1px solid #eee;border-radius:12px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.04)}
    .head{padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9}
    .title{font-size:24px;margin:0}
    .body{padding:1rem 1.25rem}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    label{display:block;font-weight:600;margin:.5rem 0 .25rem}
    select,input[type=text]{width:100%;padding:.6rem .7rem;border:1px solid #e5e7eb;border-radius:10px}
    .actions{display:flex;gap:10px;margin-top:16px}
    .btn{padding:.6rem .9rem;border-radius:10px;border:1px solid #e5e7eb;background:#0b5ed7;color:#fff;text-decoration:none}
    .btn-sec{padding:.6rem .9rem;border-radius:10px;border:1px solid #e5e7eb;background:#f9fafb;text-decoration:none}
    .muted{color:#6b7280;font-size:12px}
    .alert{padding:.8rem 1rem;border-radius:10px;background:#fff7ed;border:1px solid #ffedd5;color:#7c2d12;margin-bottom:12px}
  </style>
</head>
<body class="layout">
  <header class="topbar">
    <strong>SIGED</strong>
    <nav>
      <a href="/siged/public/index.php?action=sol_mis">Mis solicitudes</a>
      <a href="/siged/public/index.php?action=logout">Salir</a>
    </nav>
  </header>

  <main class="wrap">
    <section class="card">
      <div class="head">
        <h1 class="title">Nueva solicitud</h1>
      </div>
      <div class="body">
        <?php if (!$convs): ?>
          <div class="alert">No hay convocatorias activas. Solicita a tu área que active una convocatoria para poder crear solicitudes.</div>
        <?php endif; ?>

        <form method="post" action="/siged/public/index.php?action=sol_guardar">
          <div class="row">
            <div>
              <label for="tipo">Tipo de documento</label>
              <select id="tipo" name="tipo" required>
                <option value="" disabled <?= $tipoPre ? '' : 'selected' ?>>Selecciona…</option>
                <?php foreach ($tipos as $t):
                  $val = strtoupper((string)$t['TIPO_DOCUMENTO']);
                  $nom = (string)$t['NOMBRE'];
                  $pts = (int)$t['PUNTAJE'];
                ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= $tipoPre === $val ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nom) ?> (<?= htmlspecialchars($val) ?><?= $pts>0 ? ' · '.$pts.' pts' : '' ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="muted">Los tipos listados están vinculados a evidencias activas.</div>
            </div>

            <div>
              <label for="conv">Convocatoria</label>
              <?php if (count($convs) <= 1):
                $convId = $convs ? (int)$convs[0]['ID_CONVOCATORIA'] : 0; ?>
                <input type="hidden" id="conv" name="convocatoria_id" value="<?= $convPre ?: $convId ?>">
                <div style="padding:.55rem 0">
                  <?= $convs ? htmlspecialchars($convs[0]['NOMBRE_CONVOCATORIA'].' ('.$convs[0]['ANIO'].')') : '—' ?>
                </div>
              <?php else: ?>
                <select id="conv" name="convocatoria_id" required>
                  <?php foreach ($convs as $c): ?>
                    <option value="<?= (int)$c['ID_CONVOCATORIA'] ?>" <?= $convPre===(int)$c['ID_CONVOCATORIA'] ? 'selected':'' ?>>
                      <?= htmlspecialchars($c['NOMBRE_CONVOCATORIA'].' ('.$c['ANIO'].')') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
              <div class="muted">Se usará para folios, validación y puntaje.</div>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn" <?= $convs ? '' : 'disabled' ?>>Guardar</button>
            <a class="btn-sec" href="/siged/public/index.php?action=sol_mis">Cancelar</a>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
