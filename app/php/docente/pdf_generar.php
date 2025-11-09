<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo   = DB::conn();
$user  = (array)(Session::user() ?? []);
$uid   = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

// ------ Composer / TCPDF
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;
if (!class_exists('TCPDF')) {
  $tcpdf = __DIR__ . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
  if (file_exists($tcpdf)) require_once $tcpdf;
}
if (!class_exists('TCPDF')) { http_response_code(500); exit('TCPDF no disponible.'); }

// ------ Helper firma (dos ubicaciones comunes) + fallback mínimo
$firmaCandidates = [
  __DIR__ . '/../pdf/firma_pdf.php',     // app/php/pdf/firma_pdf.php
  __DIR__ . '/../../pdf/firma_pdf.php',  // /pdf/firma_pdf.php (raíz)
];
$firmaLoaded = false;
foreach ($firmaCandidates as $f) { if (file_exists($f)) { require_once $f; $firmaLoaded = true; break; } }
if (!$firmaLoaded) {
  function siged_firma_abs_path(PDO $pdo, int $idUsuario): ?string {
    $st = $pdo->prepare("SELECT RUTA_FIRMA FROM [SIGED].[dbo].[USUARIOS] WHERE ID_USUARIO = :id");
    $st->execute([':id'=>$idUsuario]);
    $ruta = (string)($st->fetchColumn() ?: '');
    if ($ruta === '') return null;
    $abs = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . $ruta;
    if (!file_exists($abs)) {
      $base = realpath(__DIR__ . '/../../..');
      if ($base && file_exists($base . $ruta)) $abs = $base . $ruta; else return null;
    }
    return $abs;
  }
  function siged_pdf_estampar_firma(TCPDF $pdf, PDO $pdo, int $idUser, float $x, float $y, float $w, bool $label=false, ?string $nombre=null): void {
    $abs = siged_firma_abs_path($pdo, $idUser);
    if (!$abs || !file_exists($abs)) return;
    $type = (strtoupper(pathinfo($abs, PATHINFO_EXTENSION)) === 'PNG') ? 'PNG' : 'JPG';
    $pdf->Image($abs, $x, $y, $w, 0, $type);
    if ($label) {
      $pdf->SetFont('helvetica','',9);
      $pdf->SetTextColor(60,60,60);
      $pdf->SetXY($x, $y + 12);
      $pdf->Cell($w+2, 5, $nombre ? ('Firma de ' . $nombre) : 'Firma', 0, 0, 'C');
    }
  }
  function siged_posicion_firma(string $tipo, string $rol): array {
    return (strtoupper($rol)==='JEFE') ? [140,240,35] : [40,240,35];
  }
}

// ------ Utilidades
function fecha_larga_es(?string $isoDate=null): string {
  $ts = $isoDate ? strtotime($isoDate) : time();
  $mes = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int)date('n',$ts)-1];
  return date('j',$ts) . ' de ' . $mes . ' de ' . date('Y',$ts);
}
function replace_vars(string $tpl, array $vars): string {
  // soporta {{k}}, {k} y [[k]]
  foreach ($vars as $k=>$v) {
    $tpl = str_replace(['{{'.$k.'}}','{'.$k.'}','[['.$k.']]'], (string)$v, $tpl);
  }
  return $tpl;
}

// ========== 1) Traer solicitud + validar propietario ==========
$sid = (int)($_GET['id'] ?? 0);
if ($sid <= 0) { http_response_code(400); exit('ID inválido'); }

$sql = "
SELECT TOP 1
  S.ID_SOLICITUD, S.TIPO_DOCUMENTO, S.ESTADO, S.FECHA_CREACION, S.FECHA_ENVIO, S.FECHA_DECISION,
  S.FOLIO, S.ID_CONVOCATORIA, S.RUTA_PDF, S.COMENTARIO_JEFE,
  D.ID_DOCENTE, D.NOMBRE_DOCENTE, D.APELLIDO_PATERNO_DOCENTE, D.APELLIDO_MATERNO_DOCENTE,
  D.RFC, D.CURP, D.CLAVE_EMPLEADO, D.CORREO,
  U.ID_USUARIO, U.NOMBRE_COMPLETO
FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO] S
JOIN [SIGED].[dbo].[DOCENTE] D ON D.ID_DOCENTE = S.ID_DOCENTE
JOIN [SIGED].[dbo].[USUARIOS] U ON U.ID_USUARIO = D.ID_USUARIO
WHERE S.ID_SOLICITUD = :sid";
$st = $pdo->prepare($sql);
$st->execute([':sid'=>$sid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Solicitud no encontrada'); }
if ((int)$row['ID_USUARIO'] !== $uid) { http_response_code(403); exit('No autorizado'); }

// Convocatoria (opcional)
$conv = null;
if (!empty($row['ID_CONVOCATORIA'])) {
  $stc = $pdo->prepare("SELECT TOP 1 NOMBRE_CONVOCATORIA, ANIO FROM [SIGED].[dbo].[CONVOCATORIA] WHERE ID_CONVOCATORIA = :c");
  $stc->execute([':c'=>(int)$row['ID_CONVOCATORIA']]);
  $conv = $stc->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Contexto de datos
$nombreDocente = trim(($row['NOMBRE_COMPLETO'] ?: ($row['NOMBRE_DOCENTE'].' '.$row['APELLIDO_PATERNO_DOCENTE'].' '.$row['APELLIDO_MATERNO_DOCENTE'])));
$folio          = (string)($row['FOLIO'] ?? '');
$tipo           = strtoupper((string)$row['TIPO_DOCUMENTO']);
$ciudad         = defined('Config::CIUDAD') ? (string)Config::CIUDAD : 'Culiacán, Sinaloa';
$fechaLarga     = fecha_larga_es(date('Y-m-d'));
$PROJ_ROOT = str_replace('\\','/', dirname(__DIR__, 3));

// helper para localizar la plantilla
function siged_find_template(array $cands): ?string {
  foreach ($cands as $p) { if ($p && is_readable($p)) return $p; }
  return null;
}

// ========== 2) Instanciar TCPDF ==========
$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('SIGED'); $pdf->SetAuthor('SIGED');
$pdf->SetTitle('Solicitud #' . $row['ID_SOLICITUD']);
$pdf->SetMargins(20, 25, 20); // laterales y superior
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ========== 3) Render por tipo ==========
$root = realpath(__DIR__ . '/../../..'); // C:\xampp\htdocs\SIGED
$storeDir = $root . '/storage/pdfs';
if (!is_dir($storeDir)) @mkdir($storeDir, 0775, true);

$filename = '';
$absPathSaved = '';


if (isset($_GET['debug'])) {
  header('Content-Type: text/plain; charset=UTF-8');

  $SID      = (int)($_GET['id'] ?? 0);
  $TIPO     = strtoupper(trim((string)($row['TIPO_DOCUMENTO'] ?? '')));
  $ROOT     = str_replace('\\','/', dirname(__DIR__, 3)); // C:/xampp/htdocs/SIGED
  $CANDID   = [
    $ROOT . '/pdf/plantillas/constancia_nombramiento.html',
    $ROOT . '/pdf/plantillas/cosntancia_nombramiento.html',
    $ROOT . '/app/pdf/plantillas/constancia_nombramiento.html',
    $ROOT . '/app/pdf/plantillas/cosntancia_nombramiento.html',
  ];

  echo "ID = $SID\n";
  echo "TIPO = [$TIPO]\n";
  echo "ROOT = $ROOT\n";
  echo "Candidates:\n";
  foreach ($CANDID as $p) {
    echo (file_exists($p) ? ' [OK] ' : ' [NO] ') . $p . "\n";
  }
  exit; // <<<< MUY IMPORTANTE: corta para ver el texto
}



if ($tipo === 'DCE') {
  // ---- Cargar plantilla HTML (intenta dos nombres)
  $tplPaths = [
    $root . '/pdf/plantillas/carta_exclusividad.html',  // con "i"
  ];
  $tplFile = null;
  foreach ($tplPaths as $p) { if (file_exists($p)) { $tplFile = $p; break; } }
  if (!$tplFile) {
    // fallback: título + cuerpo mínimo
    $pdf->SetFont('helvetica','B',14);
    $pdf->Cell(0,8,'CARTA DE EXCLUSIVIDAD LABORAL',0,1,'C'); $pdf->Ln(4);
    $pdf->SetFont('helvetica','',11);
    $pdf->MultiCell(0,6,$ciudad.' a '.$fechaLarga,0,'R'); $pdf->Ln(4);
    $pdf->MultiCell(0,6,"Docente: ".$nombreDocente."  (RFC: ".($row['RFC']??'—').", CURP: ".($row['CURP']??'—').")",0,'L');
    $pdf->Ln(12);
    $pdf->MultiCell(0,6,"Declaro bajo protesta de decir verdad que cumplo con la exclusividad laboral…",0,'J');
  } else {
    $html = file_get_contents($tplFile);
    if ($html === false) { http_response_code(500); exit('No se pudo leer la plantilla DCE'); }

    // ---- Variables que soporta la plantilla (reemplazo flexible)
    $vars = [
      'ciudad'             => $ciudad,
      'fecha_larga'        => $fechaLarga,
      'folio'              => $folio,
      'anio'               => $conv['ANIO'] ?? date('Y'),
      'convocatoria'       => $conv['NOMBRE_CONVOCATORIA'] ?? '',
      'nombre_docente'     => $nombreDocente,
      'rfc'                => (string)($row['RFC'] ?? ''),
      'curp'               => (string)($row['CURP'] ?? ''),
      'clave_empleado'     => (string)($row['CLAVE_EMPLEADO'] ?? ''),
      'correo_docente'     => (string)($row['CORREO'] ?? ''),
      // si quieres imprimir responsable RH:
      'nombre_jefe_rh'     => (defined('Config::JEFE_RH') ? (string)Config::JEFE_RH : ''),
      // Firma del docente inline (si la plantilla tiene {{firma_docente}})
      'firma_docente'      => '', // se llena si existe firma
    ];

    // Firma inline dentro del HTML (opcional)
    $sigPath = siged_firma_abs_path($pdo, $uid);
    if ($sigPath && file_exists($sigPath)) {
      // TCPDF acepta rutas locales absolutas
      $vars['firma_docente'] = '<img src="'.htmlspecialchars($sigPath).'" style="height:70px">';
    }

    // Reemplazos (soporta {{k}}, {k}, [[k]])
    $html = replace_vars($html, $vars);

    // Render
    $pdf->SetFont('helvetica','',11);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);

    // Si la plantilla NO traía placeholder de firma, la estampamos abajo-izquierda
    if (strpos($html, 'firma_docente') === false) {
      [$x,$y,$w] = siged_posicion_firma('DCE','DOCENTE'); // default [40,240,35]
      siged_pdf_estampar_firma($pdf, $pdo, $uid, $x, $y, $w, false, $nombreDocente);
    }
  }

  $filename = 'DCE_' . $sid . '.pdf';
  $absPathSaved = $storeDir . '/' . $filename;

}

/* ================= CNC · Constancia Nombramiento ================= */
if ($tipo === 'CNC') {


  // 1) plantilla (busca en /pdf/plantillas y /app/pdf/plantillas)
  $tplFile = siged_find_template([
    $PROJ_ROOT . '/pdf/plantillas/constancia_nombramiento.html',
    $PROJ_ROOT . '/app/pdf/plantillas/constancia_nombramiento.html',
  ]);

  // DEBUG duro: deja rastro en log por si vuelve a caer a fallback
  error_log('[SIGED CNC] tplFile=' . ($tplFile ?: 'NULL'));

  // 2) depto aprobador (desde solicitud o plantilla)
  $deptAprob = (int)($row['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
  try {
    $q = $pdo->query("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CNC' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $v = (int)($q->fetchColumn() ?: 0);
    if ($v > 0) $deptAprob = $v;
  } catch (\Throwable $e) { /* noop */ }

  // 3) jefe RH (rol=2) y firma
  $idJefe=0; $nombreJefe=''; $firmaJefeAbs='';
  if ($deptAprob > 0) {
    $s = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
      FROM dbo.USUARIOS
      WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1
      ORDER BY ID_USUARIO
    ");
    $s->execute([':d'=>$deptAprob]);
    if ($j = $s->fetch(PDO::FETCH_ASSOC)) {
      $idJefe = (int)$j['ID_USUARIO'];
      $nombreJefe = (string)$j['NOMBRE_COMPLETO'];
      $p = siged_firma_abs_path($pdo, $idJefe);
      if ($p && is_readable($p)) $firmaJefeAbs = $p;
    }
  }

  // 4) si no hay plantilla, NO sigas: muestra por qué
  if (!$tplFile) {
    // si llegas aquí, mira el php_error_log por la línea [SIGED CNC] de arriba
    $pdf->SetFont('helvetica','B',14);
    $pdf->Cell(0,8,'CONSTANCIA / SOLICITUD',0,1,'C'); $pdf->Ln(6);
    $pdf->SetFont('helvetica','',11);
    $pdf->MultiCell(0,6,'Tipo: CNC',0,'L');
    $pdf->MultiCell(0,6,'Docente: '.$nombreDocente,0,'L');
  } else {
    // 5) render con reemplazo {var} y {{var}}
    $html = file_get_contents($tplFile);
    if ($html === false) { http_response_code(500); exit('No se pudo leer la plantilla CNC'); }

    $fechaIngresoTxt = '';
    if (!empty($row['FECHA_INGRESO'])) {
      $ts = strtotime((string)$row['FECHA_INGRESO']);
      if ($ts) $fechaIngresoTxt = date('d/m/Y', $ts);
    }
    $vars = [
      'ciudad'               => $ciudad,
      'fecha_larga'          => $fechaLarga,
      'folio'                => $folio,
      'anio'                 => $conv['ANIO'] ?? date('Y'),
      'convocatoria'         => $conv['NOMBRE_CONVOCATORIA'] ?? '',
      'nombre_docente'       => $nombreDocente,
      'correo_docente'       => (string)($row['CORREO'] ?? ''),
      'rfc'                  => (string)($row['RFC'] ?? ''),
      'curp'                 => (string)($row['CURP'] ?? ''),
      'folio_documento'      => $folio,
      'filiacion_rfc'        => (string)($row['RFC'] ?? ''),
      'fecha_ingreso_texto'  => $fechaIngresoTxt,
      'categoria_anterior'   => '',
      'estatus_anterior'     => '',
      'categoria_actual'     => '',
      'clave_presupuestal'   => '',
      'estatus_actual'       => '',
      'fecha_emision_texto'  => $fechaLarga,
      'path_firma_jefe_rh'   => $firmaJefeAbs,
      'nombre_jefe_rh'       => $nombreJefe,
      'path_qr_autenticidad' => '',
      'logo_sep'             => '',
      'logo_tecnm'           => '',
    ];
    foreach ($vars as $k=>$v) {
      $html = str_replace('{{'.$k.'}}', (string)$v, $html);
      $html = str_replace('{'.$k.'}',   (string)$v, $html);
    }

    $pdf->SetFont('helvetica','',11);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);
  }

  // 6) guardar y servir
  $filename     = 'CNC_' . $sid . '.pdf';
  $absPathSaved = $PROJ_ROOT . '/storage/pdfs/' . $filename;
  $pdf->Output($absPathSaved, 'F');

  $rutaWeb = '/siged/storage/pdfs/' . $filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb) {
    $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p WHERE ID_SOLICITUD=:id")
        ->execute([':p'=>$rutaWeb, ':id'=>$sid]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($absPathSaved);
  exit;
}



else {
  // ---- Fallback genérico para otros tipos
  $pdf->SetFont('helvetica','B',16);
  $pdf->Cell(0,8,'CONSTANCIA / SOLICITUD',0,1,'C'); $pdf->Ln(2);
  $pdf->SetFont('helvetica','',11);
  $pdf->MultiCell(0,6,'Tipo: '.$tipo,0,'L');
  $pdf->MultiCell(0,6,'Docente: '.$nombreDocente,0,'L');
  $pdf->Ln(16);
  [$x,$y,$w] = siged_posicion_firma($tipo,'DOCENTE');
  siged_pdf_estampar_firma($pdf, $pdo, $uid, $x, $y, $w, true, $nombreDocente);

  $filename = $tipo . '_' . $sid . '.pdf';
  $absPathSaved = $storeDir . '/' . $filename;
}

// ========== 4) Guardar + persistir ruta + servir ==========
$pdf->Output($absPathSaved, 'F');

// Persistir ruta web si cambió
$rutaWeb = '/siged/storage/pdfs/' . $filename;
if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb) {
  $up = $pdo->prepare("UPDATE [SIGED].[dbo].[SOLICITUD_DOCUMENTO] SET RUTA_PDF = :p WHERE ID_SOLICITUD = :id");
  $up->execute([':p'=>$rutaWeb, ':id'=>$sid]);
}

// Entregar inline
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
readfile($absPathSaved);
exit;
