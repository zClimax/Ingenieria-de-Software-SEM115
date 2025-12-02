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

    $sigPath = siged_firma_abs_path($pdo, $uid);
    if ($sigPath && file_exists($sigPath)) {
      // TCPDF acepta rutas locales absolutas
      $vars['firma_docente'] = '<img src="'.htmlspecialchars($sigPath).'" style="height:70px">';
    }



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
      'firma_docente'      => '', 
      'clave_presupuestal' => '123456',
      'campus'=> 'Culiacán',
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
 $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

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
      'logo_sep'             => $logoSep,
      'logo_tecnm'           => $logoTecNM,
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




/* ================= CCA enriquecido (DOCENTE + CARGA_DOCENTE + CARGA_DETALLE) ================= */
if ($tipo === 'CCA') {
    $PROJ_ROOT = str_replace('\\', '/', dirname(__DIR__, 3));

    // 1) Localizar plantilla HTML
    $tpl = null;
    foreach ([
        $PROJ_ROOT . '/pdf/plantillas/constancia_horarios.html',
        $PROJ_ROOT . '/app/pdf/plantillas/constancia_horarios.html'
    ] as $p) {
        if (is_readable($p)) { $tpl = $p; break; }
    }
    if (!$tpl) {
        http_response_code(500);
        exit('Plantilla CCA no encontrada');
    }

    // 2) Datos de la solicitud + docente + departamento
    $sqlInfo = $pdo->prepare("
        SELECT 
            S.ID_SOLICITUD,
            S.FOLIO,
            S.RUTA_PDF,
            S.ID_DOCENTE,
            S.ID_DEPARTAMENTO_APROBADOR,
            D.NOMBRE_DOCENTE,
            D.APELLIDO_PATERNO_DOCENTE,
            D.APELLIDO_MATERNO_DOCENTE,
            D.RFC,
            D.CURP,
            D.FECHA_INGRESO,
            D.NOMBRAMIENTO,
            D.HORAS_BASE,
            U.ID_DEPARTAMENTO AS DEP_DOCENTE
        FROM dbo.SOLICITUD_DOCUMENTO S
        JOIN dbo.DOCENTE  D ON D.ID_DOCENTE = S.ID_DOCENTE
        JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
        WHERE S.ID_SOLICITUD = :id
    ");
    $sqlInfo->execute([':id' => $sid]);
    $info = $sqlInfo->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        http_response_code(404);
        exit('Solicitud / Docente no encontrados para CCA');
    }

    $idDocente = (int)$info['ID_DOCENTE'];
    $depDoc    = (int)($info['DEP_DOCENTE'] ?? 0);
    $depApr    = (int)($info['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
    if ($depApr === 0) $depApr = $depDoc;

    $nombreDocente = trim(
        (string)($info['NOMBRE_DOCENTE'] ?? '') . ' ' .
        (string)($info['APELLIDO_PATERNO_DOCENTE'] ?? '') . ' ' .
        (string)($info['APELLIDO_MATERNO_DOCENTE'] ?? '')
    );

    $rfc          = (string)($info['RFC'] ?? '');
    $curp         = (string)($info['CURP'] ?? '');
    $nombramiento = (string)($info['NOMBRAMIENTO'] ?? '');
    $horasBase    = (string)($info['HORAS_BASE'] ?? '');

    // Antigüedad
    $antiguedadTxt = '';
    if (!empty($info['FECHA_INGRESO'])) {
        try {
            $fi   = new DateTime((string)$info['FECHA_INGRESO']);
            $hoy  = new DateTime();
            $diff = $fi->diff($hoy);
            $antiguedadTxt = $diff->y . ' años ' . $diff->m . ' meses';
        } catch (Throwable $e) {
            $antiguedadTxt = '';
        }
    }

    // 3) Jefe de departamento firmante (ROL = 2)
    $idJefe     = 0;
    $nombreJefe = '';
    $firmaJefe  = '';

    $sj = $pdo->prepare("
        SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO, RUTA_FIRMA
        FROM dbo.USUARIOS
        WHERE ID_ROL = 2
          AND ID_DEPARTAMENTO = :d
          AND ACTIVO = 1
        ORDER BY ID_USUARIO
    ");
    $sj->execute([':d' => $depApr]);
    if ($j = $sj->fetch(PDO::FETCH_ASSOC)) {
        $idJefe     = (int)$j['ID_USUARIO'];
        $nombreJefe = (string)$j['NOMBRE_COMPLETO'];

        if (function_exists('siged_firma_abs_path')) {
            $abs = siged_firma_abs_path($pdo, $idJefe);
            if ($abs && is_readable($abs)) {
                $firmaJefe = $abs;
            }
        } else {
            $rutaFirma = (string)($j['RUTA_FIRMA'] ?? '');
            if ($rutaFirma !== '') {
                $firmaAbs = str_replace('\\', '/', $PROJ_ROOT . $rutaFirma);
                if (is_readable($firmaAbs)) {
                    $firmaJefe = $firmaAbs;
                }
            }
        }
    }

    // 4) Nombre de departamento
    $deptName = (string)(
        $pdo->query("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO=" . (int)$depDoc)
            ->fetchColumn()
        ?: ('Depto #' . $depDoc)
    );

    // 5) Periodos y carga académica (USANDO CARGA_DOCENTE + CARGA_DETALLE)
    $anioActual   = (int)date('Y');
    $anioAnterior = $anioActual - 1;

    // ⚠️ AJUSTA ESTOS ID_PERIODO SEGÚN TU CATÁLOGO ⚠️
    // Ejemplo con tu dato:
    // ID_PERIODO = 1 -> 2024-ENE-JUN
    // ID_PERIODO = 2 -> 2024-AGO-DIC
    $PER_ANT_ENE_JUN = 1; // ENE–JUN del año anterior
    $PER_ANT_AGO_DIC = 2; // AGO–DIC del año anterior

    $filaVacia  = '<tr><td colspan="5">Sin registro</td></tr>';
    $tot_fg     = 0; // suma de HORAS_SEMANA (frente a grupo)
    $tot_global = 0; // suma de TOTAL_HORAS

    $makeRows = function (int $idDoc, int $idPeriodo) use ($pdo, $filaVacia, &$tot_fg, &$tot_global) {
        try {
            $st = $pdo->prepare("
                SELECT 
                    DET.ASIGNATURA,
                    DET.NIVEL,
                    DET.GRUPO,
                    DET.HORAS_SEMANA,
                    DET.TOTAL_HORAS
                FROM dbo.CARGA_DOCENTE  CDOC
                JOIN dbo.CARGA_DETALLE  DET ON DET.ID_CARGA = CDOC.ID_CARGA
                WHERE CDOC.ID_DOCENTE = :doc
                  AND CDOC.ID_PERIODO = :per
                  AND CDOC.ESTADO     = 'VIGENTE'
                  AND DET.ACTIVO      = 1
                ORDER BY DET.ASIGNATURA
            ");
            $st->execute([':doc' => $idDoc, ':per' => $idPeriodo]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) return $filaVacia;

            $html = '';
            foreach ($rows as $x) {
                $hSem   = (float)($x['HORAS_SEMANA'] ?? 0);
                $hTotal = (float)($x['TOTAL_HORAS'] ?? 0);

                $tot_fg     += $hSem;
                $tot_global += $hTotal;

                $html .= '<tr>'
                    . '<td>' . htmlspecialchars((string)$x['ASIGNATURA']) . '</td>'
                    . '<td>' . htmlspecialchars((string)$x['NIVEL'])      . '</td>'
                    . '<td>' . htmlspecialchars((string)$x['GRUPO'])      . '</td>'
                    . '<td class="num">' . $hSem   . '</td>'
                    . '<td class="num">' . $hTotal . '</td>'
                    . '</tr>';
            }
            return $html;
        } catch (Throwable $e) {
            return $filaVacia;
        }
    };

    // ENE–JUN año anterior (ID_PERIODO 1 – ajusta si aplica)
    $filasP1 = $makeRows($idDocente, $PER_ANT_ENE_JUN);
    // AGO–DIC año anterior (ID_PERIODO 2 – ajusta si aplica)
    $filasP2 = $makeRows($idDocente, $PER_ANT_AGO_DIC);

    // 6) Folio y URL de verificación
    $folio = (string)($info['FOLIO'] ?? '');
    if ($folio === '') {
        $folio = 'SIGED-' . $anioActual . '-' . $sid;
    }

    $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio=' . $folio;

    // Fecha tipo "Culiacán, Sin., a 27 de noviembre de 2025"
    setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'es');
    $lugarFecha = 'Culiacán, Sin., a ' . strftime('%d de %B de %Y');

    // 7) Logos
    $ASSETS    = str_replace('\\', '/', realpath($PROJ_ROOT . '/pdf/assets'));
    $logoSep   = ($ASSETS && file_exists($ASSETS . '/logo_sep.png'))   ? $ASSETS . '/logo_sep.png'   : '';
    $logoTecNM = ($ASSETS && file_exists($ASSETS . '/logo_tecnm.png')) ? $ASSETS . '/logo_tecnm.png' : '';

    // 8) Sustituir variables en plantilla
    $html = file_get_contents($tpl);

    $vars = [
        'lugar_fecha'         => $lugarFecha,
        'nombre_departamento' => $deptName,
        'nombre_docente'      => $nombreDocente,
        'rfc_docente'         => $rfc,
        'curp_docente'        => $curp,
        'nombramiento'        => $nombramiento,
        'horas_base'          => $horasBase,
        'antiguedad_texto'    => $antiguedadTxt,
        'anio_actual'         => (string)$anioActual,
        'anio_anterior'       => (string)$anioAnterior,
        'filas_tabla_2024_1'  => $filasP1,
        'filas_tabla_2024_2'  => $filasP2,
        'tot_horas_fg'        => (string)$tot_fg,
        'tot_horas_global'    => (string)$tot_global,
        'firma_jefe_depto'    => $firmaJefe ?: '',
        'nombre_jefe_depto'   => $nombreJefe ?: '',
        'folio'               => $folio,
        'url_verificacion'    => $urlVer,
        'qr_html'             => '',
        'logo_sep'            => $logoSep,
        'logo_tecnm'          => $logoTecNM,
    ];

    foreach ($vars as $k => $v) {
        $html = str_replace(
            ['{{' . $k . '}}', '{' . $k . '}'],
            (string)$v,
            $html
        );
    }

    // 9) Renderizar y guardar PDF
    $pdf->SetFont('helvetica', '', 11);
    $pdf->writeHTML($html, true, false, true, false, '');

    $filename = 'CCA_' . $sid . '.pdf';
    $abs      = $PROJ_ROOT . '/storage/pdfs/' . $filename;
    if (!is_dir(dirname($abs))) {
        @mkdir(dirname($abs), 0775, true);
    }
    $pdf->Output($abs, 'F');

    $rutaWeb = '/siged/storage/pdfs/' . $filename;
    $upd = $pdo->prepare("
        UPDATE dbo.SOLICITUD_DOCUMENTO
        SET RUTA_PDF = :p,
            FOLIO    = :f
        WHERE ID_SOLICITUD = :id
    ");
    $upd->execute([':p' => $rutaWeb, ':f' => $folio, ':id' => $sid]);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename=\"' . $filename . '\"');
    readfile($abs);
    exit;
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

  $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';


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

  $html = str_replace(
    ['{logo_sep}','{logo_tecnm}','{CIUDAD}','{FECHA_LARGA}'],
    [$logoSep,     $logoTecNM,       $ciudad,  $fechaLarga],
    $html
  );

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

   $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

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
    '{logo_sep}'        => $logoSep,
    '{logo_tecnm}'      => $logoTecNM,
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

/* ================== CSE2 (Constancia de Servicios Escolares) ================== */
if ($tipo === 'CSE2') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_servicios_7ma.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_servicios_7ma.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CSE2 no encontrada'); }

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
  $folio  = $row['FOLIO'] ?: ('CSE2-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

   $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

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
    '{logo_sep}'        => $logoSep,
    '{logo_tecnm}'      => $logoTecNM,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  // 7) Guardar/actualizar y servir
  $filename = 'CSE2_'.$sid.'.pdf';
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


/* ================== CSEP (Constancia asignaturas posgrado) ================== */
if ($tipo === 'CSEP') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_asignaturas_posgrado.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_asignaturas_posgrado.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CSEP no encontrada'); }

  // 2) Datos base del docente desde $row (igual que CSE)
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $anio       = date('Y');

  // 3) Aprobador y firma del Jefe de Servicios Escolares
  $stApr = $pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:sid");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CSEP' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
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

  // 4) Asignaturas POSGRADO desde DOCENTE_CARGA (ligadas a ESTA solicitud)
  $q = $pdo->prepare("
    SELECT PERIODO, NIVEL, CLAVE_MATERIA, NOMBRE_MATERIA, ALUMNOS_ATENDIDOS
    FROM dbo.DOCENTE_CARGA
    WHERE ID_SOLICITUD = :sid
      AND UPPER(NIVEL) LIKE '%POSGRADO%'
    ORDER BY ORDEN, ID_CARGA
  ");
  $q->execute([':sid'=>$sid]);
  $rowsCarga = $q->fetchAll(PDO::FETCH_ASSOC);

  $lista = '';
  $totalAlum = 0;

  if ($rowsCarga) {
    $lista .= '<ul>';
    foreach ($rowsCarga as $r) {
      $sem   = trim((string)$r['PERIODO']);
      if ($sem === '') $sem = 'Semestre '.$anio;
      $nom   = htmlspecialchars((string)$r['NOMBRE_MATERIA'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $cla   = htmlspecialchars((string)$r['CLAVE_MATERIA'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $cant  = (int)$r['ALUMNOS_ATENDIDOS'];
      $totalAlum += $cant;

      $lista .= '<li>'
              . htmlspecialchars($sem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
              . ' - Nivel: Posgrado, Asignatura: '.$nom.' (básica/optativa), '
              . 'Clave: '.$cla.', Estudiantes: '.$cant.'.'
              . '</li>';
    }
    $lista .= '</ul>';
  } else {
    $lista = '<p>No se encontraron asignaturas de posgrado capturadas para esta solicitud.</p>';
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.date('d').' de '.$mes.' de '.date('Y');

  $folio  = $row['FOLIO'] ?: ('CSEP-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'       => $lugarFecha,
    '{nombre_docente}'    => $nombreDocente,
    '{expediente}'        => $expediente,
    '{anio}'              => $anio,
    '{lista_asignaturas}' => $lista,
    '{total_alumnos}'     => (string)$totalAlum,
    '{firma_jefe}'        => $firmaJefe,
    '{nombre_jefe}'       => $nombreJefe,
    '{folio}'             => $folio,
    '{url_verificacion}'  => $urlVer,
    '{logo_sep}'          => $logoSep,
    '{logo_tecnm}'        => $logoTecNM,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  // 7) Guardar / actualizar y servir
  $filename = 'CSEP_'.$sid.'.pdf';
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

/* ================== CSEM (Constancia modalidades de atención) ================== */
if ($tipo === 'CSEM') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_modalidades_atencion.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_modalidades_atencion.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CSEM no encontrada'); }

  // 2) Datos base del docente desde $row (igual que CSE/CSEP)
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $anio       = date('Y');

  // 3) Aprobador y firma del Jefe de Servicios Escolares
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD=:sid
  ");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CSEM' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
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

  // 4) Modalidades desde DOCENTE_CARGA_MODALIDAD (ligadas a ESTA solicitud)
  $q = $pdo->prepare("
    SELECT PERIODO, NIVEL, CLAVE_MATERIA, NOMBRE_MATERIA,
           ALUMNOS_ESCOLARIZADA, ALUMNOS_NO_ESCOLARIZADA, ALUMNOS_MIXTA,
           ORDEN, ID_CARGA_MODALIDAD
    FROM dbo.DOCENTE_CARGA_MODALIDAD
    WHERE ID_SOLICITUD = :sid
    ORDER BY ORDEN, ID_CARGA_MODALIDAD
  ");
  $q->execute([':sid'=>$sid]);
  $rowsMod = $q->fetchAll(PDO::FETCH_ASSOC);

  $lista = '';
  $totEsc = 0;
  $totNoEsc = 0;
  $totMix = 0;

  if ($rowsMod) {
    $lista .= '<ul>';
    foreach ($rowsMod as $r) {
      $sem   = trim((string)$r['PERIODO']);
      if ($sem === '') $sem = 'Semestre '.$anio;

      $nivel = htmlspecialchars((string)$r['NIVEL'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $nom   = htmlspecialchars((string)$r['NOMBRE_MATERIA'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $cla   = htmlspecialchars((string)$r['CLAVE_MATERIA'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

      $esc   = (int)$r['ALUMNOS_ESCOLARIZADA'];
      $noesc = (int)$r['ALUMNOS_NO_ESCOLARIZADA'];
      $mix   = (int)$r['ALUMNOS_MIXTA'];

      $totEsc   += $esc;
      $totNoEsc += $noesc;
      $totMix   += $mix;

      $lista .= '<li>'
              . htmlspecialchars($sem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
              . ' - Nivel: '.$nivel
              . ', Asignatura: '.$nom
              . ', Clave: '.$cla
              . ', Modalidad escolarizada: '.$esc.' estudiantes'
              . ', No escolarizada: '.$noesc.' estudiantes'
              . ', Mixta: '.$mix.' estudiantes.'
              . '</li>';
    }
    $lista .= '</ul>';
  } else {
    $lista = '<p>No se encontraron registros de modalidades capturados para esta solicitud.</p>';
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.date('d').' de '.$mes.' de '.date('Y');

  $folio  = $row['FOLIO'] ?: ('CSEM-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'          => $lugarFecha,
    '{nombre_docente}'       => $nombreDocente,
    '{expediente}'           => $expediente,
    '{anio}'                 => $anio,
    '{lista_modalidades}'    => $lista,
    '{total_escolarizada}'   => (string)$totEsc,
    '{total_no_escolarizada}'=> (string)$totNoEsc,
    '{total_mixta}'          => (string)$totMix,
    '{firma_jefe}'           => $firmaJefe,
    '{nombre_jefe}'          => $nombreJefe,
    '{folio}'                => $folio,
    '{url_verificacion}'     => $urlVer,
    '{logo_sep}'             => $logoSep,
    '{logo_tecnm}'           => $logoTecNM,
  ];
  $html = strtr($html, $repl);
  $pdf->SetMargins(15, 0, 15);
  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  // 7) Guardar / actualizar y servir
  $filename = 'CSEM_'.$sid.'.pdf';
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


/* ================== CHA (Horarios de asignaturas de posgrado) ================== */
if ($tipo === 'CHA') {
  $PROJ_ROOT = str_replace('\\', '/', dirname(__DIR__, 3));

  // 1) Localizar plantilla
  $tpl = null;
  foreach ([
      $PROJ_ROOT . '/pdf/plantillas/constancia_horarios_posgrado.html',
      $PROJ_ROOT . '/app/pdf/plantillas/constancia_horarios_posgrado.html'
  ] as $p) {
      if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
      http_response_code(500);
      exit('Plantilla CHA no encontrada');
  }

  // 2) Datos de la solicitud + docente + departamento
  $sqlInfo = $pdo->prepare("
      SELECT 
          S.ID_SOLICITUD,
          S.FOLIO,
          S.RUTA_PDF,
          S.ID_DOCENTE,
          S.ID_DEPARTAMENTO_APROBADOR,
          D.NOMBRE_DOCENTE,
          D.APELLIDO_PATERNO_DOCENTE,
          D.APELLIDO_MATERNO_DOCENTE,
          U.ID_DEPARTAMENTO AS DEP_DOCENTE
      FROM dbo.SOLICITUD_DOCUMENTO S
      JOIN dbo.DOCENTE  D ON D.ID_DOCENTE = S.ID_DOCENTE
      JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
      WHERE S.ID_SOLICITUD = :id
  ");
  $sqlInfo->execute([':id' => $sid]);
  $info = $sqlInfo->fetch(PDO::FETCH_ASSOC);

  if (!$info) {
      http_response_code(404);
      exit('Solicitud / Docente no encontrados para CHA');
  }

  $idDocente = (int)$info['ID_DOCENTE'];
  $depDoc    = (int)($info['DEP_DOCENTE'] ?? 0);
  $depApr    = (int)($info['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
  if ($depApr === 0) $depApr = $depDoc;

  $nombreDocente = trim(
      (string)($info['NOMBRE_DOCENTE'] ?? '') . ' ' .
      (string)($info['APELLIDO_PATERNO_DOCENTE'] ?? '') . ' ' .
      (string)($info['APELLIDO_MATERNO_DOCENTE'] ?? '')
  );

  $anioActual = (int)date('Y');

  // 3) Jefe de departamento firmante (ROL = 2)
  $nombreJefe = '';
  $firmaJefe  = '';

  $sj = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO, RUTA_FIRMA
      FROM dbo.USUARIOS
      WHERE ID_ROL = 2
        AND ID_DEPARTAMENTO = :d
        AND ACTIVO = 1
      ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  if ($j = $sj->fetch(PDO::FETCH_ASSOC)) {
      $idJefe     = (int)$j['ID_USUARIO'];
      $nombreJefe = (string)$j['NOMBRE_COMPLETO'];

      if (function_exists('siged_firma_abs_path')) {
          $abs = siged_firma_abs_path($pdo, $idJefe);
          if ($abs && is_readable($abs)) {
              $firmaJefe = $abs;
          }
      } else {
          $rutaFirma = (string)($j['RUTA_FIRMA'] ?? '');
          if ($rutaFirma !== '') {
              $firmaAbs = str_replace('\\', '/', $PROJ_ROOT . $rutaFirma);
              if (is_readable($firmaAbs)) {
                  $firmaJefe = $firmaAbs;
              }
          }
      }
  }

  // 4) Nombre del departamento
  $deptName = (string)(
      $pdo->query("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO=" . (int)$depDoc)
          ->fetchColumn()
      ?: ('Departamento #' . $depDoc)
  );

  // 5) Horarios por semestre desde DOCENTE_CARGA_HORARIO + DOCENTE_CARGA
  $qh = $pdo->prepare("
      SELECT 
          H.SEMESTRE,
          H.DIAS_SEMANA,
          H.HORA_INICIO,
          H.HORA_FIN,
          H.AULA,
          C.NIVEL,
          C.CLAVE_MATERIA,
          C.NOMBRE_MATERIA,
          C.ALUMNOS_ATENDIDOS
      FROM dbo.DOCENTE_CARGA_HORARIO H
      JOIN dbo.DOCENTE_CARGA C ON C.ID_CARGA = H.ID_CARGA
      WHERE H.ID_SOLICITUD = :sid
      ORDER BY H.SEMESTRE, C.NIVEL, C.NOMBRE_MATERIA, H.ID_HORARIO
  ");
  $qh->execute([':sid' => $sid]);
  $rows = $qh->fetchAll(PDO::FETCH_ASSOC);

  $itemsS1 = [];
  $itemsS2 = [];

  foreach ($rows as $r) {
      $sem   = (int)$r['SEMESTRE'];
      $nivel = (string)$r['NIVEL'];
      $clave = (string)$r['CLAVE_MATERIA'];
      $asig  = (string)$r['NOMBRE_MATERIA'];
      $dias  = (string)$r['DIAS_SEMANA'];
      $hini  = substr((string)$r['HORA_INICIO'], 0, 5);
      $hfin  = substr((string)$r['HORA_FIN'], 0, 5);
      $aula  = (string)($r['AULA'] ?? '');
      $alum  = (int)$r['ALUMNOS_ATENDIDOS'];

      $txt = "Nivel: {$nivel}, Asignatura: {$asig}, Clave: {$clave}, Días: {$dias}, Horario: {$hini}-{$hfin}";
      if ($aula !== '') {
          $txt .= ", Aula: {$aula}";
      }
      $txt .= ", Estudiantes: {$alum}";

      if ($sem === 1) {
          $itemsS1[] = $txt;
      } elseif ($sem === 2) {
          $itemsS2[] = $txt;
      }
  }

  $buildList = function(array $items): string {
      if (!$items) {
          return '<p><em>Sin registros capturados.</em></p>';
      }
      $html = '<ul>';
      foreach ($items as $t) {
          $html .= '<li>' . htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
      }
      $html .= '</ul>';
      return $html;
  };

  $listaS1 = $buildList($itemsS1);
  $listaS2 = $buildList($itemsS2);

  // 6) Folio, fecha y URL de verificación
  $folio = (string)($info['FOLIO'] ?? '');
  if ($folio === '') {
      $folio = 'CHA-' . $anioActual . '-' . $sid;
  }

  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio=' . $folio;

  setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'es');
  $lugarFecha = 'Culiacán, Sin., a ' . (function_exists('strftime') ? strftime('%d de %B de %Y') : date('d \d\e F \d\e Y'));

  // 7) Logos
  $ASSETS    = str_replace('\\', '/', realpath($PROJ_ROOT . '/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS . '/logo_sep.png'))   ? $ASSETS . '/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS . '/logo_tecnm.png')) ? $ASSETS . '/logo_tecnm.png' : '';

  // 8) Sustituir variables en plantilla
  $html = file_get_contents($tpl);

  $vars = [
      'lugar_fecha'         => $lugarFecha,
      'nombre_departamento' => $deptName,
      'nombre_docente'      => $nombreDocente,
      'anio'                => (string)$anioActual,
      'lista_horarios_s1'   => $listaS1,
      'lista_horarios_s2'   => $listaS2,
      'firma_jefe_depto'    => $firmaJefe ?: '',
      'nombre_jefe_depto'   => $nombreJefe ?: '',
      'folio'               => $folio,
      'url_verificacion'    => $urlVer,
      'logo_sep'            => $logoSep,
      'logo_tecnm'          => $logoTecNM,
      'qr_html'             => '',
  ];

  foreach ($vars as $k => $v) {
      $html = str_replace(
          ['{{' . $k . '}}', '{' . $k . '}'],
          (string)$v,
          $html
      );
  }

  // 9) Renderizar y guardar PDF
  $pdf->SetFont('helvetica', '', 11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CHA_' . $sid . '.pdf';
  $abs      = $PROJ_ROOT . '/storage/pdfs/' . $filename;
  if (!is_dir(dirname($abs))) {
      @mkdir(dirname($abs), 0775, true);
  }
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/' . $filename;
  $upd = $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p,
          FOLIO    = :f
      WHERE ID_SOLICITUD = :id
  ");
  $upd->execute([':p' => $rutaWeb, ':f' => $folio, ':id' => $sid]);

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="' . $filename . '"');
  readfile($abs);
  exit;
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

  // Rutas de logos (opcionales)
$ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
$logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
$logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';


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

  $html = str_replace(
    ['{logo_sep}','{logo_tecnm}','{CIUDAD}','{FECHA_LARGA}'],
    [$logoSep,     $logoTecNM,                 $ciudad,  $fechaLarga],
    $html
  );

  $html = strtr($html, $repl);

  // 7) Render y persistencia
  $pdf->SetMargins(22, 0, 22);
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

  
/* ================= ESTR · Estrategias Didácticas ================= */
if ($tipo === 'ESTR') {
  $idSol = (int)($_GET['id'] ?? $_REQUEST['id'] ?? 0);
  if ($idSol <= 0) { http_response_code(400); exit('ID inválido'); }

  // Docente + depto aprobador
  $st = $pdo->prepare("
    SELECT S.ID_SOLICITUD, S.ID_DOCENTE, S.ID_DEPARTAMENTO_APROBADOR, S.FOLIO,
           D.NOMBRE_DOCENTE, D.APELLIDO_PATERNO_DOCENTE, D.APELLIDO_MATERNO_DOCENTE
    FROM dbo.SOLICITUD_DOCUMENTO S
    JOIN dbo.DOCENTE D ON D.ID_DOCENTE = S.ID_DOCENTE
    WHERE S.ID_SOLICITUD = :id
  ");
  $st->execute([':id'=>$idSol]);
  $cab = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cab) { http_response_code(404); exit('Solicitud no encontrada'); }

  $depApr    = (int)$cab['ID_DEPARTAMENTO_APROBADOR'];
  $nombreDoc = trim(($cab['NOMBRE_DOCENTE'] ?? '').' '.($cab['APELLIDO_PATERNO_DOCENTE'] ?? '').' '.($cab['APELLIDO_MATERNO_DOCENTE'] ?? ''));

  // Datos capturados por Jefe
  $r = $pdo->prepare("SELECT TOP 1 * FROM dbo.DOC_DEP_ESTRAT WHERE ID_SOLICITUD=:id ORDER BY ID_ESTRAT DESC");
  $r->execute([':id'=>$idSol]);
  $estr = $r->fetch(PDO::FETCH_ASSOC) ?: ['ASIGNATURA'=>'','ESTRATEGIA'=>'','PROGRAMA_EDUCATIVO'=>'','LUGAR'=>'Culiacán, Sinaloa','FECHA_EMISION'=>null];

  // Dept & jefe
  $depNombre = (string)$pdo->query("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO={$depApr}")->fetchColumn();
  $jefeNombre = (string)$pdo->query("SELECT TOP 1 NOMBRE_COMPLETO FROM dbo.USUARIOS WHERE ID_ROL=2 AND ID_DEPARTAMENTO={$depApr} AND ACTIVO=1 ORDER BY COALESCE(FECHA_FIRMA,'1900-01-01') DESC, ID_USUARIO DESC")->fetchColumn();

  // firma absoluta
  if (!function_exists('siged_firma_abs_path')) require_once __DIR__ . '/../../pdf/firma_pdf.php';
  $idJefe = (int)$pdo->query("SELECT TOP 1 ID_USUARIO FROM dbo.USUARIOS WHERE ID_ROL=2 AND ID_DEPARTAMENTO={$depApr} AND ACTIVO=1 ORDER BY COALESCE(FECHA_FIRMA,'1900-01-01') DESC, ID_USUARIO DESC")->fetchColumn();
  $firmaAbs = $idJefe ? siged_firma_abs_path($pdo,$idJefe) : '';

  // PDF cosmetics
  $pdf->SetTextColor(0,0,0);
  $pdf->SetDrawColor(0,0,0);
  $pdf->SetLineWidth(0.25);
  $pdf->SetMargins(22,18,22);
  $pdf->SetAutoPageBreak(true,18);

  $root   = str_replace('\\','/', realpath(__DIR__.'/../../..'));
  $tpl    = $root.'/pdf/plantillas/constancia_estrategia.html';
  $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  $html = file_exists($tpl) ? file_get_contents($tpl) : '<p>Plantilla no disponible.</p>';

  $fechaEmi = $estr['FECHA_EMISION'] ? (new DateTime($estr['FECHA_EMISION']))->format('d/m/Y') : date('d/m/Y');
  $firmaTag = ($firmaAbs && is_readable($firmaAbs))
    ? '<img src="'.htmlspecialchars($firmaAbs,ENT_QUOTES,'UTF-8').'" style="width:190px;height:auto;display:inline-block;" />'
    : '';

  // folio y verificación
  $folio  = $cab['FOLIO'] ?: ('ESTR-'.date('Y').'-'.$idSol);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $repl = [
    '{logo_sep}'            => $logoSep,
    '{logo_tecnm}'          => $logoTecNM,
    '{CIUDAD}'              => ($estr['LUGAR'] ?: 'Culiacán, Sinaloa'),
    '{FECHA_LARGA}'         => $fechaLarga,
    '{DOCENTE_NOMBRE}'      => $nombreDoc,
    '{ASIGNATURA}'          => (string)$estr['ASIGNATURA'],
    '{ESTRATEGIA}'          => (string)$estr['ESTRATEGIA'],
    '{PROGRAMA}'            => (string)$estr['PROGRAMA_EDUCATIVO'],
    '{DEPTO_NOMBRE}'        => ($depNombre ?: 'Departamento'),
    '{JEFE_NOMBRE}'         => ($jefeNombre ?: 'Jefe de Departamento'),
    '{path_firma_jefe_img}' => $firmaTag,
    '{folio}'               => $folio,
    '{url_verificacion}'    => $urlVer,
  ];
  $html = strtr($html, $repl);

  $pdf->writeHTML($html, true, false, true, false, '');

  // Guardar y servir
  $absOut = $root.'/storage/pdfs/SOL_'.$idSol.'_ESTR.pdf';
  if (!is_dir(dirname($absOut))) @mkdir(dirname($absOut),0777,true);
  $pdf->Output($absOut,'F');

  $webPath = '/siged/storage/pdfs/'.basename($absOut);
  $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p WHERE ID_SOLICITUD=:id")
      ->execute([':p'=>$webPath, ':id'=>$idSol]);

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.basename($absOut).'"');
  readfile($absOut);
  exit;
}

/* ================= TUT · Tutorados (Servicios Escolares) ================= */
if ($tipo === 'TUT') {
  $idSol = (int)($_GET['id'] ?? $_REQUEST['id'] ?? 0);
  if ($idSol <= 0) { http_response_code(400); exit('ID inválido'); }

  // Cabecera: docente + aprobador
  $st = $pdo->prepare("
    SELECT S.ID_SOLICITUD, S.ID_DOCENTE, S.ID_DEPARTAMENTO_APROBADOR, S.FOLIO,
           D.NOMBRE_DOCENTE, D.APELLIDO_PATERNO_DOCENTE, D.APELLIDO_MATERNO_DOCENTE,
           D.MATRICULA, D.CLAVE_EMPLEADO
    FROM dbo.SOLICITUD_DOCUMENTO S
    JOIN dbo.DOCENTE D ON D.ID_DOCENTE = S.ID_DOCENTE
    WHERE S.ID_SOLICITUD = :id
  ");
  $st->execute([':id'=>$idSol]);
  $cab = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cab) { http_response_code(404); exit('Solicitud no encontrada'); }

  $depApr    = (int)$cab['ID_DEPARTAMENTO_APROBADOR']; // debe ser 14
  $nombreDoc = trim(($cab['NOMBRE_DOCENTE'] ?? '').' '.($cab['APELLIDO_PATERNO_DOCENTE'] ?? '').' '.($cab['APELLIDO_MATERNO_DOCENTE'] ?? ''));
  $exped     = (string)($cab['MATRICULA'] ?: $cab['CLAVE_EMPLEADO'] ?: '—');

  // Datos guardados por Jefe
  $r = $pdo->prepare("
    SELECT TOP 1 TUT_EJ_2024, TUT_AD_2024, LUGAR, FECHA_EMISION
    FROM dbo.DOC_SE_TUTORADOS
    WHERE ID_SOLICITUD=:id
    ORDER BY ID_TUT DESC
  ");
  $r->execute([':id'=>$idSol]);
  $tu = $r->fetch(PDO::FETCH_ASSOC) ?: ['TUT_EJ_2024'=>0,'TUT_AD_2024'=>0,'LUGAR'=>'Culiacán, Sinaloa','FECHA_EMISION'=>null];

  // Jefa(e) Servicios Escolares y firma
  if (!function_exists('siged_firma_abs_path')) require_once __DIR__ . '/../../pdf/firma_pdf.php';
  $jefeNombre = (string)$pdo->query("
    SELECT TOP 1 NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL=2 AND ID_DEPARTAMENTO=14 AND ACTIVO=1
    ORDER BY COALESCE(FECHA_FIRMA,'1900-01-01') DESC, ID_USUARIO DESC
  ")->fetchColumn();
  $idJefe = (int)$pdo->query("
    SELECT TOP 1 ID_USUARIO
    FROM dbo.USUARIOS
    WHERE ID_ROL=2 AND ID_DEPARTAMENTO=14 AND ACTIVO=1
    ORDER BY COALESCE(FECHA_FIRMA,'1900-01-01') DESC, ID_USUARIO DESC
  ")->fetchColumn();
  $firmaAbs = $idJefe ? siged_firma_abs_path($pdo,$idJefe) : '';

  
  // PDF cosmetics
  $pdf->SetTextColor(0,0,0);
  $pdf->SetDrawColor(0,0,0);
  $pdf->SetLineWidth(0.25);
  $pdf->SetMargins(22,1,22);
  $pdf->SetAutoPageBreak(true,18);

  $root   = str_replace('\\','/', realpath(__DIR__.'/../../..'));
  $tpl    = $root.'/pdf/plantillas/tutorados.html';
  $ASSETS = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
  $html = file_exists($tpl) ? file_get_contents($tpl) : '<p>Plantilla no disponible.</p>';

  $fechaTxt = $tu['FECHA_EMISION'] ? (new DateTime($tu['FECHA_EMISION']))->format('d/m/Y') : date('d/m/Y');
  $firmaTag = ($firmaAbs && is_readable($firmaAbs))
    ? '<img src="'.htmlspecialchars($firmaAbs,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />'
    : '';

  $folio  = $cab['FOLIO'] ?: ('TUT-'.date('Y').'-'.$idSol);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // Reemplazos
  $repl = [
    '{logo_sep}'                  => $logoSep,
    '{nombre_docente}'            => $nombreDoc,
    '{expediente}'                => $exped,
    '{tutorados_ene_jun_2024}'    => (string)(int)$tu['TUT_EJ_2024'],
    '{tutorados_ago_dic_2024}'    => (string)(int)$tu['TUT_AD_2024'],
    '{nombre_jefa_servicios}'     => ($jefeNombre ?: 'Jefa(e) de Servicios Escolares'),
    '{path_firma_jefe_img}'       => $firmaTag,
    '{firma_sub}'                 => $firma_sub,
  ];
  $html = strtr($html, $repl);

  $pdf->writeHTML($html, true, false, true, false, '');

  // Guardar y servir
  $absOut = $root.'/storage/pdfs/SOL_'.$idSol.'_TUT.pdf';
  if (!is_dir(dirname($absOut))) @mkdir(dirname($absOut),0777,true);
  $pdf->Output($absOut,'F');

  $webPath = '/siged/storage/pdfs/'.basename($absOut);
  $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p WHERE ID_SOLICITUD=:id")
      ->execute([':p'=>$webPath, ':id'=>$idSol]);

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.basename($absOut).'"');
  readfile($absOut);
  exit;
}

/* ============== LAD - Constancia de Liberación de Actividades Docentes ============== */
if ($tipo === 'LAD') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
      $PROJ_ROOT.'/pdf/plantillas/constancia_liberacion.html',
      $PROJ_ROOT.'/app/pdf/plantillas/constancia_liberacion.html'
  ] as $p) {
      if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
      http_response_code(500);
      exit('Plantilla LAD no encontrada');
  }

  // 2) Datos de solicitud + docente
  $infoStmt = $pdo->prepare("
      SELECT S.ID_SOLICITUD,
             S.FOLIO,
             S.RUTA_PDF,
             S.ID_DOCENTE,
             S.ID_DEPARTAMENTO_APROBADOR,
             D.NOMBRE_DOCENTE,
             D.APELLIDO_PATERNO_DOCENTE,
             D.APELLIDO_MATERNO_DOCENTE,
             D.RFC,
             D.CURP,
             U.ID_DEPARTAMENTO AS DEP_DOCENTE
      FROM dbo.SOLICITUD_DOCUMENTO S
      JOIN dbo.DOCENTE  D ON D.ID_DOCENTE = S.ID_DOCENTE
      JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
      WHERE S.ID_SOLICITUD = :sid
  ");
  $infoStmt->execute([':sid'=>$sid]);
  $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
  if (!$info) {
      http_response_code(404);
      exit('Solicitud / Docente no encontrados para LAD');
  }

  $idDocente = (int)$info['ID_DOCENTE'];
  $depDoc    = (int)($info['DEP_DOCENTE'] ?? 0);
  $depApr    = (int)($info['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
  if ($depApr === 0) $depApr = $depDoc;

  $nombreDocente = trim(
      (string)($info['NOMBRE_DOCENTE'] ?? '').' '.
      (string)($info['APELLIDO_PATERNO_DOCENTE'] ?? '').' '.
      (string)($info['APELLIDO_MATERNO_DOCENTE'] ?? '')
  );
  $rfc  = (string)($info['RFC'] ?? '');
  $curp = (string)($info['CURP'] ?? '');

  // 3) Nombre del departamento
  $deptName = (string)(
      $pdo->query("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO=".(int)$depDoc)->fetchColumn()
      ?: ('Depto #'.$depDoc)
  );

  // 4) Cargar registro de liberación
  $stL = $pdo->prepare("
      SELECT TOP 1 *
      FROM dbo.DOCENTE_LAD
      WHERE ID_SOLICITUD = :sid
      ORDER BY ID_LAD DESC
  ");
  $stL->execute([':sid'=>$sid]);
  $lad = $stL->fetch(PDO::FETCH_ASSOC);
  if (!$lad) {
      http_response_code(409);
      exit('No se han capturado datos de liberación para esta solicitud.');
  }

  $semestreTxt = (string)($lad['SEMESTRE'] ?? '');
  $liberado    = (int)($lad['LIBERADO'] ?? 0) === 1;
  $estadoLib   = $liberado ? 'LIBERADO' : 'NO LIBERADO';
  $textoLib    = $liberado
      ? 'Se otorga la liberación de actividades.'
      : 'No se otorga la liberación de actividades.';

  $mk = function(string $val,string $expected): string {
      return strtoupper($val) === $expected ? 'X' : '&nbsp;';
  };

  $acts = [];
  for ($i=1; $i<=7; $i++) {
      $v = strtoupper((string)($lad['ACT'.$i] ?? 'NA'));
      $acts[$i] = [
          'si' => $mk($v,'SI'),
          'no' => $mk($v,'NO'),
          'na' => $mk($v,'NA'),
      ];
  }

  // 5) Folio, fecha, URL verificación
  $anioActual = (int)date('Y');
  $folio = (string)($info['FOLIO'] ?? '');
  if ($folio === '') {
      $folio = 'LAD-'.$anioActual.'-'.$sid;
  }
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  setlocale(LC_TIME,'es_MX.UTF-8','es_MX','es');
  $lugarFecha = 'Culiacán, Sinaloa, a '.strftime('%d de %B de %Y');

  // 6) Firmas
  // ========== JEFE DEPARTAMENTO ==========
  $nombreJefe = ''; 
  $firmaJefe  = '';

  $sj = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
      FROM dbo.USUARIOS
      WHERE ID_ROL = 2
        AND ID_DEPARTAMENTO = :d
        AND ACTIVO = 1
      ORDER BY ID_USUARIO
  ");
  $sj->execute([':d'=>$depApr]);
  if ($j = $sj->fetch(PDO::FETCH_ASSOC)) {
      $nombreJefe = (string)$j['NOMBRE_COMPLETO'];
      if (function_exists('siged_firma_abs_path')) {
          $abs = siged_firma_abs_path($pdo,(int)$j['ID_USUARIO']);
          if ($abs && is_readable($abs)) {
              $firmaJefe = $abs;
          }
      }
  }

  // ========== SUBDIRECCIÓN ACADÉMICA ==========
  $nombreSub = '';
  $firmaSub  = '';

  $qs = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO, RUTA_FIRMA
      FROM dbo.USUARIOS
      WHERE ID_ROL = 3 AND ACTIVO = 1
      ORDER BY ID_USUARIO
  ");
  $qs->execute();
  if ($s = $qs->fetch(PDO::FETCH_ASSOC)) {
      $nombreSub = (string)$s['NOMBRE_COMPLETO'];

      $abs2 = null;
      if (function_exists('siged_firma_abs_path')) {
          $abs2 = siged_firma_abs_path($pdo,(int)$s['ID_USUARIO']);
      }

      if ($abs2 && is_readable($abs2)) {
          // Helper funcionó
          $firmaSub = $abs2;
      } else {
          // Plan B: armar ruta absoluta a partir de RUTA_FIRMA
          $rutaFirma = trim((string)($s['RUTA_FIRMA'] ?? ''));
          if ($rutaFirma !== '') {
              $rutaFirma = str_replace('\\','/',$rutaFirma);
              if ($rutaFirma[0] !== '/') {
                  $rutaFirma = '/'.$rutaFirma;  // "storage/..." -> "/storage/..."
              }

              // Candidato 1: DOCUMENT_ROOT + ruta (para cosas tipo "/siged/storage/...")
              $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
              $cand1   = $docRoot . $rutaFirma;

              if (is_readable($cand1)) {
                  $firmaSub = $cand1;
              } else {
                  // Candidato 2: PROJ_ROOT como raíz del proyecto
                  $rutaRel = $rutaFirma;
                  if (strpos($rutaFirma, '/siged/') === 0) {
                      $rutaRel = substr($rutaFirma, strlen('/siged')); // deja "/storage/..."
                  }

                  $cand2 = rtrim($PROJ_ROOT,'/').$rutaRel;
                  if (is_readable($cand2)) {
                      $firmaSub = $cand2;
                  }
              }
          }
      }
  }

  // 7) Logos y construcción del <img> de la firma del sub
  $ASSETS    = str_replace('\\','/', realpath($PROJ_ROOT.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // Path final para la firma: primero dinámica, luego fallback a PNG estático
  $firmaImgPath = $firmaSub;
  if ($firmaImgPath === '' && $ASSETS && file_exists($ASSETS.'/firma_sub.png')) {
      $firmaImgPath = $ASSETS.'/firma_sub.png';
  }

  $firma_sub = '';
  if ($firmaImgPath !== '') {
      $firma_sub = '<img src="'.htmlspecialchars($firmaImgPath,ENT_QUOTES,'UTF-8').'" '.
                   'class="firma-img" style="position:absolute; top:-20px;" />';
  }

  // 8) Sustituir en plantilla
  $html = file_get_contents($tpl);
  $vars = [
      'logo_sep'            => $logoSep,
      'logo_tecnm'          => $logoTecNM,
      'lugar_fecha'         => $lugarFecha,
      'nombre_departamento' => $deptName,
      'nombre_docente'      => $nombreDocente,
      'depto_docente'       => $deptName,
      'semestre_texto'      => $semestreTxt,
      'rfc_docente'         => $rfc,
      'curp_docente'        => $curp,

      'act1_si' => $acts[1]['si'], 'act1_no' => $acts[1]['no'], 'act1_na' => $acts[1]['na'],
      'act2_si' => $acts[2]['si'], 'act2_no' => $acts[2]['no'], 'act2_na' => $acts[2]['na'],
      'act3_si' => $acts[3]['si'], 'act3_no' => $acts[3]['no'], 'act3_na' => $acts[3]['na'],
      'act4_si' => $acts[4]['si'], 'act4_no' => $acts[4]['no'], 'act4_na' => $acts[4]['na'],
      'act5_si' => $acts[5]['si'], 'act5_no' => $acts[5]['no'], 'act5_na' => $acts[5]['na'],
      'act6_si' => $acts[6]['si'], 'act6_no' => $acts[6]['no'], 'act6_na' => $acts[6]['na'],
      'act7_si' => $acts[7]['si'], 'act7_no' => $acts[7]['no'], 'act7_na' => $acts[7]['na'],

      'texto_liberacion'    => $textoLib,
      'estado_liberacion'   => $estadoLib,
      'folio'               => $folio,
      'url_verificacion'    => $urlVer,

      'firma_jefe_depto'    => $firmaJefe,
      'nombre_jefe_depto'   => $nombreJefe,

      // Subdirección
      'firma_subdirector'   => $firmaSub,   // ruta absoluta (por si se usa directo)
      'nombre_subdirector'  => $nombreSub,  // la plantilla ya la usa
      'firma_sub'           => $firma_sub,  // <img ...> que se inyecta en {firma_sub}
  ];

  foreach ($vars as $k => $v) {
      $html = str_replace(
          ['{{'.$k.'}}','{'.$k.'}'],
          (string)$v,
          $html
      );
  }

  // 9) Render y persistencia
  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html,true,false,true,false,'');
  $pdf->SetMargins(15,1,15);

  $filename = 'LAD_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  if (!is_dir(dirname($abs))) {
      @mkdir(dirname($abs),0775,true);
  }
  $pdf->Output($abs,'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  $upd = $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
  ");
  $upd->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename=\"'.$filename.'\"');
  readfile($abs);
  exit;
}

/* ============== CLFG - Constancia de Liberación de Actividades Frente al Grupo ============== */
if ($tipo === 'CLFG') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
      $PROJ_ROOT.'/pdf/plantillas/constancia_frentegrupo.html',
      $PROJ_ROOT.'/app/pdf/plantillas/constancia_frentegrupo.html'
  ] as $p) {
      if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
      http_response_code(500);
      exit('Plantilla CLFG no encontrada');
  }

  // 2) Datos de solicitud + docente
  $infoStmt = $pdo->prepare("
      SELECT S.ID_SOLICITUD,
             S.FOLIO,
             S.RUTA_PDF,
             S.ID_DOCENTE,
             S.ID_DEPARTAMENTO_APROBADOR,
             D.NOMBRE_DOCENTE,
             D.APELLIDO_PATERNO_DOCENTE,
             D.APELLIDO_MATERNO_DOCENTE,
             D.RFC,
             D.CURP,
             U.ID_DEPARTAMENTO AS DEP_DOCENTE
      FROM dbo.SOLICITUD_DOCUMENTO S
      JOIN dbo.DOCENTE  D ON D.ID_DOCENTE = S.ID_DOCENTE
      JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
      WHERE S.ID_SOLICITUD = :sid
  ");
  $infoStmt->execute([':sid'=>$sid]);
  $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
  if (!$info) {
      http_response_code(404);
      exit('Solicitud / Docente no encontrados para LAD');
  }

  $idDocente = (int)$info['ID_DOCENTE'];
  $depDoc    = (int)($info['DEP_DOCENTE'] ?? 0);
  $depApr    = (int)($info['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
  if ($depApr === 0) $depApr = $depDoc;

  $nombreDocente = trim(
      (string)($info['NOMBRE_DOCENTE'] ?? '').' '.
      (string)($info['APELLIDO_PATERNO_DOCENTE'] ?? '').' '.
      (string)($info['APELLIDO_MATERNO_DOCENTE'] ?? '')
  );
  $rfc  = (string)($info['RFC'] ?? '');
  $curp = (string)($info['CURP'] ?? '');

  // 3) Nombre del departamento
  $deptName = (string)(
      $pdo->query("SELECT NOMBRE_DEPARTAMENTO FROM dbo.DEPARTAMENTO WHERE ID_DEPARTAMENTO=".(int)$depDoc)->fetchColumn()
      ?: ('Depto #'.$depDoc)
  );

  // 4) Cargar registro de liberación
  $stL = $pdo->prepare("
      SELECT TOP 1 *
      FROM dbo.DOCENTE_LAD
      WHERE ID_SOLICITUD = :sid
      ORDER BY ID_LAD DESC
  ");
  $stL->execute([':sid'=>$sid]);
  $lad = $stL->fetch(PDO::FETCH_ASSOC);
  if (!$lad) {
      http_response_code(409);
      exit('No se han capturado datos de liberación para esta solicitud.');
  }

  $semestreTxt = (string)($lad['SEMESTRE'] ?? '');
  $liberado    = (int)($lad['LIBERADO'] ?? 0) === 1;
  $estadoLib   = $liberado ? 'LIBERADO' : 'NO LIBERADO';
  $textoLib    = $liberado
      ? 'Se otorga la liberación de actividades.'
      : 'No se otorga la liberación de actividades.';

  $mk = function(string $val,string $expected): string {
      return strtoupper($val) === $expected ? 'X' : '&nbsp;';
  };

  $acts = [];
  for ($i=1; $i<=7; $i++) {
      $v = strtoupper((string)($lad['ACT'.$i] ?? 'NA'));
      $acts[$i] = [
          'si' => $mk($v,'SI'),
          'no' => $mk($v,'NO'),
          'na' => $mk($v,'NA'),
      ];
  }

  // 5) Folio, fecha, URL verificación
  $anioActual = (int)date('Y');
  $folio = (string)($info['FOLIO'] ?? '');
  if ($folio === '') {
      $folio = 'LAD-'.$anioActual.'-'.$sid;
  }
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  setlocale(LC_TIME,'es_MX.UTF-8','es_MX','es');
  $lugarFecha = 'Culiacán, Sinaloa, a '.strftime('%d de %B de %Y');

  // 6) Firmas
  $nombreJefe = ''; $firmaJefe = '';
  $sj = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
      FROM dbo.USUARIOS
      WHERE ID_ROL = 2
        AND ID_DEPARTAMENTO = :d
        AND ACTIVO = 1
      ORDER BY ID_USUARIO
  ");
  $sj->execute([':d'=>$depApr]);
  if ($j = $sj->fetch(PDO::FETCH_ASSOC)) {
      $nombreJefe = (string)$j['NOMBRE_COMPLETO'];
      if (function_exists('siged_firma_abs_path')) {
          $abs = siged_firma_abs_path($pdo,(int)$j['ID_USUARIO']);
          if ($abs && is_readable($abs)) {
              $firmaJefe = $abs;
          }
      }
  }

  // Ajusta ID_ROL aquí según el rol que uses para Subdirección Académica
  $nombreSub = ''; $firmaSub = '';
  $qs = $pdo->prepare("
      SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
      FROM dbo.USUARIOS
      WHERE ID_ROL = 3 AND ACTIVO = 1
      ORDER BY ID_USUARIO
  ");
  $qs->execute();
  if ($s = $qs->fetch(PDO::FETCH_ASSOC)) {
      $nombreSub = (string)$s['NOMBRE_COMPLETO'];
      if (function_exists('siged_firma_abs_path')) {
          $abs2 = siged_firma_abs_path($pdo,(int)$s['ID_USUARIO']);
          if ($abs2 && is_readable($abs2)) {
              $firmaSub = $abs2;
          }
      }
  }

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($PROJ_ROOT.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" 
  style="width:100px; height:auto; position:absolute; top:-20px;" />';
  

  // 8) Sustituir en plantilla
  $html = file_get_contents($tpl);
  $vars = [
      'logo_sep'           => $logoSep,
      'logo_tecnm'         => $logoTecNM,
      'lugar_fecha'        => $lugarFecha,
      'nombre_departamento'=> $deptName,
      'nombre_docente'     => $nombreDocente,
      'depto_docente'      => $deptName,
      'semestre_texto'     => $semestreTxt,
      'rfc_docente'        => $rfc,
      'curp_docente'       => $curp,

      'act1_si' => $acts[1]['si'], 'act1_no' => $acts[1]['no'], 'act1_na' => $acts[1]['na'],
      'act2_si' => $acts[2]['si'], 'act2_no' => $acts[2]['no'], 'act2_na' => $acts[2]['na'],
      'act3_si' => $acts[3]['si'], 'act3_no' => $acts[3]['no'], 'act3_na' => $acts[3]['na'],
      'act4_si' => $acts[4]['si'], 'act4_no' => $acts[4]['no'], 'act4_na' => $acts[4]['na'],
      'act5_si' => $acts[5]['si'], 'act5_no' => $acts[5]['no'], 'act5_na' => $acts[5]['na'],
      'act6_si' => $acts[6]['si'], 'act6_no' => $acts[6]['no'], 'act6_na' => $acts[6]['na'],
      'act7_si' => $acts[7]['si'], 'act7_no' => $acts[7]['no'], 'act7_na' => $acts[7]['na'],

      'texto_liberacion'   => $textoLib,
      'estado_liberacion'  => $estadoLib,
      'folio'              => $folio,
      'url_verificacion'   => $urlVer,
      'firma_jefe_depto'   => $firmaJefe,
      'nombre_jefe_depto'  => $nombreJefe,
      'firma_subdirector'  => $firmaSub,
      'nombre_subdirector' => $nombreSub,
      'firma_sub'=> $firma_sub,
  ];

  foreach ($vars as $k => $v) {
      $html = str_replace(
          ['{{'.$k.'}}','{'.$k.'}'],
          (string)$v,
          $html
      );
  }

  // 9) Render y persistencia
  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html,true,false,true,false,'');
  $pdf->SetMargins(15,1,15);
  $filename = 'LAD_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  if (!is_dir(dirname($abs))) {
      @mkdir(dirname($abs),0775,true);
  }
  $pdf->Output($abs,'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  $upd = $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
  ");
  $upd->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}

/* ================== CPI (Constancia proyecto integrador) ================== */
if ($tipo === 'CPI') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_proyecto_integrador.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_proyecto_integrador.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CPI no encontrada'); }

  // 2) Datos base del docente desde $row
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $anio       = date('Y');

  // 3) Aprobador y firma (Titular del Departamento Académico)
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD=:sid
  ");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CPI' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
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
  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($j && function_exists('siged_firma_abs_path')) {
    $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    if ($abs && is_readable($abs)) $firmaJefe = $abs;
  }

  // 4) Datos del proyecto integrador desde DOCENTE_PROYECTO_INTEGRADOR
  $qp = $pdo->prepare("
    SELECT NOMBRE_PROYECTO, LISTA_ASIGNATURAS, NIVEL
    FROM dbo.DOCENTE_PROYECTO_INTEGRADOR
    WHERE ID_SOLICITUD = :sid
  ");
  $qp->execute([':sid'=>$sid]);
  $P = $qp->fetch(PDO::FETCH_ASSOC);

  $nombreProyecto = '';
  $listaAsig      = '';
  $nivel          = '';

  if ($P) {
    $nombreProyecto = htmlspecialchars((string)$P['NOMBRE_PROYECTO'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $listaAsig      = htmlspecialchars((string)$P['LISTA_ASIGNATURAS'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $nivel          = htmlspecialchars((string)($P['NIVEL'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  } else {
    $nombreProyecto = 'Proyecto integrador';
    $listaAsig      = 'Asignaturas no capturadas';
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.date('d').' de '.$mes.' de '.date('Y');

  $folio  = $row['FOLIO'] ?: ('CPI-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'      => $lugarFecha,
    '{nombre_docente}'   => $nombreDocente,
    '{expediente}'       => $expediente,
    '{anio}'             => $anio,
    '{nombre_proyecto}'  => $nombreProyecto,
    '{lista_asignaturas}'=> $listaAsig,
    '{firma_jefe}'       => $firmaJefe,
    '{nombre_jefe}'      => $nombreJefe,
    '{folio}'            => $folio,
    '{url_verificacion}' => $urlVer,
    '{logo_sep}'         => $logoSep,
    '{logo_tecnm}'       => $logoTecNM,
    'firma_sub'          => $firma_sub,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  // 7) Guardar / actualizar y servir
  $filename = 'CPI_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET RUTA_PDF=:p, FOLIO=:f WHERE ID_SOLICITUD=:sid")
        ->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename=\"".$filename."\""');
  readfile($abs); exit;
}

/* ================== CMP (Constancia manual de prácticas) ================== */
if ($tipo === 'CMP') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_manual_practicas.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_manual_practicas.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CMP no encontrada'); }

  // 2) Datos base del docente desde $row
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $anio       = date('Y');

  // 3) Aprobador y firma (Titular del Departamento Académico)
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD=:sid
  ");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CMP' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
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
  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($j && function_exists('siged_firma_abs_path')) {
    $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    if ($abs && is_readable($abs)) $firmaJefe = $abs;
  }

  // 4) Datos del manual desde DOCENTE_MANUAL_PRACTICAS
  $qm = $pdo->prepare("
    SELECT NOMBRE_MANUAL
    FROM dbo.DOCENTE_MANUAL_PRACTICAS
    WHERE ID_SOLICITUD = :sid
  ");
  $qm->execute([':sid'=>$sid]);
  $M = $qm->fetch(PDO::FETCH_ASSOC);

  $nombreManual = '';
  if ($M) {
    $nombreManual = htmlspecialchars((string)$M['NOMBRE_MANUAL'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  } else {
    $nombreManual = 'Manual de prácticas';
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.date('d').' de '.$mes.' de '.date('Y');

  $folio  = $row['FOLIO'] ?: ('CMP-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

   // 3b) Firma Subdirección Académica (si la manejan como usuario fijo)
   $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
   $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />'; 
 

  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'      => $lugarFecha,
    '{nombre_docente}'   => $nombreDocente,
    '{expediente}'       => $expediente,
    '{anio}'             => $anio,
    '{nombre_manual}'    => $nombreManual,
    '{firma_jefe}'       => $firmaJefe,
    '{nombre_jefe}'      => $nombreJefe,
    'firma_sub'          => $firma_sub,
    '{folio}'            => $folio,
    '{url_verificacion}' => $urlVer,
    '{logo_sep}'         => $logoSep,
    '{logo_tecnm}'       => $logoTecNM,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');
  $pdf->SetMargins(10, 0, 10); // laterales y superior
  // 7) Guardar / actualizar y servir
  $filename = 'CMP_'.$sid.'.pdf';
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


/* ================== CMDI (Constancia materiales didácticos inclusivos) ================== */
if ($tipo === 'CMDI') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_materiales_inclusivos.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_materiales_inclusivos.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) { http_response_code(500); exit('Plantilla CMDI no encontrada'); }

  // 2) Datos base del docente desde $row
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $anio       = date('Y');

  // 3) Aprobador y firma (Titular del Departamento Académico)
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD=:sid
  ");
  $stApr->execute([':sid'=>$sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CMDI' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
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
  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($j && function_exists('siged_firma_abs_path')) {
    $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    if ($abs && is_readable($abs)) $firmaJefe = $abs;
  }

  // 4) Datos desde DOCENTE_MATERIALES_INCLUSIVOS
  $qm = $pdo->prepare("
    SELECT ENFOQUE, LISTA_PRODUCTOS, DESCRIPCION_IMPACTO
    FROM dbo.DOCENTE_MATERIALES_INCLUSIVOS
    WHERE ID_SOLICITUD = :sid
  ");
  $qm->execute([':sid'=>$sid]);
  $M = $qm->fetch(PDO::FETCH_ASSOC);

  $enfoque   = '';
  $listaProd = '';
  $impacto   = '';

  if ($M) {
    $enfoque = htmlspecialchars((string)$M['ENFOQUE'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $impacto = htmlspecialchars((string)$M['DESCRIPCION_IMPACTO'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Listado: convertimos el texto en <ul><li>...</li></ul>
    $rawList = (string)$M['LISTA_PRODUCTOS'];
    $items = preg_split('/[\r\n;]+/', $rawList);
    $items = array_filter(array_map('trim', $items), static fn($v) => $v !== '');
    if ($items) {
      $listaProd = '<ul>';
      foreach ($items as $it) {
        $listaProd .= '<li>'.htmlspecialchars($it, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
      }
      $listaProd .= '</ul>';
    } else {
      $listaProd = '<p>No se registraron productos.</p>';
    }
  } else {
    $enfoque   = 'intercultural';
    $impacto   = 'Información no capturada.';
    $listaProd = '<p>No se registraron productos.</p>';
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $mes = function_exists('strftime') ? strftime('%B') : date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.date('d').' de '.$mes.' de '.date('Y');

  $folio  = $row['FOLIO'] ?: ('CMDI-'.date('Y').'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
     // 3b) Firma Subdirección Académica (si la manejan como usuario fijo)
     $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
     $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />'; 
   

  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_docente}'     => $nombreDocente,
    '{expediente}'         => $expediente,
    '{anio}'               => $anio,
    '{enfoque}'            => $enfoque,
    '{lista_productos}'    => $listaProd,
    '{descripcion_impacto}'=> $impacto,
    '{firma_jefe}'         => $firmaJefe,
    '{nombre_jefe}'        => $nombreJefe,
    'firma_sub'            => $firma_sub,
    '{folio}'              => $folio,
    '{url_verificacion}'   => $urlVer,
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('helvetica','',11);
  $pdf->writeHTML($html, true, false, true, false, '');
  $pdf->SetMargins(10, 0, 10); // laterales y superior
  // 7) Guardar / actualizar y servir
  $filename = 'CMDI_'.$sid.'.pdf';
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

/* ============ CCID (Constancia comisión de instructor docente) ============ */
if ($tipo === 'CCID') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_comision_instructor.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_comision_instructor.html'
  ] as $p) { if (is_readable($p)) { $tpl = $p; break; } }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CCID no encontrada');
  }

  // 2) Datos base del docente desde $row
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $rfcCurp    = (string)($row['RFC'] ?? $row['CURP'] ?? '—');
  $anio       = date('Y');

  // 3) Datos de la comisión desde DOCENTE_COMISION_INSTRUCTOR
  $qc = $pdo->prepare("
    SELECT NOMBRE_CURSO, TIPO_CURSO, NUMERO_HORAS, NUMERO_OFICIO
    FROM dbo.DOCENTE_COMISION_INSTRUCTOR
    WHERE ID_SOLICITUD = :sid
  ");
  $qc->execute([':sid' => $sid]);
  $C = $qc->fetch(PDO::FETCH_ASSOC);

  if (!$C) {
    http_response_code(400);
    exit('No hay datos de comisión de instructor capturados para esta solicitud');
  }

  $nombreCurso = trim((string)$C['NOMBRE_CURSO']);
  $tipoCurso   = trim((string)$C['TIPO_CURSO']);     // 'formación docente' / 'actualización profesional'
  $numHoras    = (int)$C['NUMERO_HORAS'];
  $numOficio   = trim((string)$C['NUMERO_OFICIO']);

  // Regla de negocio mínima: 30 horas
  if ($numHoras < 30) {
    // Si quieres bloquear duro, descomenta estas dos líneas:
    // http_response_code(400);
    // exit('La duración del curso debe ser de al menos 30 horas (criterio 1.2.2.1).');
  }

  // 4) Aprobador y emisor (jefe de departamento)
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CCID' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  // Emisor: jefe del departamento aprobador (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $em = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreEmisor = $em ? (string)$em['NOMBRE_COMPLETO'] : 'Nombre del Emisor';
  // Puedes ajustar este cargo a lo que realmente usen:
  $cargoEmisor  = 'JEFA(E) DE DESARROLLO ACADÉMICO';

  $firmaEmisor  = '';
  if ($em && function_exists('siged_firma_abs_path')) {
    $absE = siged_firma_abs_path($pdo, (int)$em['ID_USUARIO']);
    if ($absE && is_readable($absE)) {
      $firmaEmisor = $absE;
    }
  }

  // Director(a) del plantel (asumimos ID_ROL=1)
  $sd = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 1 AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sd->execute();
  $dir = $sd->fetch(PDO::FETCH_ASSOC);

  $nombreDirector = $dir ? (string)$dir['NOMBRE_COMPLETO'] : 'Nombre del Director(a) del Plantel';
  $firmaDirector  = '';
  if ($dir && function_exists('siged_firma_abs_path')) {
    $absD = siged_firma_abs_path($pdo, (int)$dir['ID_USUARIO']);
    if ($absD && is_readable($absD)) {
      $firmaDirector = $absD;
    }
  }

  // 5) Lugar y fecha (en texto)
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  // Si quieres parametrizar la ciudad/estado, cámbialo aquí
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';

  // 7) Folio y URL de verificación (si usas doc_verify)
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CCID-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 8) Reemplazo de placeholders en la plantilla
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'        => $logoSep,
    '{logo_tecnm}'      => $logoTecNM,
    '{lugar_fecha}'     => $lugarFecha,
    '{numero_oficio}'   => htmlspecialchars($numOficio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'  => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'      => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{rfc_curp}'        => htmlspecialchars($rfcCurp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_curso}'    => htmlspecialchars($nombreCurso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{tipo_curso}'      => htmlspecialchars($tipoCurso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{num_horas}'       => (string)$numHoras,

    '{cargo_emisor}'    => htmlspecialchars($cargoEmisor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{nombre_emisor}'   => htmlspecialchars($nombreEmisor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_emisor}'    => $firmaEmisor,

    '{nombre_director}' => htmlspecialchars($nombreDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_sub}'  => $firma_sub,

    // Por si quieres mostrar la URL de verificación en algún lugar luego:
    '{url_verificacion}' => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CCID_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}



/* ============ CCUI (Constancia curso impartido TecNM) ============ */
if ($tipo === 'CCUI') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_curso_impartido.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_curso_impartido.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CCUI no encontrada');
  }

  // 2) Datos base del docente desde $row
  $expediente   = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc    = $nombreDocente;
  $anio         = date('Y');
  $idDocente    = (int)($row['ID_DOCENTE'] ?? $row['ID_EMPLEADO'] ?? 0);

  // 3) Datos del curso impartido
  $qc = $pdo->prepare("
    SELECT NOMBRE_CURSO, NUMERO_REGISTRO, FECHA_INICIO, FECHA_FIN, NUMERO_HORAS
    FROM dbo.DOCENTE_CURSO_IMPARTIDO
    WHERE ID_SOLICITUD = :sid
  ");
  $qc->execute([':sid' => $sid]);
  $C = $qc->fetch(PDO::FETCH_ASSOC);

  if (!$C) {
    http_response_code(400);
    exit('No hay datos de curso impartido capturados para esta solicitud');
  }

  $nombreCurso   = trim((string)$C['NOMBRE_CURSO']);
  $numRegistro   = trim((string)$C['NUMERO_REGISTRO']);
  $numHoras      = (int)$C['NUMERO_HORAS'];
  $fIni          = (string)$C['FECHA_INICIO'];
  $fFin          = (string)$C['FECHA_FIN'];

  // Regla de negocio mínima: 30 horas
  if ($numHoras < 30) {
    // Si quieres bloquear duro, descomenta:
    // http_response_code(400);
    // exit('La duración del curso debe ser de al menos 30 horas (criterio 1.2.2.2).');
  }

  // Formato de fechas (dd/mm/aaaa)
  $fmtFecha = function (?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    if (!$ts) return $d;
    return date('d/m/Y', $ts);
  };
  $fechaIni = $fmtFecha($fIni);
  $fechaFin = $fmtFecha($fFin);

  // 4) Departamento del docente (para el jefe de departamento)
  // Ajusta estos campos a tu modelo real; aquí asumimos que en $row viene el ID_DEPARTAMENTO del docente.
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);

  // Si no hay departamento en $row, caemos al aprobador por defecto
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depDoc <= 0) {
    $depDoc = $depApr;
  }

  // 5) Jefe del Departamento Académico del docente (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Subdirector(a) Académico(a)
  // Ajusta ID_ROL=3 al rol real que usen para Subdirección Académica
  $ss = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 3 AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $ss->execute();
  $sub = $ss->fetch(PDO::FETCH_ASSOC);

  $nombreSub = $sub ? (string)$sub['NOMBRE_COMPLETO'] : 'Subdirector(a) Académico(a)';

  // 7) Lugar y fecha en texto
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 8) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 9) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';


  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';

  // 10) Folio y URL de verificación (si aplican)
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CCUI-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 11) Cargo emisor (para el texto "El que suscribe...")
  $cargoEmisor = 'Subdirector(a) Académico(a)';

  // 12) Reemplazo de placeholders
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{cargo_emisor}'       => htmlspecialchars($cargoEmisor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_curso}'       => htmlspecialchars($nombreCurso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{numero_registro}'    => htmlspecialchars($numRegistro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_inicio}'       => htmlspecialchars($fechaIni, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_fin}'          => htmlspecialchars($fechaFin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{num_horas}'          => (string)$numHoras,

    // Firmas
    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{nombre_sub}'         => htmlspecialchars($nombreSub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_sub}'          => $firma_sub,

    // Por si en algún momento decides mostrar la URL
    '{url_verificacion}'   => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 13) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CCUI_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}

/* ============ CDPC (Comisión Diplomado Pensamiento Crítico) ============ */
if ($tipo === 'CDPC') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/comision_diplomado_pensamiento_critico.html',
    $PROJ_ROOT.'/app/pdf/plantillas/comision_diplomado_pensamiento_critico.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CDPC no encontrada');
  }

  // 2) Datos base del docente desde $row
  $expediente   = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc    = $nombreDocente;
  $anio         = date('Y');

  // 3) Departamento del docente
  // Ajusta este campo al que realmente uses para el docente
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);

  // Si no viene en $row, usamos el aprobador de la solicitud como fallback
  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 4) Jefe del Departamento (ID_ROL=2) de ese departamento
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) de Departamento';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }
 
// 5) Firma del docente (buscando en dbo.USUARIOS por ROL = 1)
$firmaDocente = '';

// Buscar usuario docente por rol = 1 y nombre completo
$uDocStmt = $pdo->prepare("
  SELECT TOP 1 ID_USUARIO, RUTA_FIRMA
  FROM dbo.USUARIOS
  WHERE ID_ROL = 1
    AND ACTIVO = 1
    AND NOMBRE_COMPLETO = :nom
  ORDER BY ID_USUARIO
");
$uDocStmt->execute([
  ':nom' => $nombreDocente,   // el mismo que usas en {nombre_docente}
]);

$uDoc = $uDocStmt->fetch(PDO::FETCH_ASSOC);

if ($uDoc) {
  $rutaRel = trim((string)$uDoc['RUTA_FIRMA']);  // p.ej. "storage/firmas/firma_10_20251130_035358.png"
  if ($rutaRel !== '') {
    // Armamos ruta absoluta usando el root del proyecto
    $absD = rtrim($PROJ_ROOT, '/').'/'.ltrim($rutaRel, '/');
    if (is_readable($absD)) {
      $firmaDocente = $absD;
    }
  }
}
// Si $firmaDocente queda vacío, sólo se verá la línea + nombre, para firma autógrafa.

  // 6) Lugar y fecha en texto
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 7) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 8) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 9) Folio y URL de verificación (opcional)
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CDPC-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 10) Cargo emisor (quien “suscribe” el oficio)
  $cargoEmisor = 'Jefe(a) del Departamento';

  // 11) Reemplazo de placeholders
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{cargo_emisor}'       => htmlspecialchars($cargoEmisor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{firma_docente}'      => $firmaDocente,

    // Por si en algún momento decides mostrar/usar esta URL:
    '{url_verificacion}'   => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 12) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CDPC_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}


/* ============ CIPC (Constancia Instructor Pensamiento Crítico) ============ */
if ($tipo === 'CIPC') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cipc.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cipc.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CIPC no encontrada');
  }

  // 2) Datos base del docente
  $expediente   = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc    = $nombreDocente;
  $anio         = date('Y');

  // 3) Datos del módulo del diplomado
  $qm = $pdo->prepare("
    SELECT NOMBRE_MODULO, HORAS_IMPARTIDAS
    FROM dbo.DOCENTE_DIPLOMADO_PC
    WHERE ID_SOLICITUD = :sid
  ");
  $qm->execute([':sid' => $sid]);
  $M = $qm->fetch(PDO::FETCH_ASSOC);

  if (!$M) {
    http_response_code(400);
    exit('No hay datos de módulo del diplomado capturados para esta solicitud (CIPC).');
  }

  $nombreModulo   = trim((string)$M['NOMBRE_MODULO']);
  $horasImpartidas = (int)$M['HORAS_IMPARTIDAS'];

  // Regla de negocio: mínimo 40 horas
  if ($horasImpartidas < 40) {
    // Puedes bloquear duro, o solo dejarlo documentado.
    // http_response_code(400);
    // exit('Las horas impartidas deben ser al menos 40 para la evidencia CIPC (criterio 1.2.2.3).');
  }

  // 4) Departamento del docente para firmas
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);

  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 5) Jefe del Departamento Académico (ID_ROL = 2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Subdirector(a) Académico(a) (ajusta ID_ROL al que corresponda)
  $ss = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 3 AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $ss->execute();
  $sub = $ss->fetch(PDO::FETCH_ASSOC);

  $nombreSub = $sub ? (string)$sub['NOMBRE_COMPLETO'] : 'Subdirector(a) Académico(a)';
  $firmaSub  = '';
  if ($sub && function_exists('siged_firma_abs_path')) {
    $absS = siged_firma_abs_path($pdo, (int)$sub['ID_USUARIO']);
    if ($absS && is_readable($absS)) {
      $firmaSub = $absS;
    }
  }

  // 7) Lugar y fecha
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 8) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 9) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists(filename: $ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
  // 10) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CIPC-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 11) Reemplazo de placeholders
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_modulo}'      => htmlspecialchars($nombreModulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{horas_impartidas}'   => (string)$horasImpartidas,

    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{nombre_sub}'         => htmlspecialchars($nombreSub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_sub}'          => $firma_sub,
    '{url_verificacion}'   => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 12) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CIPC_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}


/* ============ CPFT (Constancia Diplomado Formación de Tutores) ============ */
if ($tipo === 'CPFT') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cpft.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cpft.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CPFT no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del módulo del diplomado de tutores
  $qt = $pdo->prepare("
    SELECT NOMBRE_MODULO, HORAS_IMPARTIDAS
    FROM dbo.DOCENTE_DIPLOMADO_TUTORES
    WHERE ID_SOLICITUD = :sid
  ");
  $qt->execute([':sid' => $sid]);
  $T = $qt->fetch(PDO::FETCH_ASSOC);

  if (!$T) {
    http_response_code(400);
    exit('No hay datos del módulo del diplomado de tutores capturados para esta solicitud (CPFT).');
  }

  $nombreModulo    = trim((string)$T['NOMBRE_MODULO']);
  $horasImpartidas = (int)$T['HORAS_IMPARTIDAS'];

  // 4) Departamento del docente
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);

  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 5) Jefe del Departamento (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Firma del DOCENTE (igual patrón que lo que ya te funcionó)
  $firmaDocente = '';

  $uDocStmt = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, RUTA_FIRMA
    FROM dbo.USUARIOS
    WHERE ID_ROL = 1
      AND ACTIVO = 1
      AND NOMBRE_COMPLETO = :nom
    ORDER BY ID_USUARIO
  ");
  $uDocStmt->execute([
    ':nom' => $nombreDoc,   // mismo nombre que se imprime
  ]);
  $uDoc = $uDocStmt->fetch(PDO::FETCH_ASSOC);

  if ($uDoc) {
    $rutaRel = trim((string)$uDoc['RUTA_FIRMA']); // p.ej. storage/firmas/firma_10_...
    if ($rutaRel !== '') {
      $absD = rtrim($PROJ_ROOT, '/').'/'.ltrim($rutaRel, '/');
      if (is_readable($absD)) {
        $firmaDocente = $absD;
      }
    }
  }
  // Si queda vacío, firma autógrafa sobre la línea.

  // 7) Lugar y fecha
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 8) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 9) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 10) Folio y URL verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CPFT-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 11) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_modulo}'      => htmlspecialchars($nombreModulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{horas_impartidas}'   => (string)$horasImpartidas,

    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{firma_docente}'      => $firmaDocente,
    '{url_verificacion}'   => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 12) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CPFT_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename=\"'.$filename.'\"');
  readfile($abs);
  exit;
}

/* ============ CDRE (Constancia Diplomado Recursos Educativos) ============ */
if ($tipo === 'CDRE') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cdre.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cdre.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CDRE no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del módulo del diplomado REA
  $qr = $pdo->prepare("
    SELECT NOMBRE_MODULO
    FROM dbo.DOCENTE_DIPLOMADO_REA
    WHERE ID_SOLICITUD = :sid
  ");
  $qr->execute([':sid' => $sid]);
  $R = $qr->fetch(PDO::FETCH_ASSOC);

  if (!$R) {
    http_response_code(400);
    exit('No hay datos del módulo del diplomado REA capturados para esta solicitud (CDRE).');
  }

  $nombreModulo = trim((string)$R['NOMBRE_MODULO']);

  // 4) Departamento del docente
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);
  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 5) Jefe del Departamento (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Firma del DOCENTE por RUTA_FIRMA en USUARIOS (ROL=1)
  $firmaDocente = '';

  $uDocStmt = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, RUTA_FIRMA
    FROM dbo.USUARIOS
    WHERE ID_ROL = 1
      AND ACTIVO = 1
      AND NOMBRE_COMPLETO = :nom
    ORDER BY ID_USUARIO
  ");
  $uDocStmt->execute([
    ':nom' => $nombreDoc,
  ]);
  $uDoc = $uDocStmt->fetch(PDO::FETCH_ASSOC);

  if ($uDoc) {
    $rutaRel = trim((string)$uDoc['RUTA_FIRMA']); // p.ej. storage/firmas/firma_10_...
    if ($rutaRel !== '') {
      $absD = rtrim($PROJ_ROOT, '/').'/'.ltrim($rutaRel, '/');
      if (is_readable($absD)) {
        $firmaDocente = $absD;
      }
    }
  }
  // Si queda vacío, el docente firma autógrafamente sobre la línea.

  // 7) Lugar y fecha
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 8) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 9) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 10) Folio + verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CDRE-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 11) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_modulo}'      => htmlspecialchars($nombreModulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{firma_docente}'      => $firmaDocente,
    '{url_verificacion}'   => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 12) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CDRE_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}

/* ============ CDEI (Constancia Diplomado Educación Inclusiva) ============ */
if ($tipo === 'CDEI') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cdei.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cdei.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CDEI no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del módulo de Educación Inclusiva
  $qe = $pdo->prepare("
    SELECT NOMBRE_MODULO, HORAS_IMPARTIDAS
    FROM dbo.DOCENTE_DIPLOMADO_EDUCACION_INCLUSIVA
    WHERE ID_SOLICITUD = :sid
  ");
  $qe->execute([':sid' => $sid]);
  $E = $qe->fetch(PDO::FETCH_ASSOC);

  if (!$E) {
    http_response_code(400);
    exit('No hay datos del módulo del Diplomado en Educación Inclusiva para esta solicitud (CDEI).');
  }

  $nombreModulo    = trim((string)$E['NOMBRE_MODULO']);
  $horasImpartidas = (int)$E['HORAS_IMPARTIDAS'];

  // 4) Departamento del docente
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);
  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 5) Jefe del Departamento (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Firma del DOCENTE por RUTA_FIRMA en USUARIOS (ROL=1)
  $firmaDocente = '';

  $uDocStmt = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, RUTA_FIRMA
    FROM dbo.USUARIOS
    WHERE ID_ROL = 1
      AND ACTIVO = 1
      AND NOMBRE_COMPLETO = :nom
    ORDER BY ID_USUARIO
  ");
  $uDocStmt->execute([
    ':nom' => $nombreDoc,
  ]);
  $uDoc = $uDocStmt->fetch(PDO::FETCH_ASSOC);

  if ($uDoc) {
    $rutaRel = trim((string)$uDoc['RUTA_FIRMA']); // p.ej. storage/firmas/firma_10_...
    if ($rutaRel !== '') {
      $absD = rtrim($PROJ_ROOT, '/').'/'.ltrim($rutaRel, '/');
      if (is_readable($absD)) {
        $firmaDocente = $absD;
      }
    }
  }

  // 7) Lugar y fecha
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 8) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 9) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
  // 10) Folio + verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CDEI-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 11) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_modulo}'      => htmlspecialchars($nombreModulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{horas_impartidas}'   => (string)$horasImpartidas,

    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{firma_docente}'      => $firmaDocente,
    '{url_verificacion}'   => $urlVer,
    'firma_sub'            => $firma_sub,
  ];

  $html = strtr($html, $repl);

  // 12) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CDEI_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}

/* ============ CDPE (Comisión Diplomados Estratégicos) ============ */
if ($tipo === 'CDPE') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/oficio_cdpe.html',
    $PROJ_ROOT.'/app/pdf/plantillas/oficio_cdpe.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CDPE no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del diplomado estratégico
  $qs = $pdo->prepare("
    SELECT NOMBRE_DIPLOMADO, NOMBRE_PROYECTO
    FROM dbo.DOCENTE_DIPLOMADO_ESTRATEGICO
    WHERE ID_SOLICITUD = :sid
  ");
  $qs->execute([':sid' => $sid]);
  $D = $qs->fetch(PDO::FETCH_ASSOC);

  if (!$D) {
    http_response_code(400);
    exit('No hay datos del diplomado estratégico capturados para esta solicitud (CDPE).');
  }

  $nombreDiplomado = trim((string)$D['NOMBRE_DIPLOMADO']);
  $nombreProyecto  = trim((string)$D['NOMBRE_PROYECTO']);

  // 4) Departamento del docente
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);
  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 5) Jefe del Departamento (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Firma del DOCENTE por RUTA_FIRMA en USUARIOS (ROL=1)
  $firmaDocente = '';

  $uDocStmt = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, RUTA_FIRMA
    FROM dbo.USUARIOS
    WHERE ID_ROL = 1
      AND ACTIVO = 1
      AND NOMBRE_COMPLETO = :nom
    ORDER BY ID_USUARIO
  ");
  $uDocStmt->execute([
    ':nom' => $nombreDoc,
  ]);
  $uDoc = $uDocStmt->fetch(PDO::FETCH_ASSOC);

  if ($uDoc) {
    $rutaRel = trim((string)$uDoc['RUTA_FIRMA']); // p.ej. storage/firmas/firma_10_...
    if ($rutaRel !== '') {
      $absD = rtrim($PROJ_ROOT, '/').'/'.ltrim($rutaRel, '/');
      if (is_readable($absD)) {
        $firmaDocente = $absD;
      }
    }
  }

  // 7) Lugar y fecha
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 8) Nombre de la institución
  $nombreInstitucion = 'INSTITUTO TECNOLÓGICO DE CULIACÁN';

  // 9) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 10) Folio + verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CDPE-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 11) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'           => $logoSep,
    '{logo_tecnm}'         => $logoTecNM,
    '{lugar_fecha}'        => $lugarFecha,
    '{nombre_institucion}' => htmlspecialchars($nombreInstitucion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_docente}'     => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'         => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_diplomado}'   => htmlspecialchars($nombreDiplomado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{nombre_proyecto}'    => htmlspecialchars($nombreProyecto,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'        => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'         => $firmaJefe,

    '{firma_docente}'      => $firmaDocente,
    '{url_verificacion}'   => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 12) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CDPE_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF'] !== $rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF = :p, FOLIO = :f
      WHERE ID_SOLICITUD = :sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs);
  exit;
}

/* ================== CAE (Constancia Acta Examen) ================== */
if ($tipo === 'CAE') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cae.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cae.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CAE no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del acta de examen
  $qa = $pdo->prepare("
    SELECT TIPO_EXAMEN, FECHA_EXAMEN, NOMBRE_ESTUDIANTE, PROGRAMA, ROL_PARTICIPACION
    FROM dbo.DOCENTE_ACTA_EXAMEN
    WHERE ID_SOLICITUD = :sid
  ");
  $qa->execute([':sid' => $sid]);
  $A = $qa->fetch(PDO::FETCH_ASSOC);

  if (!$A) {
    http_response_code(400);
    exit('No hay datos capturados del acta de examen para esta solicitud (CAE).');
  }

  $tipoExamen  = trim((string)$A['TIPO_EXAMEN']);       // profesional / de grado
  $fechaExam   = trim((string)$A['FECHA_EXAMEN']);      // texto como lo capturó el jefe
  $nomEst      = trim((string)$A['NOMBRE_ESTUDIANTE']);
  $programa    = trim((string)$A['PROGRAMA']);
  $rolPart     = trim((string)$A['ROL_PARTICIPACION']);

  // 4) Departamento del docente (para jefe académico)
  $depDoc = (int)($row['ID_DEPARTAMENTO'] ?? 0);
  if ($depDoc <= 0) {
    $stApr = $pdo->prepare("
      SELECT ID_DEPARTAMENTO_APROBADOR
      FROM dbo.SOLICITUD_DOCUMENTO
      WHERE ID_SOLICITUD = :sid
    ");
    $stApr->execute([':sid' => $sid]);
    $depDoc = (int)($stApr->fetchColumn() ?: 0);
  }

  // 5) Jefe del Departamento Académico (ID_ROL=2)
  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depDoc]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 6) Lugar y fecha de emisión (no confundir con fecha del examen)
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 7) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CAE-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 8) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';


  // 9) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'          => $logoSep,
    '{logo_tecnm}'        => $logoTecNM,
    '{folio}'             => $folio,
    '{lugar_fecha}'       => $lugarFecha,

    '{nombre_docente}'    => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'        => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{tipo_examen}'       => htmlspecialchars($tipoExamen, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_examen}'      => htmlspecialchars($fechaExam, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{nombre_estudiante}' => htmlspecialchars($nomEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{programa}'          => htmlspecialchars($programa, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{rol_participacion}' => htmlspecialchars($rolPart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'       => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'        => $firmaJefe,
    'firma_sub'           => $firma_sub,
    '{url_verificacion}'  => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 10) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CAE_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== CST (Constancia Sinodal Titulación) ================== */
if ($tipo === 'CST') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cst.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cst.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CST no encontrada');
  }

  // 2) Datos base del docente desde $row
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Sinodalías ligadas a ESTA solicitud
  $q = $pdo->prepare("
    SELECT TIPO_EXAMEN,
           NOMBRE_ESTUDIANTE,
           PROGRAMA_EDUCATIVO,
           FOLIO_ACTA,
           FECHA_EXAMEN,
           ROL_PARTICIPACION,
           ORDEN
    FROM dbo.DOCENTE_SINODALIA_TITULACION
    WHERE ID_SOLICITUD = :sid
    ORDER BY ORDEN, ID_SINODALIA
  ");
  $q->execute([':sid' => $sid]);
  $rowsSin = $q->fetchAll(PDO::FETCH_ASSOC);

  $lista = '';
  $totalEx = 0;

  if ($rowsSin) {
    $lista .= '<ul>';
    foreach ($rowsSin as $r) {
      $tipo  = htmlspecialchars((string)$r['TIPO_EXAMEN'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');          // grado/profesional
      $est   = htmlspecialchars((string)$r['NOMBRE_ESTUDIANTE'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $prog  = htmlspecialchars((string)$r['PROGRAMA_EDUCATIVO'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $folio = htmlspecialchars((string)$r['FOLIO_ACTA'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $fec   = htmlspecialchars((string)$r['FECHA_EXAMEN'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $rol   = htmlspecialchars((string)$r['ROL_PARTICIPACION'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

      $lista .= '<li>'
              . 'Examen '.$tipo.' del(la) estudiante '.$est
              . ', programa educativo '.$prog
              . ', folio del acta '.$folio
              . ', realizado el día '.$fec
              . ', fungiendo como '.$rol.'.'
              . '</li>';
      $totalEx++;
    }
    $lista .= '</ul>';
  } else {
    $lista = '<p>No se encontraron sinodalías capturadas para esta solicitud.</p>';
  }

  // 4) Jefe de Servicios Escolares (por depto aprobador o fijo)
  $depApr = 0;
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CST' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $j = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $j ? (string)$j['NOMBRE_COMPLETO'] : 'Jefe(a) del Departamento de Servicios Escolares';
  $firmaJefe  = '';
  if ($j && function_exists('siged_firma_abs_path')) {
    $abs = siged_firma_abs_path($pdo, (int)$j['ID_USUARIO']);
    if ($abs && is_readable($abs)) $firmaJefe = $abs;
  }

  // 5) Lugar/fecha + folio + URL de verificación
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  $folio  = $row['FOLIO'] ?: ('CST-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 6) Reemplazo y render
  $html = file_get_contents($tpl);
  $repl = [
    '{lugar_fecha}'      => $lugarFecha,
    '{nombre_docente}'   => $nombreDoc,
    '{expediente}'       => $expediente,
    '{lista_sinodalias}' => $lista,
    '{total_examenes}'   => (string)$totalEx,
    '{firma_jefe}'       => $firmaJefe,
    '{nombre_jefe}'      => $nombreJefe,
    '{folio}'            => $folio,
    '{url_verificacion}' => $urlVer,
    '{logo_sep}'         => $logoSep,
    '{logo_tecnm}'       => $logoTecNM,
  ];
  $html = strtr($html, $repl);

  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CST_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([':p'=>$rutaWeb, ':f'=>$folio, ':sid'=>$sid]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}


/* ================== CCO (Constancia asesoría concursos) ================== */
if ($tipo === 'CCO') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cco.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cco.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CCO no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos de asesoría (un registro por solicitud)
  $qa = $pdo->prepare("
    SELECT TIPO_EVENTO, NOMBRE_EVENTO, LUGAR_EVENTO, FECHA_INICIO, FECHA_FIN
    FROM dbo.DOCENTE_ASESORIA_CONCURSO
    WHERE ID_SOLICITUD = :sid
  ");
  $qa->execute([':sid' => $sid]);
  $C = $qa->fetch(PDO::FETCH_ASSOC);

  if (!$C) {
    http_response_code(400);
    exit('No hay datos capturados de asesoría en concurso/evento para esta solicitud (CCO).');
  }

  $tipoEvento   = trim((string)$C['TIPO_EVENTO']);    // evento / concurso
  $nombreEvento = trim((string)$C['NOMBRE_EVENTO']);
  $lugarEvento  = trim((string)$C['LUGAR_EVENTO']);
  $fechaIni     = trim((string)$C['FECHA_INICIO']);
  $fechaFin     = trim((string)$C['FECHA_FIN']);

  // 4) Departamento aprobador -> jefe académico
  $depApr = 0;
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CCO' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 5) Lugar y fecha de emisión
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'noviembre'
  ];
  // pequeño fix: mes 12 debe ser 'diciembre'
  $meses[12] = 'diciembre';

  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');

  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CCO-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
 
  // 8) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'        => $logoSep,
    '{logo_tecnm}'      => $logoTecNM,
    '{folio}'           => $folio,
    '{lugar_fecha}'     => $lugarFecha,

    '{nombre_docente}'  => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'      => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{tipo_evento}'     => htmlspecialchars($tipoEvento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{nombre_evento}'   => htmlspecialchars($nombreEvento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{lugar_evento}'    => htmlspecialchars($lugarEvento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_inicio}'    => htmlspecialchars($fechaIni, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_fin}'       => htmlspecialchars($fechaFin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'     => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'      => $firmaJefe,
    'firma_sub'         => $firma_sub,
    '{url_verificacion}'=> $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CCO_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== CPP (Constancia proyecto premiado) ================== */
if ($tipo === 'CPP') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cpp.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cpp.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CPP no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del proyecto premiado (un registro por solicitud)
  $qp = $pdo->prepare("
    SELECT NOMBRE_PROYECTO,
           LUGAR_OBTENIDO,
           NOMBRE_CONCURSO
    FROM dbo.DOCENTE_PROYECTO_PREMIADO
    WHERE ID_SOLICITUD = :sid
  ");
  $qp->execute([':sid' => $sid]);
  $P = $qp->fetch(PDO::FETCH_ASSOC);

  if (!$P) {
    http_response_code(400);
    exit('No hay datos capturados de proyecto premiado para esta solicitud (CPP).');
  }

  $nombreProyecto = trim((string)$P['NOMBRE_PROYECTO']);
  $lugarObtenido  = trim((string)$P['LUGAR_OBTENIDO']);   // ej. "primer", "segundo"
  $nombreConcurso = trim((string)$P['NOMBRE_CONCURSO']);

  // 4) Departamento aprobador -> jefe académico
  $depApr = 0;
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CPP' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 5) Lugar y fecha de emisión
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];

  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CPP-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
 
  // 8) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'         => $logoSep,
    '{logo_tecnm}'       => $logoTecNM,
    '{folio}'            => $folio,
    '{lugar_fecha}'      => $lugarFecha,

    '{nombre_docente}'   => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'       => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_proyecto}'  => htmlspecialchars($nombreProyecto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{lugar_obtenido}'   => htmlspecialchars($lugarObtenido, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{nombre_concurso}'  => htmlspecialchars($nombreConcurso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'      => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'       => $firmaJefe,
    'firma_sub'          => $firma_sub,
    '{url_verificacion}' => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CPP_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== CCE (Constancia coordinación eventos) ================== */
if ($tipo === 'CCE') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cce.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cce.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CCE no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos de coordinación/colaboración (un registro por solicitud)
  $qc = $pdo->prepare("
    SELECT TIPO_PARTICIPACION,
           NOMBRE_EVENTO,
           FUNCIONES,
           ACTIVIDADES
    FROM dbo.DOCENTE_COORD_EVENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $qc->execute([':sid' => $sid]);
  $C = $qc->fetch(PDO::FETCH_ASSOC);

  if (!$C) {
    http_response_code(400);
    exit('No hay datos capturados de coordinación/colaboración para esta solicitud (CCE).');
  }

  $tipoPart   = trim((string)$C['TIPO_PARTICIPACION']); // coordinación / colaboración
  $nombreEvt  = trim((string)$C['NOMBRE_EVENTO']);
  $funciones  = trim((string)$C['FUNCIONES']);
  $actividades= trim((string)$C['ACTIVIDADES']);

  // 4) Departamento aprobador -> jefe académico
  $depApr = 0;
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CCE' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 5) Lugar y fecha de emisión
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];

  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CCE-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';
  $firma_sub = ($ASSETS && file_exists($ASSETS.'/firma_sub.png'))   ? $ASSETS.'/firma_sub.png'  : '';
  $firma_sub = '<img src="'.htmlspecialchars($firma_sub,ENT_QUOTES,'UTF-8').'" style="width:100px;height:auto;display:inline-block;" />';
 
  // 8) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'          => $logoSep,
    '{logo_tecnm}'        => $logoTecNM,
    '{folio}'             => $folio,
    '{lugar_fecha}'       => $lugarFecha,

    '{nombre_docente}'    => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'        => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{tipo_participacion}'=> htmlspecialchars($tipoPart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{nombre_evento}'     => htmlspecialchars($nombreEvt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{funciones}'         => nl2br(htmlspecialchars($funciones, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
    '{actividades}'       => nl2br(htmlspecialchars($actividades, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),

    '{nombre_jefe}'       => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'        => $firmaJefe,
    'firma_sub'           => $firma_sub,
    '{url_verificacion}'  => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CCE_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== CJEA (Comisión jurado eventos académicos) ================== */
if ($tipo === 'CJEA') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/oficio_cjea.html',
    $PROJ_ROOT.'/app/pdf/plantillas/oficio_cjea.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CJEA no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del jurado en evento (un registro por solicitud)
  $qj = $pdo->prepare("
    SELECT NOMBRE_EVENTO,
           FECHA_EVENTO,
           LUGAR_EVENTO
    FROM dbo.DOCENTE_JURADO_EVENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $qj->execute([':sid' => $sid]);
  $J = $qj->fetch(PDO::FETCH_ASSOC);

  if (!$J) {
    http_response_code(400);
    exit('No hay datos capturados de jurado en evento para esta solicitud (CJEA).');
  }

  $nombreEvento = trim((string)$J['NOMBRE_EVENTO']);
  $lugarEvento  = trim((string)$J['LUGAR_EVENTO']);
  $fechaRaw     = (string)$J['FECHA_EVENTO'];
  $fechaEvento  = '';
  if ($fechaRaw !== '' && $fechaRaw !== '0000-00-00') {
    $ts = strtotime($fechaRaw);
    $fechaEvento = $ts ? date('d/m/Y', $ts) : $fechaRaw;
  }

  // 4) Departamento aprobador -> jefe firmante
  $depApr = 0;
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CJEA' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Departamento Académico';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 5) Lugar y fecha de emisión (oficio)
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];

  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CJEA-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 8) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'        => $logoSep,
    '{logo_tecnm}'      => $logoTecNM,
    '{folio}'           => $folio,
    '{lugar_fecha}'     => $lugarFecha,

    '{nombre_docente}'  => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'      => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_evento}'   => htmlspecialchars($nombreEvento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_evento}'    => htmlspecialchars($fechaEvento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{lugar_evento}'    => htmlspecialchars($lugarEvento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'     => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'      => $firmaJefe,

    '{url_verificacion}' => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CJEA_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== CEPA (Comités de evaluación / acreditación) ================== */
if ($tipo === 'CEPA') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_cepa.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_cepa.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla CEPA no encontrada');
  }

  // 2) Datos base del docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos del comité (un registro por solicitud)
  $qc = $pdo->prepare("
    SELECT TIPO_COMITE,
           ORGANISMO
    FROM dbo.DOCENTE_COMITE_EVAL
    WHERE ID_SOLICITUD = :sid
  ");
  $qc->execute([':sid' => $sid]);
  $C = $qc->fetch(PDO::FETCH_ASSOC);

  if (!$C) {
    http_response_code(400);
    exit('No hay datos capturados de comité de evaluación para esta solicitud (CEPA).');
  }

  $tipoComite = trim((string)$C['TIPO_COMITE']);   // p.ej. 'propuestas de proyectos', 'acreditación'
  $organismo  = trim((string)$C['ORGANISMO']);

  // 4) Departamento aprobador -> jefe firmante
  $depApr = 0;
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='CEPA' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Centro de Adscripción';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 5) Lugar y fecha de emisión
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];

  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Folio y URL de verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('CEPA-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 8) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'       => $logoSep,
    '{logo_tecnm}'     => $logoTecNM,
    '{folio}'          => $folio,
    '{lugar_fecha}'    => $lugarFecha,

    '{nombre_docente}' => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'     => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{tipo_comite}'    => htmlspecialchars($tipoComite, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{organismo}'      => htmlspecialchars($organismo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'    => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'     => $firmaJefe,

    '{url_verificacion}' => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'CEPA_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}

/* ================== PASG (Auditorías de sistemas de gestión) ================== */
if ($tipo === 'PASG') {
  $PROJ_ROOT = str_replace('\\','/', dirname(__DIR__,3));

  // 1) Plantilla
  $tpl = null;
  foreach ([
    $PROJ_ROOT.'/pdf/plantillas/constancia_pasg.html',
    $PROJ_ROOT.'/app/pdf/plantillas/constancia_pasg.html'
  ] as $p) {
    if (is_readable($p)) { $tpl = $p; break; }
  }
  if (!$tpl) {
    http_response_code(500);
    exit('Plantilla PASG no encontrada');
  }

  // 2) Datos base docente
  $expediente = (string)($row['MATRICULA'] ?? $row['CLAVE_EMPLEADO'] ?? '—');
  $nombreDoc  = $nombreDocente;
  $anio       = date('Y');

  // 3) Datos de auditoría
  $qa = $pdo->prepare("
    SELECT TIPO_AUDITORIA,
           TIPO_SISTEMA,
           FECHA_INICIO,
           FECHA_FIN,
           LUGAR
    FROM dbo.DOCENTE_AUDITORIA_SG
    WHERE ID_SOLICITUD = :sid
  ");
  $qa->execute([':sid' => $sid]);
  $A = $qa->fetch(PDO::FETCH_ASSOC);

  if (!$A) {
    http_response_code(400);
    exit('No hay datos capturados de auditoría para esta solicitud (PASG).');
  }

  $tipoAud    = trim((string)$A['TIPO_AUDITORIA']); // interna / externa
  $tipoSist   = trim((string)$A['TIPO_SISTEMA']);
  $lugar      = trim((string)$A['LUGAR']);

  $fIniRaw    = (string)$A['FECHA_INICIO'];
  $fFinRaw    = (string)$A['FECHA_FIN'];

  $fechaIni = '';
  $fechaFin = '';

  if ($fIniRaw && $fIniRaw !== '0000-00-00') {
    $ts = strtotime($fIniRaw);
    $fechaIni = $ts ? date('d/m/Y', $ts) : $fIniRaw;
  }
  if ($fFinRaw && $fFinRaw !== '0000-00-00') {
    $ts = strtotime($fFinRaw);
    $fechaFin = $ts ? date('d/m/Y', $ts) : $fFinRaw;
  }

  // 4) Departamento aprobador -> jefe firmante
  $stApr = $pdo->prepare("
    SELECT ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
  ");
  $stApr->execute([':sid' => $sid]);
  $depApr = (int)($stApr->fetchColumn() ?: 0);

  if ($depApr === 0) {
    $d = $pdo->prepare("
      SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
      FROM dbo.PLANTILLA_DOC
      WHERE TIPO_DOCUMENTO='PASG' AND ACTIVO=1
      ORDER BY ID_PLANTILLA DESC
    ");
    $d->execute();
    $depApr = (int)($d->fetchColumn() ?: 0);
  }

  $sj = $pdo->prepare("
    SELECT TOP 1 ID_USUARIO, NOMBRE_COMPLETO
    FROM dbo.USUARIOS
    WHERE ID_ROL = 2 AND ID_DEPARTAMENTO = :d AND ACTIVO = 1
    ORDER BY ID_USUARIO
  ");
  $sj->execute([':d' => $depApr]);
  $jefe = $sj->fetch(PDO::FETCH_ASSOC);

  $nombreJefe = $jefe ? (string)$jefe['NOMBRE_COMPLETO'] : 'Titular del Centro de Adscripción';
  $firmaJefe  = '';
  if ($jefe && function_exists('siged_firma_abs_path')) {
    $absJ = siged_firma_abs_path($pdo, (int)$jefe['ID_USUARIO']);
    if ($absJ && is_readable($absJ)) {
      $firmaJefe = $absJ;
    }
  }

  // 5) Lugar y fecha de emisión
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',     4 => 'abril',
    5 => 'mayo',  6 => 'junio',   7 => 'julio',     8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  $dia = (int)date('j');
  $mes = $meses[(int)date('n')] ?? date('F');
  $lugarFecha = 'Culiacán, Sinaloa, a '.$dia.' de '.$mes.' de '.$anio;

  // 6) Folio + verificación
  $folioExistente = (string)($row['FOLIO'] ?? '');
  $folio = $folioExistente !== '' ? $folioExistente : ('PASG-'.$anio.'-'.$sid);
  $urlVer = 'http://localhost/siged/public/index.php?action=doc_verify&folio='.$folio;

  // 7) Logos
  $ASSETS    = str_replace('\\','/', realpath($root.'/pdf/assets'));
  $logoSep   = ($ASSETS && file_exists($ASSETS.'/logo_sep.png'))   ? $ASSETS.'/logo_sep.png'   : '';
  $logoTecNM = ($ASSETS && file_exists($ASSETS.'/logo_tecnm.png')) ? $ASSETS.'/logo_tecnm.png' : '';

  // 8) Reemplazos
  $html = file_get_contents($tpl);

  $repl = [
    '{logo_sep}'        => $logoSep,
    '{logo_tecnm}'      => $logoTecNM,
    '{folio}'           => $folio,
    '{lugar_fecha}'     => $lugarFecha,

    '{nombre_docente}'  => htmlspecialchars($nombreDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{expediente}'      => htmlspecialchars($expediente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{tipo_auditoria}'  => htmlspecialchars($tipoAud, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{tipo_sistema}'    => htmlspecialchars($tipoSist, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_inicio}'    => htmlspecialchars($fechaIni, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{fecha_fin}'       => htmlspecialchars($fechaFin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{lugar}'           => htmlspecialchars($lugar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),

    '{nombre_jefe}'     => htmlspecialchars($nombreJefe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    '{firma_jefe}'      => $firmaJefe,

    '{url_verificacion}' => $urlVer,
  ];

  $html = strtr($html, $repl);

  // 9) Render PDF
  $pdf->SetFont('times','',11);
  $pdf->writeHTML($html, true, false, true, false, '');

  $filename = 'PASG_'.$sid.'.pdf';
  $abs      = $PROJ_ROOT.'/storage/pdfs/'.$filename;
  $pdf->Output($abs, 'F');

  $rutaWeb = '/siged/storage/pdfs/'.$filename;
  if (empty($row['RUTA_PDF']) || $row['RUTA_PDF']!==$rutaWeb || empty($row['FOLIO'])) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
      SET RUTA_PDF=:p, FOLIO=:f
      WHERE ID_SOLICITUD=:sid
    ")->execute([
      ':p'   => $rutaWeb,
      ':f'   => $folio,
      ':sid' => $sid,
    ]);
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$filename.'"');
  readfile($abs); exit;
}



// ================== Otros tipos genéricos ==================

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
