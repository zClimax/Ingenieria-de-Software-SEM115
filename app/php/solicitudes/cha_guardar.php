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
           . 'CHA·DBG — ' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
           . '</pre>';
    }
};

try {
    // ===========================
    // 1) Depto del jefe logueado
    // ===========================
    $miDep = 0;
    if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
        $miDep = (int)$u['id_departamento'];
    } else {
        $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO = :idu");
        $q->execute([':idu' => (int)$u['id']]);
        $miDep = (int)($q->fetchColumn() ?: 0);
    }
    $debug('miDep = '.$miDep);

    // ===========================
    // 2) Parámetros básicos
    // ===========================
    $sid      = (int)($_POST['id']        ?? 0);   // ID_SOLICITUD (CHA)
    $idCarga  = (int)($_POST['carga_id']  ?? 0);   // ID_CARGA (de DOCENTE_CARGA)
    $semestre = trim((string)($_POST['semestre']    ?? ''));
    $dias     = trim((string)($_POST['dias_semana'] ?? ''));
    $horaIni  = trim((string)($_POST['hora_inicio'] ?? ''));
    $horaFin  = trim((string)($_POST['hora_fin']    ?? ''));
    $aula     = trim((string)($_POST['aula']        ?? ''));

    if ($sid <= 0) {
        throw new RuntimeException('ID de solicitud inválido.');
    }
    if ($idCarga <= 0) {
        throw new RuntimeException('Debe seleccionar una asignatura.');
    }
    if ($semestre === '' || !in_array($semestre, ['1','2'], true)) {
        throw new RuntimeException('Semestre inválido (debe ser 1 o 2).');
    }
    if ($dias === '' || $horaIni === '' || $horaFin === '') {
        throw new RuntimeException('Día, hora de inicio y hora de fin son obligatorios.');
    }

    $debug('POST='.print_r($_POST, true));

    // ===========================
    // 3) Validar solicitud CHA
    // ===========================
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
        throw new RuntimeException('No tienes permisos para capturar horarios en esta solicitud.');
    }

    $idDocente = (int)($S['ID_DOCENTE'] ?? 0);
    if ($idDocente <= 0) {
        throw new RuntimeException('Solicitud CHA sin docente asociado.');
    }

    // ==============================================================
    // 4) Validar que la CARGA pertenezca al MISMO docente (CSE/CSE2)
    // ==============================================================

    $chk = $pdo->prepare("
        SELECT TOP 1 C.ID_CARGA
        FROM dbo.DOCENTE_CARGA C
        JOIN dbo.SOLICITUD_DOCUMENTO SD ON SD.ID_SOLICITUD = C.ID_SOLICITUD
        WHERE C.ID_CARGA = :cid
          AND SD.ID_DOCENTE = :doc
          AND SD.TIPO_DOCUMENTO IN ('CSE','CSE2')
    ");
    $chk->execute([
        ':cid' => $idCarga,
        ':doc' => $idDocente,
    ]);

    if (!$chk->fetchColumn()) {
        throw new RuntimeException('La asignatura seleccionada no corresponde a un CSE/CSE2 de este docente.');
    }

    // ===========================
    // 5) Insert en DOCENTE_CARGA_HORARIO
    // ===========================
    $ins = $pdo->prepare("
        INSERT INTO dbo.DOCENTE_CARGA_HORARIO
            (ID_SOLICITUD, ID_CARGA, SEMESTRE, DIAS_SEMANA, HORA_INICIO, HORA_FIN, AULA, CREATED_AT)
        VALUES
            (:sid, :cid, :sem, :dias, :hini, :hfin, :aula, SYSDATETIME())
    ");

    $ins->execute([
        ':sid'  => $sid,
        ':cid'  => $idCarga,
        ':sem'  => $semestre,
        ':dias' => $dias,
        ':hini' => $horaIni,
        ':hfin' => $horaFin,
        ':aula' => ($aula !== '' ? $aula : null),
    ]);

    $debug('Insert OK en DOCENTE_CARGA_HORARIO');

    // ===========================
    // 6) Redirección limpia
    // ===========================
    if (!$DBG) {
        header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cha_saved=1');
        exit;
    }

} catch (Throwable $e) {
    if ($DBG) {
        http_response_code(200);
        echo '<h2 style="color:#f97316;font-family:system-ui">Error al guardar horario CHA</h2>';
        echo '<pre style="font-family:monospace;background:#111;color:#fca5a5;padding:8px;border-radius:4px;">'
           . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
           . "</pre>";
        exit;
    }

    // En productivo solo regresamos a jefe_ver con un flag genérico
    $sid = $sid ?? 0;
    header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cha_err=1');
    exit;
}
