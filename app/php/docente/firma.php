<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$user = (array)(Session::user() ?? []);
$pdo  = DB::conn();

$uId = (int)($user['id'] ?? 0);
$displayName = trim((string)($user['nombre'] ?? 'Docente'));
$firmaUrl = '';

if ($uId > 0) {
  $st = $pdo->prepare("
    SELECT NOMBRE_COMPLETO, RUTA_FIRMA
    FROM [SIGED].[dbo].[USUARIOS]
    WHERE ID_USUARIO = :id
  ");
  $st->execute([':id' => $uId]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $full = trim((string)($row['NOMBRE_COMPLETO'] ?? ''));
    if ($full !== '') { $displayName = $full; }
    $firmaUrl = (string)($row['RUTA_FIRMA'] ?? '');
  }
}

$msg = $_GET['msg'] ?? '';
function msgFirmaDoc(string $m): string {
  return match($m) {
    'firma_ok'     => 'Firma actualizada correctamente.',
    'firma_tipo'   => 'Formato no válido (usa PNG o JPG).',
    'firma_pesada' => 'Archivo demasiado grande (máx. 2 MB).',
    'firma_error'  => 'No se pudo recibir el archivo.',
    default        => ''
  };
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Mi firma</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/siged/app/css/styles.css">
  <style>
    .wrap{max-width:1100px;margin:2rem auto}
    .card{border:1px solid #eee;border-radius:10px;padding:1rem;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.03)}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media (max-width:900px){.grid2{grid-template-columns:1fr}}
    .dash{border:1px dashed #ddd;border-radius:8px;padding:10px;text-align:center;min-height:120px;display:flex;align-items:center;justify-content:center}
    .ghost{color:#666}
    .btn{display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #e5e7eb;background:#0b5ed7;color:#fff;text-decoration:none}
    .btn-sec{display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;text-decoration:none}
    .stack{display:flex;gap:8px;flex-wrap:wrap}
  </style>
</head>
<body class="layout">
  <header class="topbar">
    <strong>SIGED</strong>
    <nav>
      <a href="/siged/public/index.php?action=home_docente">Inicio</a>
      <a href="/siged/public/index.php?action=logout">Salir</a>
    </nav>
  </header>

  <main class="wrap">
    <section class="card" style="margin-bottom:16px">
      <h1 style="margin:.25rem 0 0">Mi firma</h1>
      <div class="ghost">Usuario: <?= htmlspecialchars($displayName) ?></div>
      <?php if ($txt = msgFirmaDoc($msg)): ?>
        <div class="card" style="margin-top:10px;background:#f9fafb">
          <strong>Aviso:</strong> <?= htmlspecialchars($txt) ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card grid2">
      <div>
        <h3 style="margin-top:0">Vista previa</h3>
        <div class="dash">
          <?php if ($firmaUrl): ?>
            <img src="<?= htmlspecialchars($firmaUrl) ?>" alt="Firma" style="max-width:100%;max-height:120px;object-fit:contain">
          <?php else: ?>
            <div class="ghost">Sin firma cargada</div>
          <?php endif; ?>
        </div>
        <div class="ghost" style="font-size:12px;margin-top:8px">
          Recomendado: PNG con fondo transparente, aprox. 900×300 px. Peso máx. 2 MB.
        </div>
      </div>
      <div>
        <h3 style="margin-top:0">Actualizar firma</h3>
        <form method="post" action="/siged/public/index.php?action=doc_firma_guardar" enctype="multipart/form-data">
          <input type="file" name="firma" accept="image/png, image/jpeg" required>
          <div class="stack" style="margin-top:8px">
            <button type="submit" class="btn">Guardar firma</button>
            <a class="btn-sec" href="/siged/public/index.php?action=home_docente">Cancelar</a>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
