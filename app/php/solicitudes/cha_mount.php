<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (!isset($pdo)) {
    $pdo = DB::conn();
}

if ($id <= 0) return;

// Traemos la solicitud
$st = $pdo->prepare("
  SELECT ID_SOLICITUD, TIPO_DOCUMENTO, ESTADO, ID_DOCENTE, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD=:sid
");
$st->execute([':sid' => $id]);
$S = $st->fetch(PDO::FETCH_ASSOC);
if (!$S) return;

if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CHA') {
    return;
}

// Normalizar llaves para el form
$sol = [
  'id'            => (int)$S['ID_SOLICITUD'],
  'tipo'          => (string)$S['TIPO_DOCUMENTO'],
  'estado'        => (string)$S['ESTADO'],
  'id_docente'    => (int)($S['ID_DOCENTE'] ?? 0),
  'dep_aprobador' => (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0),
];

// Montar el formulario
require __DIR__ . '/cha_form.php';
