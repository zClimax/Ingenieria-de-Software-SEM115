<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

header('Content-Type: application/json; charset=UTF-8');

try {
  requireRole(['DOCENTE']); // solo docentes
  $pdo = DB::conn();

  // Tablas
  $T_DOC   = 'dbo.DOCENTE';
  $T_USER  = 'dbo.USUARIOS';
  $T_DEPTS = 'dbo.DEPARTAMENTO'; 

  // Usuario en sesión
  $u = Session::user();
  $idUsuario = (int)$u['id'];

  $sql = "
    SELECT D.ID_DOCENTE,
           D.NOMBRE_DOCENTE, D.APELLIDO_PATERNO_DOCENTE, D.APELLIDO_MATERNO_DOCENTE,
           D.RFC, D.CORREO, D.CLAVE_EMPLEADO, D.NSS, D.FECHA_INGRESO, D.MATRICULA,
           U.ID_DEPARTAMENTO,
           DP.NOMBRE_DEPARTAMENTO
    FROM $T_DOC D
    JOIN $T_USER U    ON U.ID_USUARIO = D.ID_USUARIO
    LEFT JOIN $T_DEPTS DP ON DP.ID_DEPARTAMENTO = U.ID_DEPARTAMENTO
    WHERE D.ID_USUARIO = :idu";
  $st = $pdo->prepare($sql);
  $st->execute([':idu'=>$idUsuario]);
  $doc = $st->fetch();
  if (!$doc) { echo json_encode(['ok'=>false,'msg'=>'Docente no encontrado']); exit; }

  $idDoc = (int)$doc['ID_DOCENTE'];
  $nombreCompleto = trim(($doc['NOMBRE_DOCENTE'] ?? '') . ' ' . ($doc['APELLIDO_PATERNO_DOCENTE'] ?? '') . ' ' . ($doc['APELLIDO_MATERNO_DOCENTE'] ?? ''));

  // 2) Progreso total (vista)
  $sqlP = "SELECT PUNTOS_OBTENIDOS AS pts, PUNTOS_MAX AS mx, PORCENTAJE AS pct
           FROM dbo.VW_EDD_PROGRESO_DOCENTE WHERE ID_DOCENTE=:d";
  $sp = $pdo->prepare($sqlP);
  $sp->execute([':d'=>$idDoc]);
  $rowP = $sp->fetch();
  $pts = $rowP ? (int)$rowP['pts'] : 0;
  $mx  = $rowP ? (int)$rowP['mx']  : 300;
  $pct = $rowP ? (int)$rowP['pct'] : 0;

  // 3) Detalle por evidencia (todas las evidencias + flag TIENE)
  $sqlAll = "SELECT CODIGO, NOMBRE, PUNTAJE FROM dbo.EDD_EVIDENCIA_TIPO WHERE ACTIVO=1 ORDER BY CODIGO";
  $all = $pdo->query($sqlAll)->fetchAll();

  $sqlDet = "SELECT CODIGO_EVIDENCIA FROM dbo.VW_EDD_PROGRESO_DETALLE WHERE ID_DOCENTE=:d";
  $sd = $pdo->prepare($sqlDet);
  $sd->execute([':d'=>$idDoc]);
  $has = array_column($sd->fetchAll(), 'CODIGO_EVIDENCIA');
  $hasSet = array_flip($has); // para acceso O(1)

  $detalle = array_map(function($r) use ($hasSet){
    $cod = $r['CODIGO'];
    return [
      'codigo' => $cod,
      'nombre' => $r['NOMBRE'],
      'puntos' => (int)$r['PUNTAJE'],
      'tiene'  => array_key_exists($cod, $hasSet)
    ];
  }, $all);

  echo json_encode([
    'ok' => true,
    'docente' => [
      'id'             => $idDoc,
      'nombre'         => $nombreCompleto,
      'correo'         => $doc['CORREO'] ?? '',
      'departamento'   => $doc['NOMBRE_DEPARTAMENTO'] ?? ('Depto #' . ($doc['ID_DEPARTAMENTO'] ?? '')),
      'clave_empleado' => $doc['CLAVE_EMPLEADO'] ?? '',
      'nss'            => $doc['NSS'] ?? '',
      'fecha_ingreso'  => $doc['FECHA_INGRESO'] ?? '',
      'matricula'      => $doc['MATRICULA'] ?? '',
      'rfc'            => $doc['RFC'] ?? ''
    ],
    'progreso' => [
      'puntos'     => $pts,
      'max'        => $mx,
      'porcentaje' => $pct,
      'detalle'    => $detalle
    ]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
