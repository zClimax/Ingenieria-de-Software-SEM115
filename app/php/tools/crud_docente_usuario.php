<?php
// CRUD simple para USUARIOS + DOCENTE
// Úsalo SOLO como herramienta interna (no dejar expuesto en producción).

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Ajusta la ruta según dónde pongas este archivo
require_once __DIR__ . '/../config.php';

$pdo = DB::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$error   = '';
$currentIdDoc = 0;

// Helper: null si viene vacío
function nullIfEmpty(?string $v): ?string {
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

// =======================================
// PROCESAR POST (CREATE / UPDATE)
// =======================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Campos compartidos
    $idUsuario  = (int)($_POST['u_id_usuario'] ?? 0);
    $idDocente  = (int)($_POST['d_id_docente'] ?? 0);

    $idRol      = (int)($_POST['id_rol'] ?? 0);
    $idDepto    = (int)($_POST['id_depto'] ?? 0);
    $usuario    = trim((string)($_POST['usuario'] ?? ''));
    $contrasena = trim((string)($_POST['contrasena'] ?? ''));
    $activo     = isset($_POST['activo']) ? 1 : 0;

    $nombreDocente  = trim((string)($_POST['nombre_docente'] ?? ''));
    $apPaterno      = trim((string)($_POST['apellido_paterno'] ?? ''));
    $apMaterno      = trim((string)($_POST['apellido_materno'] ?? ''));
    $rfc            = trim((string)($_POST['rfc'] ?? ''));
    $curp           = trim((string)($_POST['curp'] ?? ''));
    $correo         = trim((string)($_POST['correo'] ?? ''));
    $telefono       = trim((string)($_POST['telefono'] ?? ''));
    $claveEmpleado  = trim((string)($_POST['clave_empleado'] ?? ''));
    $nss            = trim((string)($_POST['nss'] ?? ''));
    $fechaIngreso   = nullIfEmpty($_POST['fecha_ingreso'] ?? null); // formato YYYY-MM-DD
    $matricula      = trim((string)($_POST['matricula'] ?? ''));
    $gradoEstudios  = trim((string)($_POST['grado_estudios'] ?? ''));
    $nombramiento   = trim((string)($_POST['nombramiento'] ?? ''));
    $horasBase      = ($_POST['horas_base'] ?? '') === '' ? null : (int)$_POST['horas_base'];

    // Nombre completo para USUARIOS
    $nombreCompleto = trim($nombreDocente . ' ' . $apPaterno . ' ' . $apMaterno);

    if ($action === 'create' || $action === 'update') {
        try {
            if ($usuario === '' || $contrasena === '' || $nombreDocente === '') {
                throw new RuntimeException('Usuario, contraseña y nombre del docente son obligatorios.');
            }

            $pdo->beginTransaction();

            // ===================================
            // NUEVO REGISTRO
            // ===================================
            if ($action === 'create') {
                // Insert USUARIOS
                $sqlU = "
                    INSERT INTO dbo.USUARIOS
                        (ID_ROL, ID_DEPARTAMENTO, NOMBRE_USUARIO, CONTRASENA, ACTIVO, NOMBRE_COMPLETO, CORREO)
                    VALUES
                        (:rol, :depto, :user, :pass, :activo, :nombre, :correo)
                ";
                $stU = $pdo->prepare($sqlU);
                $stU->execute([
                    ':rol'    => $idRol,
                    ':depto'  => $idDepto,
                    ':user'   => $usuario,
                    ':pass'   => $contrasena, // OJO: aquí está en texto plano, igual que tu ejemplo
                    ':activo' => $activo,
                    ':nombre' => $nombreCompleto,
                    ':correo' => $correo
                ]);
                $idUsuario = (int)$pdo->lastInsertId();

                // Insert DOCENTE
                $sqlD = "
                    INSERT INTO dbo.DOCENTE
                        (ID_USUARIO, NOMBRE_DOCENTE, APELLIDO_PATERNO_DOCENTE, APELLIDO_MATERNO_DOCENTE,
                         RFC, CURP, CORREO, TELEFONO_DOCENTE, ACTIVO, CLAVE_EMPLEADO, NSS,
                         FECHA_INGRESO, MATRICULA, GRADO_ESTUDIOS, NOMBRAMIENTO, HORAS_BASE)
                    VALUES
                        (:id_usr, :nom, :ap, :am,
                         :rfc, :curp, :correo, :tel, :activo,
                         :clave, :nss, :fing, :mat, :grado, :nombr, :horas)
                ";
                $stD = $pdo->prepare($sqlD);
                $stD->execute([
                    ':id_usr' => $idUsuario,
                    ':nom'    => $nombreDocente,
                    ':ap'     => $apPaterno,
                    ':am'     => $apMaterno,
                    ':rfc'    => $rfc,
                    ':curp'   => $curp,
                    ':correo' => $correo,
                    ':tel'    => $telefono,
                    ':activo' => $activo,
                    ':clave'  => $claveEmpleado,
                    ':nss'    => $nss,
                    ':fing'   => $fechaIngreso,
                    ':mat'    => $matricula,
                    ':grado'  => $gradoEstudios,
                    ':nombr'  => $nombramiento,
                    ':horas'  => $horasBase
                ]);
                $idDocente = (int)$pdo->lastInsertId();

                $pdo->commit();
                $message = "Docente creado correctamente. ID_DOCENTE={$idDocente}, ID_USUARIO={$idUsuario}";
                $currentIdDoc = $idDocente;

            // ===================================
            // ACTUALIZAR REGISTRO EXISTENTE
            // ===================================
            } elseif ($action === 'update') {
                if ($idUsuario <= 0 || $idDocente <= 0) {
                    throw new RuntimeException('Faltan IDs para actualizar (ID_USUARIO / ID_DOCENTE).');
                }

                // Update USUARIOS
                $sqlU = "
                    UPDATE dbo.USUARIOS
                    SET ID_ROL = :rol,
                        ID_DEPARTAMENTO = :depto,
                        NOMBRE_USUARIO = :user,
                        CONTRASENA = :pass,
                        ACTIVO = :activo,
                        NOMBRE_COMPLETO = :nombre,
                        CORREO = :correo
                    WHERE ID_USUARIO = :id
                ";
                $stU = $pdo->prepare($sqlU);
                $stU->execute([
                    ':rol'    => $idRol,
                    ':depto'  => $idDepto,
                    ':user'   => $usuario,
                    ':pass'   => $contrasena,
                    ':activo' => $activo,
                    ':nombre' => $nombreCompleto,
                    ':correo' => $correo,
                    ':id'     => $idUsuario
                ]);

                // Update DOCENTE
                $sqlD = "
                    UPDATE dbo.DOCENTE
                    SET NOMBRE_DOCENTE = :nom,
                        APELLIDO_PATERNO_DOCENTE = :ap,
                        APELLIDO_MATERNO_DOCENTE = :am,
                        RFC = :rfc,
                        CURP = :curp,
                        CORREO = :correo,
                        TELEFONO_DOCENTE = :tel,
                        ACTIVO = :activo,
                        CLAVE_EMPLEADO = :clave,
                        NSS = :nss,
                        FECHA_INGRESO = :fing,
                        MATRICULA = :mat,
                        GRADO_ESTUDIOS = :grado,
                        NOMBRAMIENTO = :nombr,
                        HORAS_BASE = :horas
                    WHERE ID_DOCENTE = :id_doc
                ";
                $stD = $pdo->prepare($sqlD);
                $stD->execute([
                    ':nom'    => $nombreDocente,
                    ':ap'     => $apPaterno,
                    ':am'     => $apMaterno,
                    ':rfc'    => $rfc,
                    ':curp'   => $curp,
                    ':correo' => $correo,
                    ':tel'    => $telefono,
                    ':activo' => $activo,
                    ':clave'  => $claveEmpleado,
                    ':nss'    => $nss,
                    ':fing'   => $fechaIngreso,
                    ':mat'    => $matricula,
                    ':grado'  => $gradoEstudios,
                    ':nombr'  => $nombramiento,
                    ':horas'  => $horasBase,
                    ':id_doc' => $idDocente
                ]);

                $pdo->commit();
                $message = "Registros actualizados correctamente (ID_DOCENTE={$idDocente}).";
                $currentIdDoc = $idDocente;
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// =======================================
// CARGAR LISTA DE DOCENTES PARA LA TABLA
// =======================================
$listStmt = $pdo->query("
    SELECT D.ID_DOCENTE, U.ID_USUARIO, U.NOMBRE_USUARIO,
           D.NOMBRE_DOCENTE, D.APELLIDO_PATERNO_DOCENTE, D.APELLIDO_MATERNO_DOCENTE,
           U.ID_DEPARTAMENTO, U.ID_ROL
    FROM dbo.DOCENTE D
    JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
    ORDER BY D.ID_DOCENTE DESC
");
$listaDocentes = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// =======================================
// CARGAR REGISTRO PARA EDICIÓN (SI APLICA)
// =======================================
$editIdDoc = isset($_GET['id_docente']) ? (int)$_GET['id_docente'] : 0;
if ($currentIdDoc > 0) {
    // Después de crear/actualizar, mostramos ese mismo
    $editIdDoc = $currentIdDoc;
}

$current = [
    'U_ID_USUARIO'      => '',
    'U_ID_ROL'          => 1,
    'U_ID_DEPARTAMENTO' => '',
    'U_NOMBRE_USUARIO'  => '',
    'U_CONTRASENA'      => '',
    'U_ACTIVO'          => 1,
    'D_ID_DOCENTE'      => '',
    'D_NOMBRE_DOCENTE'  => '',
    'D_AP_PATERNO'      => '',
    'D_AP_MATERNO'      => '',
    'D_RFC'             => '',
    'D_CURP'            => '',
    'D_CORREO'          => '',
    'D_TELEFONO'        => '',
    'D_ACTIVO'          => 1,
    'D_CLAVE_EMPLEADO'  => '',
    'D_NSS'             => '',
    'D_FECHA_INGRESO'   => '',
    'D_MATRICULA'       => '',
    'D_GRADO_ESTUDIOS'  => '',
    'D_NOMBRAMIENTO'    => '',
    'D_HORAS_BASE'      => ''
];

if ($editIdDoc > 0) {
    $st = $pdo->prepare("
        SELECT
            U.ID_USUARIO              AS U_ID_USUARIO,
            U.ID_ROL                  AS U_ID_ROL,
            U.ID_DEPARTAMENTO         AS U_ID_DEPARTAMENTO,
            U.NOMBRE_USUARIO          AS U_NOMBRE_USUARIO,
            U.CONTRASENA              AS U_CONTRASENA,
            U.ACTIVO                  AS U_ACTIVO,
            D.ID_DOCENTE              AS D_ID_DOCENTE,
            D.NOMBRE_DOCENTE          AS D_NOMBRE_DOCENTE,
            D.APELLIDO_PATERNO_DOCENTE AS D_AP_PATERNO,
            D.APELLIDO_MATERNO_DOCENTE AS D_AP_MATERNO,
            D.RFC                     AS D_RFC,
            D.CURP                    AS D_CURP,
            D.CORREO                  AS D_CORREO,
            D.TELEFONO_DOCENTE        AS D_TELEFONO,
            D.ACTIVO                  AS D_ACTIVO,
            D.CLAVE_EMPLEADO          AS D_CLAVE_EMPLEADO,
            D.NSS                     AS D_NSS,
            CONVERT(varchar(10), D.FECHA_INGRESO, 23) AS D_FECHA_INGRESO, -- YYYY-MM-DD
            D.MATRICULA               AS D_MATRICULA,
            D.GRADO_ESTUDIOS          AS D_GRADO_ESTUDIOS,
            D.NOMBRAMIENTO            AS D_NOMBRAMIENTO,
            D.HORAS_BASE              AS D_HORAS_BASE
        FROM dbo.DOCENTE D
        JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
        WHERE D.ID_DOCENTE = :id
    ");
    $st->execute([':id' => $editIdDoc]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $current = array_merge($current, $row);
    }
}

// Helpers para checked
function checked($v): string {
    return ((int)$v === 1) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD rápido USUARIOS + DOCENTE</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 20px;
            background: #f5f5f5;
        }
        h1 { margin-bottom: 5px; }
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
            font-size: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
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
        input[type="date"],
        input[type="password"],
        input[type="email"] {
            padding: 5px 7px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 13px;
        }
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
        .btn-secondary { background: #6b7280; color: #fff; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 4px 6px;
            border: 1px solid #ddd;
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
    </style>
</head>
<body>

<h1>CRUD rápido · USUARIOS + DOCENTE</h1>
<p><strong>IMPORTANTE:</strong> herramienta interna. No dejar pública en producción.</p>

<?php if ($message): ?>
    <div class="msg-ok"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="msg-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="wrapper">

    <!-- PANEL FORMULARIO -->
    <div class="panel">
        <h2><?= $current['D_ID_DOCENTE'] ? 'Editar docente existente' : 'Nuevo docente + usuario' ?></h2>
        <form method="post">
            <input type="hidden" name="u_id_usuario" value="<?= htmlspecialchars((string)$current['U_ID_USUARIO']) ?>">
            <input type="hidden" name="d_id_docente" value="<?= htmlspecialchars((string)$current['D_ID_DOCENTE']) ?>">

            <h3>Datos de USUARIO</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>ID_ROL</label>
                    <input type="number" name="id_rol" value="<?= htmlspecialchars((string)$current['U_ID_ROL']) ?>">
                </div>
                <div class="form-group">
                    <label>ID_DEPARTAMENTO</label>
                    <input type="number" name="id_depto" value="<?= htmlspecialchars((string)$current['U_ID_DEPARTAMENTO']) ?>">
                </div>
                <div class="form-group">
                    <label>NOMBRE_USUARIO</label>
                    <input type="text" name="usuario" value="<?= htmlspecialchars((string)$current['U_NOMBRE_USUARIO']) ?>">
                </div>
                <div class="form-group">
                    <label>CONTRASEÑA (texto plano, igual que tu BD)</label>
                    <input type="text" name="contrasena" value="<?= htmlspecialchars((string)$current['U_CONTRASENA']) ?>">
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="chkActivo" name="activo" value="1" <?= checked($current['U_ACTIVO']) ?>>
                    <label for="chkActivo">ACTIVO (aplica a usuario y docente)</label>
                </div>
            </div>

            <h3>Datos de DOCENTE</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre_docente" value="<?= htmlspecialchars((string)$current['D_NOMBRE_DOCENTE']) ?>">
                </div>
                <div class="form-group">
                    <label>Apellido paterno</label>
                    <input type="text" name="apellido_paterno" value="<?= htmlspecialchars((string)$current['D_AP_PATERNO']) ?>">
                </div>
                <div class="form-group">
                    <label>Apellido materno</label>
                    <input type="text" name="apellido_materno" value="<?= htmlspecialchars((string)$current['D_AP_MATERNO']) ?>">
                </div>
                <div class="form-group">
                    <label>RFC</label>
                    <input type="text" name="rfc" value="<?= htmlspecialchars((string)$current['D_RFC']) ?>">
                </div>
                <div class="form-group">
                    <label>CURP</label>
                    <input type="text" name="curp" value="<?= htmlspecialchars((string)$current['D_CURP']) ?>">
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" value="<?= htmlspecialchars((string)$current['D_CORREO']) ?>">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars((string)$current['D_TELEFONO']) ?>">
                </div>
                <div class="form-group">
                    <label>Clave empleado</label>
                    <input type="text" name="clave_empleado" value="<?= htmlspecialchars((string)$current['D_CLAVE_EMPLEADO']) ?>">
                </div>
                <div class="form-group">
                    <label>NSS</label>
                    <input type="text" name="nss" value="<?= htmlspecialchars((string)$current['D_NSS']) ?>">
                </div>
                <div class="form-group">
                    <label>Fecha ingreso</label>
                    <input type="date" name="fecha_ingreso" value="<?= htmlspecialchars((string)$current['D_FECHA_INGRESO']) ?>">
                </div>
                <div class="form-group">
                    <label>Matrícula</label>
                    <input type="text" name="matricula" value="<?= htmlspecialchars((string)$current['D_MATRICULA']) ?>">
                </div>
                <div class="form-group">
                    <label>Grado de estudios</label>
                    <input type="text" name="grado_estudios" value="<?= htmlspecialchars((string)$current['D_GRADO_ESTUDIOS']) ?>">
                </div>
                <div class="form-group">
                    <label>Nombramiento</label>
                    <input type="text" name="nombramiento" value="<?= htmlspecialchars((string)$current['D_NOMBRAMIENTO']) ?>">
                </div>
                <div class="form-group">
                    <label>Horas base</label>
                    <input type="number" name="horas_base" value="<?= htmlspecialchars((string)$current['D_HORAS_BASE']) ?>">
                </div>
            </div>

            <div class="actions">
                <button type="submit" name="action" value="<?= $current['D_ID_DOCENTE'] ? 'update' : 'create' ?>" class="btn-primary">
                    <?= $current['D_ID_DOCENTE'] ? 'Actualizar registro' : 'Crear nuevo registro' ?>
                </button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn-secondary" style="text-decoration:none;display:inline-block;padding:6px 10px;">
                    Limpiar formulario
                </a>
            </div>
        </form>
    </div>

    <!-- PANEL LISTA -->
    <div class="panel">
        <h2>Docentes existentes</h2>
        <table>
            <thead>
            <tr>
                <th>ID_DOCENTE</th>
                <th>ID_USUARIO</th>
                <th>Usuario</th>
                <th>Nombre docente</th>
                <th>Depto</th>
                <th>Rol</th>
                <th>Acción</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$listaDocentes): ?>
                <tr><td colspan="7" style="text-align:center;">Sin registros</td></tr>
            <?php else: ?>
                <?php foreach ($listaDocentes as $d): ?>
                    <tr>
                        <td class="id-pill"><?= (int)$d['ID_DOCENTE'] ?></td>
                        <td><?= (int)$d['ID_USUARIO'] ?></td>
                        <td><?= htmlspecialchars((string)$d['NOMBRE_USUARIO']) ?></td>
                        <td><?= htmlspecialchars(trim($d['NOMBRE_DOCENTE'].' '.$d['APELLIDO_PATERNO_DOCENTE'].' '.$d['APELLIDO_MATERNO_DOCENTE'])) ?></td>
                        <td><?= htmlspecialchars((string)$d['ID_DEPARTAMENTO']) ?></td>
                        <td><?= htmlspecialchars((string)$d['ID_ROL']) ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?id_docente=' . (int)$d['ID_DOCENTE'] ?>">Editar</a>
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
