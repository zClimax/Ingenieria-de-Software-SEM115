<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user() ?: [];

$DBG = isset($_GET['cha_dbg']) || isset($_POST['cha_dbg']);

$debug = function (string $msg) use ($DBG) {
    if ($DBG) {
        echo '<pre style="font-family:monospace;background:#111;color:#0f0;padding:6px;border-radius:4px;">'
           . 'CHA·DEL — ' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
           . '</pre>';
    }
};

try {
    // 1) Depto del jefe
    $miDep = 0;
    if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
        $miDep = (int)$u['id_departamento'];
    } else {
        $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO = :idu");
        $q->execute([':idu' => (int)$u['id']]);
        $miDep = (int)($q->fetchColumn() ?: 0);
    }
    $debug('miDep = '.$miDep);

    // 2) Parámetros
    $sid        = (int)($_POST['id']          ?? 0); // ID_SOLICITUD (CHA)
    $idHorario  = (int)($_POST['horario_id']  ?? 0); // ID_HORARIO

    if ($sid <= 0 || $idHorario <= 0) {
        throw new RuntimeException('Parámetros inválidos (solicitud/horario).');
    }

    // 3) Validar solicitud CHA + depto aprobador
    $st = $pdo->prepare("
        SELECT 
            ID_SOLICITUD,
            TIPO_DOCUMENTO,
            ID_DEPARTAMENTO_APROBADOR,
            ID_DOCENTE
        FROM dbo.SOLICITUD_DOCUMENTO
        WHERE ID_SOLICITUD = :sid
    ");
    $st->execute([':sid' => $sid]);
    $S = $st->fetch(PDO::FETCH_ASSOC);

    if (!$S) {
        throw new RuntimeException('Solicitud no encontrada.');
    }

    $tipoDoc = strtoupper((string)$S['TIPO_DOCUMENTO']);
    if ($tipoDoc !== 'CHA') {
        throw new RuntimeException('La solicitud no es de tipo CHA (es '.$tipoDoc.').');
    }

    $depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
    if ($depApr === 0 || $depApr !== $miDep) {
        throw new RuntimeException('No tienes permisos sobre esta solicitud.');
    }

    // 4) Verificar que el horario pertenece a ESA solicitud CHA
    $chk = $pdo->prepare("
        SELECT 1
        FROM dbo.DOCENTE_CARGA_HORARIO
        WHERE ID_HORARIO = :hid
          AND ID_SOLICITUD = :sid
    ");
    $chk->execute([
        ':hid' => $idHorario,
        ':sid' => $sid,
    ]);

    if (!$chk->fetchColumn()) {
        throw new RuntimeException('Horario no encontrado para esta solicitud.');
    }

    // 5) Eliminar
    $del = $pdo->prepare("DELETE FROM dbo.DOCENTE_CARGA_HORARIO WHERE ID_HORARIO = :hid");
    $del->execute([':hid' => $idHorario]);

    $debug('Horario eliminado OK');

    if (!$DBG) {
        header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cha_saved=1');
        exit;
    }

} catch (Throwable $e) {
    if ($DBG) {
        http_response_code(200);
        echo '<h2 style="color:#f97316;font-family:system-ui">Error al eliminar horario CHA</h2>';
        echo '<pre style="font-family:monospace;background:#111;color:#fca5a5;padding:8px;border-radius:4px;">'
           . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
           . "</pre>";
        exit;
    }

    header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cha_err=1');
    exit;
}
