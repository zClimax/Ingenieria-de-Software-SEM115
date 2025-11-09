<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
$pdo = DB::conn();

$U = Config::MAP['USUARIOS'];
$D = Config::MAP['DOCENTE'];

$tu=$U['TABLE']; $uId=$U['ID']; $uRol=$U['ID_ROL']; $uDep=$U['DEP']; $uUser=$U['USER']; $uPass=$U['PASS']; $uActivo=$U['ACTIVO'];
$td=$D['TABLE']; $dId=$D['ID']; $dUsr=$D['ID_USR']; $dNom=$D['NOMBRE']; $dAp=$D['AP_PAT']; $dAm=$D['AP_MAT']; $dMail=$D['CORREO']; $dActivo=$D['ACTIVO'];

// Si en tu MAP existe la llave MAIL para USUARIOS, úsala; si no, CORREO por defecto
$uMail = $U['MAIL'] ?? 'CORREO';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ident  = trim($_POST['correo'] ?? '');   // puede ser correo o nombre de usuario
  $pass   = $_POST['password'] ?? '';

  if ($ident === '') {
    $error = 'Ingresa tu correo (docente/jefe) o usuario (jefe).';
  } else {
    $row = null;

    if (strpos($ident, '@') !== false) {
      // ===== 1) DOCENTE por correo (tu flujo original) =====
      $sql = "
        SELECT 
          U.$uId    AS uid,
          U.$uRol   AS id_rol,
          U.$uDep   AS id_dep,
          U.$uUser  AS user_name,
          U.$uPass  AS pass,
          U.$uActivo AS u_activo,
          D.$dId    AS id_docente,
          D.$dNom   AS d_nombre,
          D.$dAp    AS d_ap,
          D.$dAm    AS d_am,
          D.$dMail  AS correo,
          D.$dActivo AS d_activo
        FROM $td D
        INNER JOIN $tu U ON U.$uId = D.$dUsr
        WHERE LOWER(LTRIM(RTRIM(D.$dMail))) = LOWER(LTRIM(RTRIM(:ident)))
      ";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':ident' => $ident]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row && ((int)$row['id_rol'] !== 1)) {
        $error = 'Tu cuenta no tiene rol DOCENTE. Si eres JEFE usa tu correo o tu usuario.';
        $row = null;
      }

      // ===== 2) JEFE por correo (NUEVO): si no fue DOCENTE, buscar en USUARIOS por CORREO normalizado =====
      if (!$row && !$error) {
        // Normalización robusta en SQL: lower + trim + remueve NBSP(160), TAB(9), CR(13), LF(10) y espacios
        $sqlJ = "
          SELECT 
            U.$uId      AS uid,
            U.$uRol     AS id_rol,
            U.$uDep     AS id_dep,
            U.$uUser    AS user_name,
            U.$uPass    AS pass,
            U.$uActivo  AS u_activo,
            U.$uMail    AS correo
          FROM $tu U
          WHERE 
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(LTRIM(RTRIM(U.$uMail))),
                    CHAR(160), ''),  -- NBSP
                    CHAR(9),   ''),  -- TAB
                    CHAR(13),  ''),  -- CR
                    CHAR(10),  ''),  -- LF
                    ' ',      ''     -- espacio normal
            ) = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(LTRIM(RTRIM(:ident))),
                    CHAR(160), ''), CHAR(9), ''), CHAR(13), ''), CHAR(10), ''), ' ', '')
        ";
        $stmt = $pdo->prepare($sqlJ);
        $stmt->execute([':ident' => $ident]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && ((int)$row['id_rol'] !== 2)) {
          // Encontrado por correo pero no es Jefe
          $error = 'Tu cuenta no tiene rol JEFE_DEPARTAMENTO.';
          $row = null;
        }
      }

    } else {
      // ===== 3) JEFE por NOMBRE_USUARIO (flujo original) =====
      $sql = "
        SELECT 
          U.$uId     AS uid,
          U.$uRol    AS id_rol,
          U.$uDep    AS id_dep,
          U.$uUser   AS user_name,
          U.$uPass   AS pass,
          U.$uActivo AS u_activo
        FROM $tu U
        WHERE U.$uUser = :ident
      ";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':ident' => $ident]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row && ((int)$row['id_rol'] !== 2)) {
        $error = 'Tu cuenta no tiene rol JEFE_DEPARTAMENTO. Si eres DOCENTE usa tu correo institucional.';
        $row = null;
      }
    }

    if (!$row && !$error) {
      $error = 'Usuario no encontrado.';
    } elseif ($row && (int)($row['u_activo'] ?? 1) !== 1) {
      $error = 'Usuario inactivo.';
    } elseif ($row) {
      // Comparación plano (mismo criterio que ya tenías)
      $ok = true;
      if (!empty($row['pass'])) {
        $ok = ($pass !== '') && hash_equals((string)$row['pass'], (string)$pass);
      }

      if ($ok) {
        $rol = mapRol((int)$row['id_rol']);
        $nombreVisible = $row['user_name'];

        // Si es docente y tenemos su nombre, usar nombre completo
        if ($rol === 'DOCENTE' && !empty($row['d_nombre'])) {
          $nombreVisible = trim($row['d_nombre'].' '.($row['d_ap'] ?? '').' '.($row['d_am'] ?? ''));
        }

        Session::login([
          'id'             => (int)$row['uid'],
          'nombre'         => $nombreVisible,
          'correo'         => $row['correo'] ?? $row['user_name'],
          'rol'            => $rol,
          'id_departamento'=> isset($row['id_dep']) ? (int)$row['id_dep'] : null,
        ]);

        // Redirección al router por rol (como lo dejaste)
        if ($rol === 'DOCENTE') {
          header('Location: /siged/public/index.php?action=home_docente'); 
        } else {
          header('Location: /siged/public/index.php?action=home_jefe'); 
        }
        exit;

      } else {
        $error = 'Credenciales inválidas.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Iniciar sesión</title>
  <link rel="stylesheet" href="/siged/app/css/styles.css">
</head>
<body class="layout">
  <main class="card">
    <h1>Iniciar sesión</h1>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Correo (docente/jefe) o Usuario (jefe)
        <input type="text" name="correo" placeholder="vbatiz@culiacan.tecnm.mx  |  jefe@escuela.mx  |  RaulAyon" required>
      </label>
      <label>Contraseña
        <input type="password" name="password" placeholder="(opcional en dev)">
      </label>
      <button type="submit">Entrar</button>
    </form>
  </main>
</body>
</html>
