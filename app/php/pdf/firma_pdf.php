<?php
declare(strict_types=1);

/**
 * Resuelve la ruta absoluta de la firma guardada en USUARIOS.RUTA_FIRMA
 * @return string|null Ruta absoluta al archivo o null si no existe
 */
function siged_firma_abs_path(PDO $pdo, int $idUsuario): ?string {
  $st = $pdo->prepare("SELECT RUTA_FIRMA FROM [SIGED].[dbo].[USUARIOS] WHERE ID_USUARIO = :id");
  $st->execute([':id' => $idUsuario]);
  $ruta = (string)($st->fetchColumn() ?: '');
  if ($ruta === '') return null;

  // Caso típico: guardamos '/siged/storage/firmas/archivo.png' (ruta web)
  // 1) Intento por DOCUMENT_ROOT
  $abs = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . $ruta;

  // 2) Fallback robusto al root del proyecto
  if (!file_exists($abs)) {
    $base = realpath(__DIR__ . '/../../..'); // .../SIGED
    if ($base) {
      $abs2 = rtrim($base, '/\\') . $ruta;    // concatena tal cual
      if (file_exists($abs2)) $abs = $abs2;
      else return null;
    } else {
      return null;
    }
  }
  return $abs;
}

/**
 * Estampa una firma en el PDF si existe.
 * @param TCPDF $pdf     Instancia TCPDF
 * @param PDO   $pdo     Conexión
 * @param int   $idUser  ID del usuario dueño de la firma
 * @param float $x       Posición X (mm)
 * @param float $y       Posición Y (mm)
 * @param float $w       Ancho (mm), alto se calcula proporcional
 * @param bool  $label   Si true, imprime línea “Firma de …” debajo (opcional)
 * @param string|null $nombreVisible  Texto a imprimir bajo la firma (si $label=true)
 */
function siged_pdf_estampar_firma(TCPDF $pdf, PDO $pdo, int $idUser, float $x, float $y, float $w, bool $label=false, ?string $nombreVisible=null): void {
  $abs = siged_firma_abs_path($pdo, $idUser);
  if (!$abs || !file_exists($abs)) return;

  $ext = strtoupper(pathinfo($abs, PATHINFO_EXTENSION));
  // TCPDF soporta PNG/JPG
  $type = ($ext === 'PNG') ? 'PNG' : 'JPG';

  // Estampa imagen (alto auto)
  $pdf->Image($abs, $x, $y, $w, 0, $type);

  if ($label) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(60,60,60);
    $pdf->SetXY($x, $y + 12); // ajusta según tu plantilla
    $labelTxt = 'Firma';
    if ($nombreVisible) $labelTxt = 'Firma de ' . $nombreVisible;
    $pdf->Cell($w + 2, 5, $labelTxt, 0, 0, 'C', false, '', 0, false, 'T', 'M');
  }
}

/**
 * (Opcional) Posiciones por tipo de documento/rol.
 * Devuelve [x,y,w] en mm. Ajusta a tus plantillas.
 */
function siged_posicion_firma(string $tipoDocumento, string $rol): array {
  // Defaults
  $map = [
    // Ejemplos:
    // 'CONSTANCIA_ACTIVIDAD' => ['DOCENTE' => [40, 240, 35], 'JEFE' => [140, 240, 35]],
    // 'ACTA_EVALUACION'     => ['DOCENTE' => [35, 235, 38], 'JEFE' => [150, 235, 38]],
  ];

  $rol = strtoupper($rol);
  if (isset($map[$tipoDocumento][$rol])) return $map[$tipoDocumento][$rol];

  // Fallback genérico: esquina baja
  return ($rol === 'JEFE') ? [140, 240, 35] : [40, 240, 35];
}
