<?php
declare(strict_types=1);

function siged_render_pdf(array $data): TCPDF {
  // $data: [
  //  'titulo', 'tipo', 'folio', 'hash', 'docente' => ['nombre','rfc','curp','correo'],
  //  'departamento' => ['id','nombre','siglas'],
  //  'fechas' => ['creacion','envio','decision'],
  //  'paleta' => ['primario' => [r,g,b], 'secundario' => [r,g,b]],
  // ]

  $pdf = new TCPDF('P','mm','LETTER',true,'UTF-8',false);
  $pdf->SetCreator('SIGED');
  $pdf->SetAuthor('SIGED');
  $pdf->SetTitle($data['titulo'] ?? ('Documento SIGED'));
  $pdf->SetMargins(15,18,15);
  $pdf->SetAutoPageBreak(true, 20);
  $pdf->AddPage();

  $p = $data['paleta']['primario'] ?? [20,90,160];
  $s = $data['paleta']['secundario'] ?? [30,160,100];

  $titulo = htmlspecialchars($data['titulo'] ?? 'Documento SIGED');
  $tipo   = htmlspecialchars($data['tipo'] ?? 'SIP');
  $folio  = htmlspecialchars($data['folio'] ?? 'SIN-FOLIO');

  $doc = $data['docente'] ?? [];
  $dep = $data['departamento'] ?? [];
  $fec = $data['fechas'] ?? [];

  $nombreDoc = htmlspecialchars($doc['nombre'] ?? 'N/D');
  $rfc       = htmlspecialchars($doc['rfc'] ?? 'N/D');
  $curp      = htmlspecialchars($doc['curp'] ?? 'N/D');
  $correo    = htmlspecialchars($doc['correo'] ?? 'N/D');

  $depNom = htmlspecialchars($dep['nombre'] ?? 'N/D');
  $depSig = htmlspecialchars($dep['siglas'] ?? 'N/D');

  $html = <<<HTML
  <h2 style="color:rgb({$p[0]},{$p[1]},{$p[2]});margin:0">$titulo</h2>
  <p style="margin-top:2px"><strong>Tipo:</strong> $tipo &nbsp; | &nbsp; <strong>Folio:</strong> $folio</p>
  <hr>
  <h3>Datos del docente</h3>
  <table cellpadding="4" cellspacing="0" border="0">
    <tr><td><strong>Nombre</strong></td><td>$nombreDoc</td></tr>
    <tr><td><strong>RFC</strong></td><td>$rfc</td></tr>
    <tr><td><strong>CURP</strong></td><td>$curp</td></tr>
    <tr><td><strong>Correo</strong></td><td>$correo</td></tr>
  </table>
  <h3>Departamento</h3>
  <p><strong>$depSig</strong> — $depNom</p>
  <h3>Fechas</h3>
  <p>Creación: {$fec['creacion']} &nbsp; | &nbsp; Envío: {$fec['envio']} &nbsp; | &nbsp; Decisión: {$fec['decision']}</p>
  HTML;

  $pdf->writeHTML($html, true, false, true, false, '');

  // QR
  $hash = $data['hash'] ?? 'N/A';
  $style = ['border'=>0,'padding'=>0,'fgcolor'=>[0,0,0],'bgcolor'=>false];
  $pdf->write2DBarcode($hash, 'QRCODE,H', 160, 30, 30, 30, $style);
  $pdf->Text(160, 62, 'Hash: ' . substr($hash, 0, 10) . '…');

  // Pie de página
  $pdf->SetY(-20);
  $pdf->SetTextColor($s[0],$s[1],$s[2]);
  $pdf->Cell(0, 10, 'SIGED · Documento generado automáticamente', 0, 0, 'C');

  return $pdf;
}
