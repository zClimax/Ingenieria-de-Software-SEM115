<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
$pdo = DB::conn();

// 1. Capturamos el rol esperado (si viene de los botones de selección)
//    Esto es opcional, pero sirve para mostrar títulos diferentes o validar.
$rolEsperado = $_GET['rol'] ?? $_POST['rol_esperado'] ?? '';

// Configuración de tablas
$U = Config::MAP['USUARIOS'];
$D = Config::MAP['DOCENTE'];

$tu=$U['TABLE']; $uId=$U['ID']; $uRol=$U['ID_ROL']; $uDep=$U['DEP']; $uUser=$U['USER']; $uPass=$U['PASS']; $uActivo=$U['ACTIVO'];
$td=$D['TABLE']; $dId=$D['ID']; $dUsr=$D['ID_USR']; $dNom=$D['NOMBRE']; $dAp=$D['AP_PAT']; $dAm=$D['AP_MAT']; $dMail=$D['CORREO']; $dActivo=$D['ACTIVO'];
$uMail = $U['MAIL'] ?? 'CORREO';

$error = '';

// --- PROCESAMIENTO DEL LOGIN (TU LÓGICA ORIGINAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ident  = trim($_POST['correo'] ?? '');   // IMPORTANTE: El input HTML debe tener name="correo"
  $pass   = $_POST['password'] ?? '';
  $rolEsperado = $_POST['rol_esperado'] ?? ''; 

  if ($ident === '') {
    $error = 'Por favor ingresa tus credenciales.';
  } else {
    $row = null;
    
    // Búsqueda inteligente (Correo Docente -> Correo Jefe -> Usuario Jefe)
    if (strpos($ident, '@') !== false) {
      // 1) DOCENTE por correo
      $sql = "SELECT U.$uId AS uid, U.$uRol AS id_rol, U.$uDep AS id_dep, U.$uUser AS user_name, U.$uPass AS pass, U.$uActivo AS u_activo, D.$dId AS id_docente, D.$dNom AS d_nombre, D.$dAp AS d_ap, D.$dAm AS d_am, D.$dMail AS correo, D.$dActivo AS d_activo FROM $td D INNER JOIN $tu U ON U.$uId = D.$dUsr WHERE LOWER(LTRIM(RTRIM(D.$dMail))) = LOWER(LTRIM(RTRIM(:ident)))";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':ident' => $ident]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row && ((int)$row['id_rol'] !== 1)) {
        $error = 'Tu cuenta no tiene rol DOCENTE. Intenta como Jefe.';
        $row = null;
      }

      // 2) JEFE por correo
      if (!$row && !$error) {
        $sqlJ = "SELECT U.$uId AS uid, U.$uRol AS id_rol, U.$uDep AS id_dep, U.$uUser AS user_name, U.$uPass AS pass, U.$uActivo AS u_activo, U.$uMail AS correo FROM $tu U WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(LTRIM(RTRIM(U.$uMail))), CHAR(160), ''), CHAR(9), ''), CHAR(13), ''), CHAR(10), ''), ' ', '') = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(LTRIM(RTRIM(:ident))), CHAR(160), ''), CHAR(9), ''), CHAR(13), ''), CHAR(10), ''), ' ', '')";
        $stmt = $pdo->prepare($sqlJ);
        $stmt->execute([':ident' => $ident]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && ((int)$row['id_rol'] !== 2)) {
          $error = 'Tu cuenta no es de JEFE DE DEPARTAMENTO.';
          $row = null;
        }
      }
    } else {
      // 3) JEFE por usuario
      $sql = "SELECT U.$uId AS uid, U.$uRol AS id_rol, U.$uDep AS id_dep, U.$uUser AS user_name, U.$uPass AS pass, U.$uActivo AS u_activo FROM $tu U WHERE U.$uUser = :ident";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':ident' => $ident]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row && ((int)$row['id_rol'] !== 2)) {
        $error = 'Tu cuenta no es JEFE DE DEPARTAMENTO.';
        $row = null;
      }
    }

    // Verificación final
    if (!$row && !$error) {
      $error = 'Usuario no encontrado.';
    } elseif ($row && (int)($row['u_activo'] ?? 1) !== 1) {
      $error = 'Usuario inactivo.';
    } elseif ($row) {
      $ok = true;
      if (!empty($row['pass'])) {
        $ok = ($pass !== '') && hash_equals((string)$row['pass'], (string)$pass);
      }

      if ($ok) {
        $rolEncontrado = mapRol((int)$row['id_rol']);
        
        // Validación: Si entró por botón de Docente pero es Jefe (o viceversa)
        if (!empty($rolEsperado) && $rolEncontrado !== $rolEsperado) {
             $nombreRolBonito = ($rolEsperado === 'DOCENTE') ? 'Docente' : 'Jefe';
             $error = "Contraseña incorrecta y usuario incorrecto para el rol esperado.";
        } else {
            // LOGIN EXITOSO
            $nombreVisible = $row['user_name'];
            if ($rolEncontrado === 'DOCENTE' && !empty($row['d_nombre'])) {
              $nombreVisible = trim($row['d_nombre'].' '.($row['d_ap'] ?? '').' '.($row['d_am'] ?? ''));
            }

            Session::login([
              'id'             => (int)$row['uid'],
              'nombre'         => $nombreVisible,
              'correo'         => $row['correo'] ?? $row['user_name'],
              'rol'            => $rolEncontrado,
              'id_departamento'=> isset($row['id_dep']) ? (int)$row['id_dep'] : null,
            ]);

            // Redirección según rol
            if ($rolEncontrado === 'DOCENTE') {
              header('Location: ?action=home_docente'); 
            } else {
              header('Location: ?action=home_jefe'); 
            }
            exit;
        }
      } else {
        $error = 'Contraseña incorrecta.';
      }
    }
  }
}

