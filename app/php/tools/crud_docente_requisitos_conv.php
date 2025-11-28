<?php
// CRUD rápido para DOCENTE_REQUISITO_CONV
// Herramienta interna, SIN sesión y SIN roles. No dejar pública en producción.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Ajusta la ruta si tu config.php está en otro lado
require_once __DIR__ . '/../config.php';

$pdo = DB::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Catálogo fijo de requisitos (no se modifica desde aquí)
$REQS = [
    1 => ['clave' => 'CARGA_REGLAMENTARIA',  'nombre' => 'Carga horaria reglamentaria'],
    2 => ['clave' => 'CUMPLIMIENTO_HORAS',   'nombre' => 'Cumplimiento de horas'],
    3 => ['clave' => 'NOMBRAMIENTO_DEFINIT', 'nombre' => 'Nombramiento definitivo'],
    4 => ['clave' => 'SIN_CARGOS_ADM',       'nombre' => 'Sin cargos administrativos'],
    5 => ['clave' => 'TIEMPO_COMPLETO',      'nombre' => 'Tiempo completo'],
];

$message = '';
$error   = '';
$currentId = 0;

// Normaliza fecha: recibe string o vacío, devuelve string 'Y-m-d H:i:s'
function normalizarFechaEval(string $val): string {
    $val = trim($val);
    if ($val === '') {
        return date('Y-m-d H:i:s');
    }
    // Puede venir como 'YYYY-MM-DDTHH:MM'
    $ts = strtotime($val);
    if ($ts === false) {
        return date('Y-m-d H:i:s');
    }
    return date('Y-m-d H:i:s', $ts);
}

// =======================================
// PROCESAR POST (CREATE / UPDATE)
// =======================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action        = $_POST['action'] ?? '';
    $id_drc        = (int)($_POST['id_drc'] ?? 0);
    $id_docente    = (int)($_POST['id_docente'] ?? 0);
    $id_conv       = (int)($_POST['id_convocatoria'] ?? 0);
    $id_req        = (int)($_POST['id_requisito'] ?? 0);
    $cumple        = isset($_POST['cumple']) ? 1 : 0;
    $detalle       = trim((string)($_POST['detalle'] ?? ''));
    $fecha_eval_in = trim((string)($_POST['fecha_eval'] ?? ''));

    try {
        if ($id_docente <= 0 || $id_conv <= 0 || $id_req <= 0) {
            throw new RuntimeException('ID_DOCENTE, ID_CONVOCATORIA e ID_REQUISITO son obligatorios.');
        }
        if (!array_key_exists($id_req, $REQS)) {
            throw new RuntimeException('ID_REQUISITO inválido (no está en el catálogo base).');
        }

        $fecha_eval_db = normalizarFechaEval($fecha_eval_in);

        if ($action === 'create') {
            $sql = "
                INSERT INTO dbo.DOCENTE_REQUISITO_CONV
                    (ID_DOCENTE, ID_CONVOCATORIA, ID_REQUISITO, CUMPLE, DETALLE, FECHA_EVAL)
                VALUES
                    (:doc, :conv, :req, :cumple, :det, :fec)
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':doc'    => $id_docente,
                ':conv'   => $id_conv,
                ':req'    => $id_req,
                ':cumple' => $cumple,
                ':det'    => $detalle,
                ':fec'    => $fecha_eval_db,
            ]);

            $currentId = (int)$pdo->lastInsertId();
            $message   = "Registro creado correctamente (ID_DRC={$currentId}).";

        } elseif ($action === 'update') {
            if ($id_drc <= 0) {
                throw new RuntimeException('ID_DRC requerido para actualizar.');
            }

            $sql = "
                UPDATE dbo.DOCENTE_REQUISITO_CONV
                SET ID_DOCENTE      = :doc,
                    ID_CONVOCATORIA = :conv,
                    ID_REQUISITO    = :req,
                    CUMPLE          = :cumple,
                    DETALLE         = :det,
                    FECHA_EVAL      = :fec
                WHERE ID_DRC = :id
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':doc'    => $id_docente,
                ':conv'   => $id_conv,
                ':req'    => $id_req,
                ':cumple' => $cumple,
                ':det'    => $detalle,
                ':fec'    => $fecha_eval_db,
                ':id'     => $id_drc,
            ]);

            $currentId = $id_drc;
            $message   = "Registro actualizado correctamente (ID_DRC={$id_drc}).";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// =======================================
