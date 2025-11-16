<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['DOCENTE']);
Session::start();

$pdo = DB::conn();
$u   = Session::user();

// 1) Parámetros
$idSol = (int)($_GET['id'] ?? 0);
if ($idSol <= 0) { http_response_code(400); exit('ID inválido'); }

$idUsuario = (int)($u['id'] ?? 0);
if ($idUsuario <= 0) { http_response_code(403); exit('Sesión inválida'); }

// 2) Resolver ID_DOCENTE del usuario actual (sin depender de la sesión)
$sqlDoc = $pdo->prepare("
  SELECT D.ID_DOCENTE
  FROM dbo.DOCENTE D
  JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
  WHERE U.ID_USUARIO = :idu
");
$sqlDoc->execute([':idu' => $idUsuario]);
$idDocente = (int)($sqlDoc->fetchColumn() ?: 0);
if ($idDocente <= 0) { http_response_code(403); exit('Docente no encontrado'); }

// 3) Cargar solicitud
$sqlSol = $pdo->prepare("
  SELECT ID_SOLICITUD, ID_DOCENTE, TIPO_DOCUMENTO, ESTADO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$sqlSol->execute([':id' => $idSol]);
$sol = $sqlSol->fetch(PDO::FETCH_ASSOC);
if (!$sol) { http_response_code(404); exit('Solicitud no encontrada'); }

// 4) Validaciones de propiedad y estado
if ((int)$sol['ID_DOCENTE'] !== $idDocente) {
  http_response_code(403); exit('No autorizado');
}
if (($sol['ESTADO'] ?? '') !== 'APROBADA') {
  http_response_code(409); exit('Sólo se puede solicitar corrección para documentos APROBADOS.');
}

// 5) Evitar duplicados (si ya hay corrección abierta)
$chk = $pdo->prepare("
  SELECT 1
  FROM dbo.DOC_CORRECCION
  WHERE ID_SOLICITUD = :id
    AND ESTATUS IN ('ABIERTA','EN_EDICION')
");
$chk->execute([':id' => $idSol]);
if ($chk->fetchColumn()) {
  http_response_code(409); exit('Ya existe una corrección abierta para esta solicitud.');
}

// 6) Render del formulario de motivo
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Solicitar corrección</title>
  <link rel="stylesheet" href="/siged/app/css/styles.css">
</head>
<body class="layout">
  <main class="card" style="max-width:720px;margin:2rem auto">
    <h1>Solicitar corrección · Solicitud #<?= (int)$sol['ID_SOLICITUD'] ?> (<?= htmlspecialchars($sol['TIPO_DOCUMENTO'] ?? '') ?>)</h1>

    <form method="post" action="/siged/public/index.php?action=sol_corr_guardar">
      <input type="hidden" name="id" value="<?= (int)$sol['ID_SOLICITUD'] ?>">
      <label>Motivo (detalle qué hay que corregir)
        <textarea name="motivo" rows="4" required style="width:100%"></textarea>
      </label>
      <div style="margin-top:.75rem">
        <button type="submit">Enviar solicitud</button>
        <a href="/siged/public/index.php?action=sol_mis" style="margin-left:8px">Cancelar</a>
      </div>
    </form>
  </main>
</body>
</html>