// Títulos dinámicos para la interfaz
$tituloHTML = "SIGED - Login";
$tituloCaja = "SIGED";
if ($rolEsperado === 'DOCENTE') { $tituloCaja = "Acceso Docente"; }
if ($rolEsperado === 'JEFE_DEPARTAMENTO') { $tituloCaja = "Acceso Jefatura"; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="/SIGED/public/css/IntergazLoginSIGED.css">
    <title><?php echo htmlspecialchars($tituloHTML); ?></title>
</head>
<body>
    <div class="page-wrapper">
        <section class="Contenedora-login-medio">
            <form class="Formularion-login" id="loginForm" method="POST">
                
                <input type="hidden" name="rol_esperado" value="<?php echo htmlspecialchars($rolEsperado); ?>">

                <article class="logo-tecnm2">
                    <img src="img/tecnm.png" alt="logo-tecnm">
                </article>
                <article class="TITULO-SIGED-GRANDE-AZUL">
                    <?php echo htmlspecialchars($tituloCaja); ?>
                </article>

                <?php if ($error): ?>
                <article class="mensaje-error" style="display: block;">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </article>
                <?php endif; ?>

                <article class="labels-formulario">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="correo" required 
                          placeholder="example@culiacan.tecnm.mx" 
                          value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>">
                </article>
                
                <article class="labels-formulario">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </article>

                <article class="button-azul">
                    <button class="button-azul-completo" type="submit">Iniciar sesión</button>
                </article>

                <article class="button-azul">
                    <button class="button-azul-completo" type="button">Autentica con correo</button>
                </article>

                <!-- <article>
                    <button type="button">soporte SIGED</button>
                </article> -->
                
                <?php if($rolEsperado): ?>
                <article style="text-align:center; margin-top:10px;">
                    <a href="?action=elegir_rol" style="color:#666; text-decoration:none;">← Cambiar rol</a>
                </article>
                <?php endif; ?>
            </form>
        </section>

        <footer class="footer-info">
            <p class="texto-importante">
                Sistema Integral de Gestión de Documentos
            </p>
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> SIGED - Todos los derechos reservados
            </p>
        </footer>
    </div>
</body>
</html>