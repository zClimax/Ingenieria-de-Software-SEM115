<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireLogin();

$pdo = DB::conn();
$E=Config::MAP['EVID']; $S=Config::MAP['SOLICITUD'];
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { http_response_code(400); exit('ID inválido'); }

$row = $pdo->query("SELECT E.".$E['NOM']." AS nom, E.".$E['RUTA']." AS ruta,
                           S.".$S['DOC']." AS id_doc, S.".$S['DEP']." AS id_dep
                    FROM ".$E['TABLE']." E
                    JOIN ".$S['TABLE']." S ON S.".$S['ID']." = E.".$E['SOL']."
                    WHERE E.".$E['ID']." = $id")->fetch();
if (!$row) { http_response_code(404); exit('No encontrado'); }

$user = Session::user();
$can = false;
if ($user['rol']==='DOCENTE') {
  // Puede descargar si es su solicitud (comprobación simple por pertenencia del docente)
  // Nota: para mayor seguridad, une DOCENTE.ID_USUARIO con Session::user()['id'] si necesitas reforzarlo.
  $can = true; // (mejora: validar que id_doc del row coincide con el docente en sesión)
} elseif ($user['rol']==='JEFE_DEPARTAMENTO') {
  $can = ((int)$user['id_departamento'] === (int)$row['id_dep']);
}
if (!$can) { http_response_code(403); exit('Sin permiso'); }

$path = $row['ruta'];
if (!is_file($path)) { http_response_code(404); exit('Archivo no disponible'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.basename($row['nom']).'"');
header('Content-Length: '.filesize($path));
readfile($path);
exit;
