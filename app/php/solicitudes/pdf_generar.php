<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireLogin();

$pdo = DB::conn();
$S = Config::MAP['SOLICITUD']; 
$D = Config::MAP['DOCENTE'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$ts=$S['TABLE']; $td=$D['TABLE'];
$sql = "SELECT S.".$S['ID']." AS id, S.".$S['DOC']." AS id_doc, S.".$S['DEP']." AS id_dep, 
               S.".$S['TIPO']." AS tipo, S.".$S['ESTADO']." AS estado,
               S.".$S['F_CRE']." AS f_cre, S.".$S['F_ENV']." AS f_env, S.".$S['F_DEC']." AS f_dec,
               S.".$S['PDF_PATH']." AS ruta_pdf, S.".$S['FOLIO']." AS folio, S.".$S['HASH']." AS hash
        FROM $ts S WHERE S.".$S['ID']." = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id'=>$id]);
$sol = $stmt->fetch();
if (!$sol) { http_response_code(404); exit('Solicitud no encontrada'); }

$user = Session::user();
$esDocente = ($user['rol'] === 'DOCENTE');
$esJefe    = ($user['rol'] === 'JEFE_DEPARTAMENTO');
$permitido = false;

if ($esDocente) {
  $chk = $pdo->query("SELECT ".$D['ID']." AS id_doc FROM $td WHERE ".$D['ID']."=".(int)$sol['id_doc']." AND ".$D['ID_USR']."=".(int)$user['id'])->fetch();
  $permitido = (bool)$chk;
} elseif ($esJefe) {
  $permitido = ((int)$user['id_departamento'] === (int)$sol['id_dep']);
}
if (!$permitido) { http_response_code(403); exit('No autorizado'); }

if ($sol['estado'] !== 'APROBADA') {
  http_response_code(409); exit('La solicitud debe estar APROBADA para generar PDF.');
}

$doc = $pdo->query("SELECT ".$D['NOMBRE']." AS nom, ".$D['AP_PAT']." AS ap, ".$D['AP_MAT']." AS am,
                           ".$D['RFC']." AS rfc, ".$D['CURP']." AS curp, ".$D['CORREO']." AS correo
                    FROM $td WHERE ".$D['ID']."=".(int)$sol['id_doc'])->fetch();
$docNombre = trim(($doc['nom']??'').' '.($doc['ap']??'').' '.($doc['am']??''));

$folio = $sol['folio'] ?: ('SIGED-'.date('Y').'-'.(int)$sol['id_dep'].'-'.(int)$sol['id']);
$hash  = $sol['hash']  ?: hash('sha256', 'SIGED|'.$sol['id'].'|'.$folio.'|'.date('c'));



// === Autoload Composer/TCPDF (busca en rutas típicas) ===
$autoloadCandidates = [
    __DIR__ . '/../../../vendor/autoload.php', // SIGED/vendor (raíz del proyecto)
    __DIR__ . '/../../vendor/autoload.php',    // SIGED/app/vendor (si existiera)
    dirname(__DIR__, 3) . '/vendor/autoload.php', // alternativa genérica
  ];
  
  $autoloadFound = false;
  foreach ($autoloadCandidates as $auto) {
    if (is_file($auto)) { require_once $auto; $autoloadFound = true; break; }
  }
  if (!$autoloadFound) {
    http_response_code(500);
    echo "No se encontró vendor/autoload.php. Verifica que ejecutaste:<br>"
       . "<code>cd C:\\xampp\\htdocs\\SIGED</code><br>"
       . "<code>composer require tecnickcom/tcpdf</code><br>"
       . "Ruta probada principal: <code>" . htmlspecialchars($autoloadCandidates[0]) . "</code>";
    exit;
  }
  
$pdf = new TCPDF('P','mm','LETTER',true,'UTF-8',false);
$pdf->SetCreator('SIGED'); $pdf->SetAuthor('SIGED');
$pdf->SetTitle('SIGED · '.$sol['tipo']); 
$pdf->SetMargins(20,20,20); $pdf->SetAutoPageBreak(true,20);
$pdf->AddPage();



/* === Caso Carta de Exclusividad (DCE/DEC): render con tu HTML === */
$tipoNormalizado = strtoupper(trim((string)$sol['tipo']));
$usaCartaExclusividad = in_array($tipoNormalizado, ['DCE', 'DEC'], true);

if ($usaCartaExclusividad) {

  // 1) Resolver plantilla por tipo (ambos mapean a carta_exclusividad.html)
  $tplFile = 'carta_exclusividad.html';

  // 2) Resolver rutas candidatas de forma robusta
  $tplCandidates = [
    __DIR__ . '/../../pdf/plantillas/' . $tplFile,           // SIGED/app/pdf/plantillas/
    dirname(__DIR__, 3) . '/app/pdf/plantillas/' . $tplFile, // SIGED/app/pdf/plantillas/ (alternativa)
    dirname(__DIR__, 2) . '/pdf/plantillas/' . $tplFile,     // SIGED/pdf/plantillas/ (si movieron pdf/ a raíz)
  ];

  $tplPath = null;
  foreach ($tplCandidates as $candidate) {
    if (is_file($candidate)) { $tplPath = $candidate; break; }
  }

  if (!$tplPath) {
    http_response_code(500);
    echo "Plantilla no encontrada para {$tipoNormalizado}.<br>Busqué en:<ul>";
    foreach ($tplCandidates as $c) { echo '<li><code>'.htmlspecialchars($c).'</code></li>'; }
    echo '</ul>Coloca <code>$tplFile</code> en una de esas rutas y vuelve a intentar.';
    exit;
  }

  // 3) Cargar HTML
  $html = file_get_contents($tplPath);

  // 4) Placeholders (usa valores seguros si faltan)
  //    Ajusta estos valores cuando tengas fuentes reales (p.ej., tabla DEPARTAMENTO).
  //    Para fecha en español en Windows, intenta varios locales:
  setlocale(LC_TIME, 'es_MX.UTF-8','es_MX','Spanish_Mexico','es_ES.UTF-8','es_ES','es');

  $replacements = [
    '{ciudad}'               => 'Culiacán, Sinaloa',
    '{fecha_larga}'          => strftime('%d de %B de %Y'),
    '{nombre_docente}'       => $docNombre,
    '{rfc}'                  => (string)($doc['rfc'] ?? ''),
    '{clave_presupuestal}'   => '',
    '{campus}'               => 'TecNM Campus Culiacán',
    '{nombre_jefe_rh}'       => 'Jefe(a) de Departamento',
    '{nota_piedepagina}'     => 'Documento generado automáticamente por SIGED.',
    // Imágenes (si no existen, se eliminan más abajo)
    '{path_firma_jefa_rh}'   => is_file(__DIR__.'/../../pdf/assets/firma_jefe.png') ? (__DIR__.'/../../pdf/assets/firma_jefe.png') : '',
    '{path_qr_autenticidad}' => '', // el QR lo dibujamos con TCPDF
  ];
  $html = strtr($html, $replacements);

  // 5) Eliminar <img> con src vacío para evitar íconos rotos
  $html = preg_replace('#<img[^>]+src=["\']\s*["\'][^>]*>#i', '', $html);

  // 6) Render del HTML
  $pdf->writeHTML($html, true, false, true, false, '');

  // 7) QR con hash/folio
  $style = ['border'=>0,'padding'=>0,'fgcolor'=>[0,0,0],'bgcolor'=>false];
  $pdf->write2DBarcode($hash, 'QRCODE,H', 160, 45, 30, 30, $style);
  $pdf->SetFont('helvetica','',8);
  $pdf->Text(157, 77, 'Folio: '.$folio);
  $pdf->Text(157, 81, 'Hash: '.substr($hash,0,12).'…');

} else {
  /* === Otros tipos: plantilla genérica (fallback) === */
  require_once __DIR__ . '/../../pdf/plantillas/plantilla_generica.php';
  $data = [
    'titulo' => 'Documento Oficial SIGED',
    'tipo'   => $sol['tipo'],
    'folio'  => $folio,
    'hash'   => $hash,
    'docente' => [
      'nombre' => $docNombre,
      'rfc'    => $doc['rfc'] ?? '',
      'curp'   => $doc['curp'] ?? '',
      'correo' => $doc['correo'] ?? '',
    ],
    'departamento' => ['id'=>(int)$sol['id_dep'], 'nombre'=>'', 'siglas'=>''],
    'fechas' => ['creacion'=>(string)$sol['f_cre'], 'envio'=>(string)$sol['f_env'], 'decision'=>(string)$sol['f_dec']],
    'paleta' => ['primario'=>[20,90,160], 'secundario'=>[30,160,100]],
  ];
  $pdf = siged_render_pdf($data);
}


/* Guardar y actualizar */
$salidasDir = __DIR__ . '/../../pdf/salidas';
if (!is_dir($salidasDir)) { mkdir($salidasDir, 0775, true); }
$filename = 'SOL_'.$sol['id'].'_'.$sol['tipo'].'_'.$folio.'.pdf';
$filepath = realpath($salidasDir) . DIRECTORY_SEPARATOR . $filename;

$pdf->Output($filepath, 'F');

$stmtU = $pdo->prepare("UPDATE $ts SET ".$S['PDF_PATH']."=:p, ".$S['FOLIO']."=:f, ".$S['HASH']."=:h WHERE ".$S['ID']."=:id");
$stmtU->execute([':p'=>$filepath, ':f'=>$folio, ':h'=>$hash, ':id'=>$sol['id']]);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
readfile($filepath);
exit;
