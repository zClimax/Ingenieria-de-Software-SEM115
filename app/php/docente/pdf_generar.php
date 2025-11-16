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

/* ================= CCA enriquecido ================= */
if ($tipo === 'CCA') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_horarios.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_horarios.html'
  ] as $p) if (is_readable($p)) { $tpl = $p; break; }
  if (!$tpl) { http_response_code(500); exit('Plantilla CCA no encontrada'); }

  // Deptos (ya lo tienes)
  $q = $pdo->prepare("
    SELECT S.ID_DEPARTAMENTO_APROBADOR, U.ID_DEPARTAMENTO AS DEP_DOCENTE
    FROM dbo.SOLICITUD_DOCUMENTO S
    JOIN dbo.DOCENTE D ON D.ID_DOCENTE=S.ID_DOCENTE
    JOIN dbo.USUARIOS U ON U.ID_USUARIO=D.ID_USUARIO
    WHERE S.ID_SOLICITUD=:id
  ");
  $q->execute([':id'=>$sid]);
  $r = $q->fetch(PDO::FETCH_ASSOC) ?: [];
  $depDoc = (int)($r['DEP_DOCENTE'] ?? 0);
  $depApr = (int)($r['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
  if ($depApr === 0) $depApr = $depDoc;

  // Jefe firmante (rol 2)
  $idJefe=0; $nombreJefe=''; $firmaJefe='';
  $sj = $pdo->prepare("SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO FROM dbo.USUARIOS
                       WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1
                       ORDER BY ID_USUARIO");
  $sj->execute([':d'=>$depApr]);
  if ($j=$sj->fetch(PDO::FETCH_ASSOC)) {
    $idJefe=(int)$j['ID_USUARIO']; $nombreJefe=(string)$j['NOMBRE_COMPLETO'];
    $abs = siged_firma_abs_path($pdo,$idJefe); // tu helper
    if ($abs && is_readable($abs)) $firmaJefe=$abs;
  }

  // Nombre del depto
  $deptName = (string)($pdo->query("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO=".$depDoc)->fetchColumn() ?: ('Depto #'.$depDoc));

  // Datos extra del docente (si los tienes en DOCENTE)
  $nombramiento   = (string)($row['NOMBRAMIENTO'] ?? '');   // si no existen, quedan vacíos
  $horasBase      = (string)($row['HORAS_BASE']  ?? '');
  $curp           = (string)($row['CURP']        ?? '');
  $antiguedadTxt  = ''; // calcula si tienes FECHA_INGRESO
  if (!empty($row['FECHA_INGRESO'])) {
    $fi = new DateTime((string)$row['FECHA_INGRESO']); $hoy=new DateTime();
    $diff=$fi->diff($hoy); $antiguedadTxt = $diff->y.' años '. $diff->m.' meses';
  }

  // Periodos objetivo (ajusta si quieres otro rango)
  $anioActual   = (int)date('Y');
  $anioAnterior = $anioActual - 1;
  $P1 = $anioAnterior.'-ENE-JUN';
  $P2 = $anioAnterior.'-AGO-DIC';
  $P3 = $anioActual  .'-ENE-JUN';

  $filaVacia = '<tr><td colspan="5">Sin registro</td></tr>';

  $makeRows = function(string $per) use($pdo,$idDocente,$filaVacia){
    try {
      $st = $pdo->prepare("
        SELECT ASIGNATURA,NIVEL,GRUPO,HORAS_SEMANA,(HORAS_SEMANA*16) AS TOTAL
        FROM dbo.VW_HORARIO_DOCENTE
        WHERE ID_DOCENTE=:d AND PERIODO=:p
        ORDER BY ASIGNATURA
      ");
      $st->execute([':d'=>$idDocente, ':p'=>$per]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);
      if (!$rows) return $filaVacia;
      $html='';
      foreach($rows as $x){
        $html.='<tr>'.
          '<td>'.htmlspecialchars($x['ASIGNATURA']).'</td>'.
          '<td>'.htmlspecialchars($x['NIVEL']).'</td>'.
          '<td>'.htmlspecialchars($x['GRUPO']).'</td>'.
          '<td style="text-align:center">'.(int)$x['HORAS_SEMANA'].'</td>'.
          '<td style="text-align:center">'.(int)$x['TOTAL'].'</td>'.
        '</tr>';
      }
      return $html;
    } catch(Throwable $e) { return $filaVacia; }
  };

  $filasP1 = $makeRows($P1);
  $filasP2 = $makeRows($P2);
  $filasP3 = $makeRows($P3);

  // Totales (si existe la vista de totales)
  $tot_fg=0; $tot_global=0;
  try {
    $ts = $pdo->prepare("
      SELECT SUM(TOT_FG) AS FG, SUM(TOT_GLOBAL) AS TG
      FROM dbo.VW_CARGA_TOTALES
      WHERE ID_DOCENTE=:d AND PERIODO IN (:p1,:p2,:p3)
    ");
    // truco ODBC: bindea separadas
    $ts = $pdo->prepare("
      SELECT SUM(TOT_FG) AS FG, SUM(TOT_GLOBAL) AS TG FROM dbo.VW_CARGA_TOTALES
      WHERE ID_DOCENTE=:d AND (PERIODO=:a OR PERIODO=:b OR PERIODO=:c)
    ");
    $ts->execute([':d'=>$idDocente, ':a'=>$P1, ':b'=>$P2, ':c'=>$P3]);
    if ($t=$ts->fetch(PDO::FETCH_ASSOC)) {
      $tot_fg     = (int)($t['FG'] ?? 0);
      $tot_global = (int)($t['TG'] ?? 0);
    }
  } catch(Throwable $e) { /* opcional */ }

  // Folio + URL verificación + QR
  $folio = $row['FOLIO'] ?: ('SIGED-'.$anioActual.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;
  $lugarFecha = 'Culiacán, Sin., a '.date('d').' de '.strftime('%B').' de '.date('Y');

  $html = file_get_contents($tpl);
  $vars = [
    'lugar_fecha'         => $lugarFecha,
    'nombre_departamento' => $deptName,
    'nombre_docente'      => $nombreDocente,
    'rfc_docente'         => (string)($row['RFC'] ?? ''),
    'curp_docente'        => $curp,
    'nombramiento'        => $nombramiento,
    'horas_base'          => $horasBase,
    'antiguedad_texto'    => $antiguedadTxt,
    'anio_actual'         => (string)$anioActual,
    'anio_anterior'       => (string)$anioAnterior,
    'filas_tabla_2024_1'  => $filasP1,
    'filas_tabla_2024_2'  => $filasP2,
    'filas_tabla_2025_1'  => $filasP3,
    'tot_horas_fg'        => (string)$tot_fg,
    'tot_horas_global'    => (string)$tot_global,
    'firma_jefe_depto'    => $firmaJefe ?: '',
    'nombre_jefe_depto'   => $nombreJefe ?: '',
    'folio'               => $folio,
    'url_verificacion'    => $urlVer,
    'qr_html'             => ''  // lo llenamos con TCPDF más abajo si prefieres
  ];
  foreach ($vars as $k=>$v){ $html=str_replace(['{{'.$k.'}}','{'.$k.'}'], (string)$v, $html); }

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html,true,false,true,false,'');

  // (Opcional) Generar QR en el PDF (si no usas <img>)
  // $pdf->write2DBarcode($urlVer, 'QRCODE,H', 170, 245, 25, 25);

  // Guardar/servir
  $filename='CCA_'.$sid.'.pdf';
  $abs=$PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs,'F');
  $rutaWeb='/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb) {
    $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p, FOLIO=:f WHERE ID_SOLICITUD=:id")
        ->execute([':p'=>$rutaWeb, ':f'=>$folio, ':id'=>$sid]);
  }
  header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="'.$filename.'"'); readfile($abs); exit;
}

/* ================== CVU (Constancia CVU-TecNM) ================== */
if ($tipo === 'CVU') {
  // Raíz del proyecto (normaliza separadores)
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Localizar la plantilla HTML
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cvu.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cvu.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CVU no encontrada'); }

  // 2) Datos base del docente desde $row (ya existen en tu SELECT principal)
  $rfc        = (string)($row['RFC']  ?? '');
  $curp       = (string)($row['CURP'] ?? '');
  $nombreDept = (string)($pdo->query("
    SELECT TOP 1 NOMBRE_DEPARTAMENTO 
    FROM dbo.DEPARTAMENTO 
    WHERE ID_DEPARTAMENTO = (SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO = ".(int)$row['ID_USUARIO'].")
  ")->fetchColumn() ?: '');

  // 3) Departamento aprobador (usa el que quedó en la solicitud; si no hay, default a 8 = Desarrollo Académico)
  $stApr = $pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:sid");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 8);

  // 4) Jefe firmante (rol 2 en el depto aprobador) + firma
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO 
    FROM dbo.USUARIOS 
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d'=>$depApr]);
  $j = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Titular del Departamento de Desarrollo Académico';
  $firmaJefe  = '';
  if ($j) {
    $abs = null;
    if (function_exists('siged_firma_abs_path')) {
      $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    } else {
      // Fallback si no tienes helper: intenta leer de una tabla de firmas estándar
      $sf = $pdo->prepare("SELECT RUTA_FIRMA FROM dbo.FIRMAS_USUARIO WHERE ID_USUARIO=:u AND ACTIVO=1");
      if ($sf->execute([':u'=>(int)$j['ID_USUARIO']])) {
        $ruta = (string)($sf->fetchColumn() ?: '');
        if ($ruta !== '') { $abs = (strpos($ruta,':') === false ? $PROJ_ROOT.$ruta : $ruta); }
      }
    }
    if ($abs && is_readable($abs)) { $firmaJefe = $abs; }
  }

  // 5) Lugar/fecha + folio + URL de verificación
  // (Si usas strftime con nombres de mes en ES, asegúrate de setlocale antes en tu bootstrap)
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sin., a '.date('d').' de '.$mes.' de '.date('Y');

  $folio  = $row['FOLIO'] ?: ('CVU-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 6) Render HTML (reemplazo de placeholders)
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_docente}'     => $nombreDocente,
    '{rfc_docente}'        => $rfc,
    '{curp_docente}'       => $curp,
    '{firma_jefe_depto}'   => $firmaJefe,
    '{nombre_jefe_depto}'  => $nombreJefe,
    '{folio}'              => $folio,
    '{url_verificacion}'   => $urlVer,
    '{nombre_departamento}'=> $nombreDept,
  ];
  $html = strtr($html, $repl);

  // 7) Escribir al PDF
  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  // 8) Guardar archivo y actualizar la solicitud (RUTA_PDF / FOLIO)
  $filename = 'CVU_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $up = $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p, FOLIO=:f WHERE ID_SOLICITUD=:sid");
    $up->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);
  }

  // 9) Entregar en línea
  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== CSE (Constancia de Servicios Escolares) ================== */
if ($tipo === 'CSE') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_servicios.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_servicios.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CSE no encontrada'); }

  // 2) Datos base del docente (de tu SELECT principal $row)
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—'); // usa el que tengas
  $anio = date('Y');

  // 3) Aprobador y firma del Jefe de Servicios Escolares
  $stApr = $pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:sid");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  // Si por alguna razón no viene, toma el que figura en PLANTILLA_DOC
  if ($depApr === 0) {
    $d = $pdo->prepare("SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR FROM dbo.PLANTILLA_DOC WHERE TIPO_DOCUMENTO='CSE' AND ACTIVO=1 ORDER BY ID_PLANTILLA DESC");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d'=>$depApr]);
  $j = $sj->fetch(PDO::FETCH_ASSOC);
  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Titular del Departamento de Servicios Escolares';
  $firmaJefe  = '';
  if ($j && function_exists('siged_firma_abs_path')) {
    $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    if ($abs && is_readable($abs)) $firmaJefe = $abs;
  }

  // 4) Filas de la tabla (detalle capturado en DOCENTE_CARGA)
  $q = $pdo->prepare("
    SELECT PERIODO, NIVEL, CLAVE_MATERIA, NOMBRE_MATERIA, ALUMNOS_ATENDIDOS
    FROM dbo.DOCENTE_CARGA
    WHERE ID_SOLICITUD = :sid
    ORDER BY ORDEN, ID_CARGA
  ");
  $q->execute([':sid'=>$sid]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $filas = '';
  $totalAlum = 0;
  foreach ($rows as $r) {
    $totalAlum += (int)$r['ALUMNOS_ATENDIDOS'];
    $filas .= '<tr>'
            . '<td class="center">'.htmlspecialchars($r['PERIODO']).'</td>'
            . '<td class="center">'.htmlspecialchars($r['NIVEL']).'</td>'
            . '<td class="center">'.htmlspecialchars($r['CLAVE_MATERIA']).'</td>'
            . '<td>'.htmlspecialchars($r['NOMBRE_MATERIA']).'</td>'
            . '<td class="center">'.(int)$r['ALUMNOS_ATENDIDOS'].'</td>'
            . '</tr>';
  }
  if ($filas === '') {
    $filas = '<tr><td colspan="5" class="center">Sin registros capturados.</td></tr>';
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.date('d').' de '.$mes.' de '.date('Y');
  $folio  = $row['FOLIO'] ?: ('CSE-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'     => $lugarFecha,
    '{nombre_docente}'  => $nombreDocente,
    '{expediente}'      => $expediente,
    '{anio}'            => $anio,
    '{filas_tabla}'     => $filas,
    '{total_alumnos}'   => (string)$totalAlum,
    '{firma_jefe}'      => $firmaJefe,
    '{nombre_jefe}'     => $nombreJefe,
    '{folio}'           => $folio,
    '{url_verificacion}'=> $urlVer,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  // 7) Guardar/actualizar y servir
  $filename = 'CSE_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p, FOLIO=:f WHERE ID_SOLICITUD=:sid")
        ->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}


/* ================== ACI (Constancia Centro de Información) ================== */
if ($tipo === 'ACI') {
  $ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $ROOT.'/pdf/plantillas/constancia_centro_info.html',
    $ROOT.'/app/pdf/plantillas/constancia_centro_info.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla ACI no encontrada'); }

  // 2) Datos base del oficio
  $q = $pdo->prepare("
    SELECT TOP 1 *
    FROM dbo.DOCENTE_CI_CONST
    WHERE ID_SOLICITUD=:sid
    ORDER BY ID_CI DESC
  ");
  $q->execute([':sid'=>$sid]);
  $ci = $q->fetch(PDO::FETCH_ASSOC);
  if (!$ci) { http_response_code(400); exit('Faltan datos de la constancia (DOCENTE_CI_CONST).'); }

  $actividad       = (string)$ci['ACTVIDAD'];
  $periodo         = (string)$ci['PERIODO'];
  $dictamen        = (string)($ci['DICTAMEN'] ?? '');
  $alumnosTotal    = (int)$ci['ALUMNOS_TOTAL'];
  $alumnosCredito  = (int)$ci['ALUMNOS_CREDITO'];
  $oficioNo        = (string)($ci['OFICIO_NO'] ?? '');
  $lugar           = (string)($ci['LUGAR'] ?? 'Culiacán, Sinaloa');

  // Fechas
  $ts   = $ci['FECHA_OFICIO'] ? strtotime($ci['FECHA_OFICIO']) : time();
  $mes  = function_exists('strftime') ? strftime('%B', $ts) : date('F', $ts);
  $fechaCorta = date('d/m/Y', $ts);
  $fechaLarga = date('d', $ts).' de '.$mes.' de '.date('Y', $ts);

  // 3) Firmas / Jefe CI (rol=2 depto 18)
  $DEP_CI = 18;
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d'=>$DEP_CI]);
  $j = $sj->fetch(PDO::FETCH_ASSOC);
  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Jefe(a) del Centro de Información';
  $firmaJefe  = '';
  if ($j && function_exists('siged_firma_abs_path')) {
    $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    if ($abs && is_readable($abs)) $firmaJefe = $abs;
  }

  // 4) Subdirección Académica (Vo.Bo. impreso)
  // Opcional: coloca un PNG en /storage/firmas/subdireccion_academica.png
  $firmaSub  = $ROOT.'/storage/firmas/subdireccion_academica.png';
  if (!is_readable($firmaSub)) $firmaSub = ''; // si no existe, se omite la imagen
  $nombreSub = 'Subdirección Académica';

  // 5) Folio / verificación
  $folio  = $row['FOLIO'] ?: ('ACI-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 6) Reemplazos
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar}'            => $lugar,
    '{fecha_corta}'      => $fechaCorta,
    '{fecha_larga}'      => $fechaLarga,
    '{oficio_no}'        => $oficioNo,
    '{nombre_docente}'   => $nombreDocente,
    '{actividad}'        => $actividad,
    '{periodo}'          => $periodo,
    '{dictamen}'         => $dictamen,
    '{alumnos_total}'    => (string)$alumnosTotal,
    '{alumnos_credito}'  => (string)$alumnosCredito,
    '{firma_jefe}'       => $firmaJefe,
    '{nombre_jefe}'      => $nombreJefe,
    '{firma_subdir}'     => $firmaSub,
    '{nombre_subdir}'    => $nombreSub,
    '{folio}'            => $folio,
    '{url_verificacion}' => $urlVer,
  ];
  $html = strtr($html, $repl);

  // 7) Render y persistencia
  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'ACI_'.$sid.'.pdf';
  $abs      = $ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p, FOLIO=:f WHERE ID_SOLICITUD=:sid")
        ->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================= RED · Recurso Educativo Digital ================= */
if ($tipo === 'RED') {
  // IDs base
  $sid = (int)($_GET['id'] ?? $_REQUEST['id'] ?? 0);
  if ($sid <= 0) { http_response_code(400); exit('ID de solicitud inválido'); }

  // 0) Cabecera de solicitud + docente
  $cab = $pdo->prepare("
    SELECT S.ID_SOLICITUD, S.TIPO_DOCUMENTO, S.ID_DOCENTE, S.ID_DEPARTAMENTO_APROBADOR,
           S.FOLIO, S.HASH_QR, S.VERSION,
           D.NOMBRE_DOCENTE, D.APELLIDO_PATERNO_DOCENTE, D.APELLIDO_MATERNO_DOCENTE
    FROM dbo.SOLICITUD_DOCUMENTO S
    JOIN dbo.DOCENTE D ON D.ID_DOCENTE = S.ID_DOCENTE
    WHERE S.ID_SOLICITUD = :id
  ");
  $cab->execute([':id'=>$sid]);
  $rowCab = $cab->fetch(PDO::FETCH_ASSOC);
  if (!$rowCab) { http_response_code(404); exit('Solicitud no encontrada'); }

  $nombreDocente = trim(($rowCab['NOMBRE_DOCENTE'] ?? '').' '.($rowCab['APELLIDO_PATERNO_DOCENTE'] ?? '').' '.($rowCab['APELLIDO_MATERNO_DOCENTE'] ?? ''));
  $deptAprob     = (int)($rowCab['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
  $folio         = $rowCab['FOLIO'] ?: ('RED-'.date('Y').'-'.$sid);
  $urlVer        = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 1) Datos capturados por Jefe (tabla DOC_DEP_RECURSO)
  $stData = $pdo->prepare("
    SELECT TOP 1 ASIGNATURA, PROGRAMA_EDUCATIVO, OFICIO_NO, LUGAR,
           TRY_CONVERT(date, FECHA_OFICIO) AS FECHA_OFICIO
    FROM dbo.DOC_DEP_RECURSO
    WHERE ID_SOLICITUD = :id
    ORDER BY ID_DEP_RECURSO DESC
  ");
  $stData->execute([':id'=>$sid]);
  $d = $stData->fetch(PDO::FETCH_ASSOC) ?: [
    'ASIGNATURA'=>'', 'PROGRAMA_EDUCATIVO'=>'', 'OFICIO_NO'=>'', 'LUGAR'=>'', 'FECHA_OFICIO'=>null
  ];

  // 2) Nombre de departamento aprobador
  $depNombre = '';
  if ($deptAprob > 0) {
    $qDep = $pdo->prepare("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO=:d");
    $qDep->execute([':d'=>$deptAprob]);
    $depNombre = (string)($qDep->fetchColumn() ?: '');
  }
  if ($depNombre === '') { $depNombre = 'Departamento'; }

  // 3) Jefe del depto aprobador + firma
  $idJefe = 0; $nombreJefe=''; $firmaJefeAbs='';
  if ($deptAprob > 0) {
    $s = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
      FROM dbo.USUARIOS
      WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1
      ORDER BY CASE WHEN FECHA_FIRMA IS NULL THEN 1 ELSE 0 END, FECHA_FIRMA DESC, ID_USUARIO DESC
    ");
    $s->execute([':d'=>$deptAprob]);
    if ($j = $s->fetch(PDO::FETCH_ASSOC)) {
      $idJefe = (int)$j['ID_USUARIO'];
      $nombreJefe = (string)$j['NOMBRE_COMPLETO'];
      if (function_exists('siged_firma_abs_path')) {
        $firmaJefeAbs = (string)(siged_firma_abs_path($pdo, $idJefe) ?: '');
      } else {
        // Fallback si no existe helper
        $try = realpath($PROJ_ROOT . '/storage/firmas/user_' . $idJefe . '.png');
        if ($try && is_readable($try)) $firmaJefeAbs = $try;
      }
    }
  }

  // 4) Fecha/ciudad
  $dt = $d['FECHA_OFICIO'] ? new DateTime($d['FECHA_OFICIO']) : new DateTime();
  $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $fechaLarga = $dt->format('j').' de '.$meses[(int)$dt->format('n')].' de '.$dt->format('Y');
  $ciudad = $d['LUGAR'] ?: 'Culiacán, Sinaloa';

  
  $tplFile = siged_find_template([
    $PROJ_ROOT . '/pdf/plantillas/constancia_recurso_depto.html',
    $PROJ_ROOT . '/app/pdf/plantillas/constancia_recurso_depto.html',
  ]);
  error_log('[SIGED RED] tplFile=' . ($tplFile ?: 'NULL'));

  if (!$tplFile) {
    // fallback ultra simple si no existiera la plantilla
    $pdf->SetFont('helvetica','B',14);
    $pdf->Cell(0,8,'CONSTANCIA RED',0,1,'C'); $pdf->Ln(6);
    $pdf->SetFont('helvetica','',11);
    $pdf->MultiCell(0,6,'Docente: '.$nombreDocente,0,'L');
    $pdf->MultiCell(0,6,'Asignatura: '.($d['ASIGNATURA'] ?: '—'),0,'L');
    $pdf->MultiCell(0,6,'Programa: '.($d['PROGRAMA_EDUCATIVO'] ?: '—'),0,'L');
  } else {
    $html = file_get_contents($tplFile);
    if ($html === false) { http_response_code(500); exit('No se pudo leer la plantilla RED'); }

    $firmaImgTag = '';
    if ($firmaJefeAbs && is_readable($firmaJefeAbs)) {
      $firmaImgTag = '<img src="'.htmlspecialchars($firmaJefeAbs, ENT_QUOTES, 'UTF-8').'" width="180" style="height:auto; display:block; margin:0 auto 4pt;" />';
    }

    
    // 2) Rutas de logos (opcionales)
    $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
    $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
    $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';


    $vars = [
      'DOCENTE_NOMBRE'   => $nombreDocente,
      'ASIGNATURA'       => (string)$d['ASIGNATURA'],
      'PROGRAMA'         => (string)$d['PROGRAMA_EDUCATIVO'],
      'OFICIO_NO'        => (string)$d['OFICIO_NO'],
      'LUGAR'            => $ciudad,
      'FECHA'            => $dt->format('d/m/Y'),
      'DEPTO_NOMBRE'     => $depNombre,
      'JEFE_NOMBRE'      => ($nombreJefe ?: 'Jefe de Departamento'),
      'CIUDAD'           => $ciudad,
      'FECHA_LARGA'      => $fechaLarga,
      'folio'            => $folio,
      'url_verificacion' => $urlVer,
      'path_firma_jefe_img' => $firmaImgTag,
    ];

    $html = str_replace(
      ['{logo_sep}','{logo_tecnm}','{CIUDAD}','{FECHA_LARGA}'],
      [$logoSep,     $logoTecNM,       $ciudad,  $fechaLarga],
      $html
    );
    foreach ($vars as $k=>$v) {
      $html = str_replace('{{'.$k.'}}', (string)$v, $html);
      $html = str_replace('{'.$k.'}',   (string)$v, $html);
    }


    
    $pdf->SetTextColor(0,0,0);
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetLineWidth(0.25);
    $pdf->SetMargins(22, 18, 22);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->writeHTML($html, false, false, true, false, '');

  }

  // 6) guardar y servir 
  $filename     = 'RED_' . $sid . '.pdf';
  $absPathSaved = $PROJ_ROOT . '/storage/pdfs/' . $filename;
  $pdf->Output($absPathSaved, 'F');

  $rutaWeb = '/siged/storage/pdfs/' . $filename;
  if (empty($rowCab['RUTA_PDF']) || $rowCab['RUTA_PDF'] !== $rutaWeb) {
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