// LISTADO DE REGISTROS
// =======================================
$listStmt = $pdo->query("
    SELECT
        R.ID_DRC,
        R.ID_DOCENTE,
        R.ID_CONVOCATORIA,
        R.ID_REQUISITO,
        R.CUMPLE,
        R.DETALLE,
        R.FECHA_EVAL,
        D.NOMBRE_DOCENTE,
        D.APELLIDO_PATERNO_DOCENTE,
        D.APELLIDO_MATERNO_DOCENTE
    FROM dbo.DOCENTE_REQUISITO_CONV R
    LEFT JOIN dbo.DOCENTE D ON D.ID_DOCENTE = R.ID_DOCENTE
    ORDER BY R.ID_DRC DESC
");
$lista = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// =======================================
// CARGAR PARA EDICIÓN (SI APLICA)
// =======================================
$editId = isset($_GET['id_drc']) ? (int)$_GET['id_drc'] : 0;
if ($currentId > 0) {
    $editId = $currentId;
}

$current = [
    'ID_DRC'         => '',
    'ID_DOCENTE'     => '',
    'ID_CONVOCATORIA'=> '',
    'ID_REQUISITO'   => '',
    'CUMPLE'         => 1,
    'DETALLE'        => '',
    'FECHA_EVAL'     => '',
    'FECHA_EVAL_INPUT' => '',
];

if ($editId > 0) {
    $st = $pdo->prepare("
        SELECT * FROM dbo.DOCENTE_REQUISITO_CONV
        WHERE ID_DRC = :id
    ");
    $st->execute([':id' => $editId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $current['ID_DRC']          = $row['ID_DRC'];
        $current['ID_DOCENTE']      = $row['ID_DOCENTE'];
        $current['ID_CONVOCATORIA'] = $row['ID_CONVOCATORIA'];
        $current['ID_REQUISITO']    = $row['ID_REQUISITO'];
        $current['CUMPLE']          = $row['CUMPLE'];
        $current['DETALLE']         = $row['DETALLE'];
        $current['FECHA_EVAL']      = $row['FECHA_EVAL'];

        $raw = (string)$row['FECHA_EVAL'];
        $dtLocal = '';
        if ($raw !== '') {
            $ts = strtotime($raw);
            if ($ts !== false) {
                $dtLocal = date('Y-m-d\TH:i', $ts); // para input datetime-local
            }
        }
        $current['FECHA_EVAL_INPUT'] = $dtLocal;
    }
}

// Helper para checked
function checked_bool($v): string {
    return ((int)$v === 1) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD · DOCENTE_REQUISITO_CONV</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 20px;
            background: #f3f4f6;
        }
        h1 { margin-bottom: 4px; }
        .wrapper {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .panel {
            background: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,.1);
            flex: 1;
        }
        .panel h2 {
            margin-top: 0;
            font-size: 17px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 6px;
        }
        .msg-ok {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
            background: #dcfce7;
            color: #166534;
        }
        .msg-err {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
            background: #fee2e2;
            color: #991b1b;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 8px 14px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        label {
            font-size: 12px;
            color: #444;
            margin-bottom: 2px;
        }
        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        textarea,
        select {
            padding: 5px 7px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            font-size: 13px;
        }
        textarea { resize: vertical; min-height: 60px; }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 16px;
        }
        .actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
        }
        button {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; text-decoration:none; display:inline-block; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
        }
        th {
            background: #e5e7eb;
        }
        a {
            color: #2563eb;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .id-pill {
            font-weight: bold;
            color: #111;
        }
        .tag-ok {
            color: #15803d;
            font-weight: 600;
        }
        .tag-no {
            color: #b91c1c;
            font-weight: 600;
        }
        small { font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>

<h1>CRUD rápido · DOCENTE_REQUISITO_CONV</h1>
<p>Herramienta interna para llenar requisitos de convocatoria por docente.</p>

<?php if ($message): ?>
    <div class="msg-ok"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="msg-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="wrapper">

    <!-- PANEL FORMULARIO -->
    <div class="panel">
        <h2><?= $current['ID_DRC'] ? 'Editar requisito de docente' : 'Nuevo requisito de docente' ?></h2>
        <form method="post">
            <input type="hidden" name="id_drc" value="<?= htmlspecialchars((string)$current['ID_DRC']) ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>ID_DOCENTE</label>
                    <input type="number" name="id_docente" value="<?= htmlspecialchars((string)$current['ID_DOCENTE']) ?>">
                </div>
                <div class="form-group">
                    <label>ID_CONVOCATORIA</label>
                    <input type="number" name="id_convocatoria" value="<?= htmlspecialchars((string)$current['ID_CONVOCATORIA']) ?>">
                </div>
                <div class="form-group">
                    <label>Requisito (ID_REQUISITO)</label>
                    <select name="id_requisito">
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($REQS as $idReq => $req): ?>
                            <option value="<?= $idReq ?>"
                                <?= ((int)$current['ID_REQUISITO'] === $idReq) ? 'selected' : '' ?>>
                                <?= $idReq ?> · <?= htmlspecialchars($req['clave'] . ' - ' . $req['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="chkCumple" name="cumple" value="1" <?= checked_bool($current['CUMPLE']) ?>>
                    <label for="chkCumple">Cumple</label>
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label>Detalle</label>
                    <textarea name="detalle"><?= htmlspecialchars((string)$current['DETALLE']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Fecha evaluación (FECHA_EVAL)</label>
                    <input type="datetime-local" name="fecha_eval" value="<?= htmlspecialchars((string)$current['FECHA_EVAL_INPUT']) ?>">
                    <small>Si se deja vacío, se usará la fecha/hora actual.</small>
                </div>
            </div>

            <div class="actions">
                <button type="submit" name="action" value="<?= $current['ID_DRC'] ? 'update' : 'create' ?>" class="btn-primary">
                    <?= $current['ID_DRC'] ? 'Actualizar registro' : 'Crear registro' ?>
                </button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn-secondary">
                    Limpiar formulario
                </a>
            </div>
        </form>
    </div>

    <!-- PANEL LISTA -->
    <div class="panel">
        <h2>Registros existentes</h2>
        <table>
            <thead>
                <tr>
                    <th>ID_DRC</th>
                    <th>ID_DOCENTE / Nombre</th>
                    <th>ID_CONV</th>
                    <th>Requisito</th>
                    <th>Cumple</th>
                    <th>Detalle</th>
                    <th>Fecha eval</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$lista): ?>
                <tr><td colspan="8" style="text-align:center;">Sin registros</td></tr>
            <?php else: ?>
                <?php foreach ($lista as $r): 
                    $idReq = (int)$r['ID_REQUISITO'];
                    $reqTxt = isset($REQS[$idReq])
                        ? ($REQS[$idReq]['clave'].' - '.$REQS[$idReq]['nombre'])
                        : ('ID '.$idReq.' (no definido)');
                    $nomDoc = trim((string)(
                        ($r['NOMBRE_DOCENTE'] ?? '') . ' ' .
                        ($r['APELLIDO_PATERNO_DOCENTE'] ?? '') . ' ' .
                        ($r['APELLIDO_MATERNO_DOCENTE'] ?? '')
                    ));
                    if ($nomDoc === '') $nomDoc = '—';
                    $cumpleTxt = ((int)$r['CUMPLE'] === 1)
                        ? '<span class="tag-ok">Sí</span>'
                        : '<span class="tag-no">No</span>';
                ?>
                    <tr>
                        <td class="id-pill"><?= (int)$r['ID_DRC'] ?></td>
                        <td>
                            <?= (int)$r['ID_DOCENTE'] ?><br>
                            <small><?= htmlspecialchars($nomDoc) ?></small>
                        </td>
                        <td><?= (int)$r['ID_CONVOCATORIA'] ?></td>
                        <td><?= htmlspecialchars($reqTxt) ?></td>
                        <td><?= $cumpleTxt ?></td>
                        <td><?= htmlspecialchars((string)$r['DETALLE']) ?></td>
                        <td><?= htmlspecialchars((string)$r['FECHA_EVAL']) ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?id_drc=' . (int)$r['ID_DRC'] ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
