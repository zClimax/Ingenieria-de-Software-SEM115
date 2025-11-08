<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']);

$pdo = DB::conn();
$V=Config::MAP['SOLICITUD'];
$tv=$V['TABLE']; $vId=$V['ID']; $vEstado=$V['ESTADO'];

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { die('ID inválido'); }

$row = $pdo->query("SELECT $vEstado AS estado FROM $tv WHERE $vId=$id")->fetch();
if (!$row || $row['estado']!=='BORRADOR') { die('No puedes subir en este estado.'); }

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error']!==UPLOAD_ERR_OK) { die('Archivo requerido.'); }
$f = $_FILES['archivo'];

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','jpg','jpeg','png'];
if (!in_array($ext,$allowed,true)) { die('Tipo no permitido.'); }
if ($f['size'] > 5*1024*1024) { die('> 5 MB'); }

/* ✅ Crear carpeta si no existe */
$baseDirPath = __DIR__ . '/../../uploads/evidencias';
if (!is_dir($baseDirPath)) {
  if (!mkdir($baseDirPath, 0775, true) && !is_dir($baseDirPath)) {
    die('No se pudo crear el directorio de evidencias.');
  }
}
$baseDir = realpath($baseDirPath);
if (!$baseDir) { die('No se pudo resolver la ruta de evidencias.'); }

$random = bin2hex(random_bytes(8)) . '.' . $ext;
$dest = $baseDir . DIRECTORY_SEPARATOR . $random;

if (!move_uploaded_file($f['tmp_name'], $dest)) { die('No se pudo guardar'); }

/* Guardar registro en la tabla hija de evidencias */
$E = Config::MAP['EVID'];
$stmt = $pdo->prepare("INSERT INTO ".$E['TABLE']." (".$E['SOL'].",".$E['NOM'].",".$E['RUTA'].",".$E['MIME'].",".$E['BYTES'].") 
VALUES (:sol,:nom,:ruta,:mime,:bytes)");
$stmt->execute([
  ':sol'=>$id, ':nom'=>$f['name'], ':ruta'=>$dest, ':mime'=>$f['type'] ?? null, ':bytes'=>$f['size']
]);

header('Location: /siged/public/index.php?action=sol_editar&id='.$id);
