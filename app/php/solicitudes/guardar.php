<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

try {
  // 1) Datos del POST
  $tipoDoc = strtoupper(trim((string)($_POST['tipo'] ?? '')));
  $idConv  = (int)($_POST['convocatoria_id'] ?? 0);

  if ($tipoDoc === '') { throw new RuntimeException('Selecciona un tipo de documento.'); }

  // 2) Si no vino convocatoria, detectar una activa (ventana o la más reciente)
  if ($idConv <= 0) {
    $q = $pdo->query("
      SELECT TOP 1 ID_CONVOCATORIA
      FROM dbo.CONVOCATORIA
      WHERE ACTIVO = 1
        AND (GETDATE() BETWEEN FECHA_INICIO AND DATEADD(DAY, 1, FECHA_FIN))
      ORDER BY FECHA_INICIO DESC
    ");
    $idConv = (int)($q->fetchColumn() ?: 0);

    if ($idConv <= 0) {
      $q = $pdo->query("
        SELECT TOP 1 ID_CONVOCATORIA
        FROM dbo.CONVOCATORIA
        WHERE ACTIVO = 1
        ORDER BY ANIO DESC, ID_CONVOCATORIA DESC
      ");
      $idConv = (int)($q->fetchColumn() ?: 0);
    }
  }
  if ($idConv <= 0) { throw new RuntimeException('No hay convocatoria activa.'); }

  // 3) Docente del usuario y su departamento
  $stD = $pdo->prepare("
    SELECT D.ID_DOCENTE, U.ID_DEPARTAMENTO
    FROM dbo.DOCENTE D
    JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
    WHERE U.ID_USUARIO = :uid
  ");
  $stD->execute([':uid' => $uid]);
  $doc = $stD->fetch(PDO::FETCH_ASSOC);
  if (!$doc) { throw new RuntimeException('No se encontró el docente ligado a tu usuario.'); }

  $idDocente      = (int)$doc['ID_DOCENTE'];
  $idDeptoDocente = (int)$doc['ID_DEPARTAMENTO'];

  // 4) Aprobador por tipo desde PLANTILLA_DOC (si existe)
  $idAprob = null;
  $meta = $pdo->prepare("
    SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
    FROM dbo.PLANTILLA_DOC
    WHERE TIPO_DOCUMENTO = :t AND ACTIVO = 1
    ORDER BY ID_PLANTILLA DESC
  ");
  $meta->execute([':t' => $tipoDoc]);
  $tmpAprob = (int)($meta->fetchColumn() ?: 0);
  if ($tmpAprob > 0) { $idAprob = $tmpAprob; }

  // 5) Insertar solicitud (BORRADOR) evitando NULL en ID_CONVOCATORIA
  $ins = $pdo->prepare("
    INSERT INTO dbo.SOLICITUD_DOCUMENTO
      (ID_DOCENTE, ID_DEPARTAMENTO, TIPO_DOCUMENTO,
       ESTADO, FECHA_CREACION,
       ID_DEPARTAMENTO_APROBADOR, ID_CONVOCATORIA)
    VALUES
      (:idDoc, :idDep, :tipo,
       'BORRADOR', GETDATE(),
       :idAprob, :idConv)
  ");
  $ins->execute([
    ':idDoc'   => $idDocente,
    ':idDep'   => $idDeptoDocente,
    ':tipo'    => $tipoDoc,
    ':idAprob' => $idAprob,     // puede ir NULL
    ':idConv'  => $idConv       // obligatorio (NOT NULL en BD)
  ]);

  // 6) Redirigir a Mis solicitudes
  header('Location: /siged/public/index.php?action=sol_mis');
  exit;

} catch (Throwable $e) {
  http_response_code(400);
  echo "<h2>Error al guardar</h2>";
  echo "<pre>".htmlspecialchars($e->getMessage())."</pre>";
  echo '<p><a href="/siged/public/index.php?action=sol_nueva">Regresar</a></p>';
}
