<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
header('Content-Type: application/json; charset=UTF-8');

try {
  $pdo = DB::conn();

  // ===== SESIÓN ROBUSTA =====
  $u        = (array)(Session::user() ?? []);
  $idJefe   = (int)($u['id'] ?? ($_SESSION['ID_USUARIO'] ?? 0));
  $idDepto  = (int)($u['id_departamento'] ?? ($_SESSION['ID_DEPARTAMENTO'] ?? 0));
  $correo   = (string)($u['correo'] ?? ($_SESSION['CORREO'] ?? ''));
  $usuarioN = (string)($u['usuario'] ?? ($u['NOMBRE_USUARIO'] ?? ($_SESSION['NOMBRE_USUARIO'] ?? '')));

  // Si falta depto/id, intenta recuperarlo desde USUARIOS
  $U     = Config::MAP['USUARIOS'];
  $tu    = $U['TABLE'];
  $uId   = $U['ID'];
  $uDep  = $U['DEP'];
  $uMail = $U['MAIL'] ?? 'CORREO';
  $uUser = $U['USER'];

  if ($idDepto <= 0 && $idJefe > 0) {
    $st = $pdo->prepare("SELECT $uDep FROM $tu WHERE $uId = :id");
    $st->execute([':id' => $idJefe]);
    $idDepto = (int)($st->fetchColumn() ?: 0);
  }
  if (($idJefe <= 0 || $idDepto <= 0) && $correo !== '') {
    $st = $pdo->prepare("SELECT TOP 1 $uId AS id_usr, $uDep AS id_dep FROM $tu WHERE LOWER(LTRIM(RTRIM($uMail))) = LOWER(LTRIM(RTRIM(:m)))");
    $st->execute([':m' => $correo]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) { $idJefe = $idJefe ?: (int)$row['id_usr']; $idDepto = $idDepto ?: (int)$row['id_dep']; }
  }
  if (($idJefe <= 0 || $idDepto <= 0) && $usuarioN !== '') {
    $st = $pdo->prepare("SELECT TOP 1 $uId AS id_usr, $uDep AS id_dep FROM $tu WHERE $uUser = :u");
    $st->execute([':u' => $usuarioN]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) { $idJefe = $idJefe ?: (int)$row['id_usr']; $idDepto = $idDepto ?: (int)$row['id_dep']; }
  }

  if ($idJefe <= 0 || $idDepto <= 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesión inválida (sin id o departamento).'], JSON_UNESCAPED_UNICODE); exit;
  }

  // ===== REGLAS =====
  $EST_APROBADA  = 'APROBADA';
  $EST_RECHAZADA = 'RECHAZADA';

  // Convocatoria ACTIVA (0 = sin filtro)
  $idConv = (int)($pdo->query("
    SELECT TOP 1 ID_CONVOCATORIA
    FROM [SIGED].[dbo].[CONVOCATORIA]
    WHERE ACTIVO = 1
    ORDER BY FECHA_INICIO DESC
  ")->fetchColumn() ?: 0);

  // Filtro dinámico por convocatoria (evita :id_conv duplicado)
  $convFilter = $idConv > 0 ? " AND ID_CONVOCATORIA = :id_conv " : "";
  $baseParams = [':id_dep' => $idDepto];
  if ($idConv > 0) { $baseParams[':id_conv'] = $idConv; }

  // ===== KPIs =====
  // Aprobadas
  $sql = "
    SELECT COUNT(*) 
    FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO]
    WHERE ID_DEPARTAMENTO_APROBADOR = :id_dep
      $convFilter
      AND ESTADO = :est";
  $st = $pdo->prepare($sql);
  $st->execute($baseParams + [':est' => $EST_APROBADA]);
  $k_aprob = (int)$st->fetchColumn();

  // Rechazadas
  $st = $pdo->prepare($sql);
  $st->execute($baseParams + [':est' => $EST_RECHAZADA]);
  $k_rech = (int)$st->fetchColumn();

  // Pendientes = BORRADOR / ENVIADA o sin decisión
  $sql = "
    SELECT COUNT(*)
    FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO]
    WHERE ID_DEPARTAMENTO_APROBADOR = :id_dep
      $convFilter
      AND (ESTADO IN ('BORRADOR','ENVIADA') OR FECHA_DECISION IS NULL)";
  $st = $pdo->prepare($sql);
  $st->execute($baseParams);
  $k_pend = (int)$st->fetchColumn();

  // Pendientes vencidas = ENVIADA con > 1 mes desde FECHA_ENVIO
  $sql = "
    SELECT COUNT(*)
    FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO]
    WHERE ID_DEPARTAMENTO_APROBADOR = :id_dep
      $convFilter
      AND ESTADO = 'ENVIADA'
      AND FECHA_ENVIO IS NOT NULL
      AND GETDATE() > DATEADD(month, 1, FECHA_ENVIO)";
  $st = $pdo->prepare($sql);
  $st->execute($baseParams);
  $k_venc = (int)$st->fetchColumn();

  // ===== Tendencia 30 días (solo decisiones) =====
  $sql = "
    SELECT CONVERT(date, FECHA_DECISION) AS fecha, COUNT(*) AS decisiones
    FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO]
    WHERE ID_DEPARTAMENTO_APROBADOR = :id_dep
      $convFilter
      AND FECHA_DECISION >= DATEADD(day, -30, CONVERT(date, GETDATE()))
      AND ESTADO IN ('APROBADA','RECHAZADA')
    GROUP BY CONVERT(date, FECHA_DECISION)
    ORDER BY fecha";
  $st = $pdo->prepare($sql);
  $st->execute($baseParams);
  $tendencia = $st->fetchAll(PDO::FETCH_ASSOC);

  // ===== Breakdown por tipo (decisiones últimos 30 días) =====
  $sql = "
    SELECT TIPO_DOCUMENTO AS tipo, COUNT(*) AS cantidad
    FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO]
    WHERE ID_DEPARTAMENTO_APROBADOR = :id_dep
      $convFilter
      AND FECHA_DECISION >= DATEADD(day, -30, CONVERT(date, GETDATE()))
      AND ESTADO IN ('APROBADA','RECHAZADA')
    GROUP BY TIPO_DOCUMENTO
    ORDER BY cantidad DESC";
  $st = $pdo->prepare($sql);
  $st->execute($baseParams);
  $breakdown = $st->fetchAll(PDO::FETCH_ASSOC);

  // ===== Backlog (Top 20) =====
  $sql = "
    SELECT TOP (20)
      S.ID_SOLICITUD,
      S.TIPO_DOCUMENTO,
      S.ESTADO,
      S.FECHA_CREACION,
      S.FECHA_ENVIO,
      DATEDIFF(day, COALESCE(S.FECHA_ENVIO, S.FECHA_CREACION), GETDATE()) AS dias_pendientes
    FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO] S
    WHERE S.ID_DEPARTAMENTO_APROBADOR = :id_dep
      $convFilter
      AND (S.ESTADO IN ('BORRADOR','ENVIADA') OR S.FECHA_DECISION IS NULL)
    ORDER BY dias_pendientes DESC, S.FECHA_ENVIO ASC, S.FECHA_CREACION ASC";
  $st = $pdo->prepare($sql);
  $st->execute($baseParams);
  $backlog = $st->fetchAll(PDO::FETCH_ASSOC);

  // ===== Tickets por responsable =====
  $st = $pdo->prepare("
    SELECT ESTATUS, COUNT(*) AS c
    FROM [SIGED].[dbo].[TICKETS]
    WHERE ID_USUARIO_RESPONSABLE = :id_j
    GROUP BY ESTATUS
  ");
  $st->execute([':id_j' => $idJefe]);
  $tkRaw = $st->fetchAll(PDO::FETCH_KEY_PAIR);

  $TK_ABIERTOS = ['ABIERTO','NUEVO','ABIERTA'];
  $TK_CURSO    = ['EN_CURSO','EN PROCESO','EN_PROCESO','PENDIENTE'];
  $TK_CERRADOS = ['CERRADO','RESUELTO','CERRADA'];

  $k_tk_ab = 0; foreach ($TK_ABIERTOS as $v) { $k_tk_ab += (int)($tkRaw[$v] ?? 0); }
  $k_tk_cu = 0; foreach ($TK_CURSO   as $v) { $k_tk_cu += (int)($tkRaw[$v] ?? 0); }
  $k_tk_ce = 0; foreach ($TK_CERRADOS as $v) { $k_tk_ce += (int)($tkRaw[$v] ?? 0); }

  echo json_encode([
    'kpis' => [
      'aprobadas'           => $k_aprob,
      'rechazadas'          => $k_rech,
      'pendientes'          => $k_pend,
      'pendientes_vencidas' => $k_venc,
      'tickets_abiertos'    => $k_tk_ab,
      'tickets_en_curso'    => $k_tk_cu,
      'tickets_cerrados'    => $k_tk_ce,
      'convocatoria'        => $idConv,
      'id_departamento'     => $idDepto
    ],
    'tendencia_30d'  => $tendencia,
    'breakdown_tipo' => $breakdown,
    'backlog'        => $backlog
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Fallo en jefe_home_data: ' . $e->getMessage()]);
}
