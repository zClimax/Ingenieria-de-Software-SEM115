<?php
declare(strict_types=1);

final class Session {
  public static function start(): void {
    if (session_status() === PHP_SESSION_NONE) {
      session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
      ]);
      
      @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',   // ← importante
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_start();
    }

  }

  public static function login(array $user): void {
    $_SESSION['user'] = [
      'id'    => $user['id'],
      'nombre'=> $user['nombre'],
      'correo'=> $user['correo'],
      'rol'   => $user['rol'],
    ];
  }

  public static function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
  }

  public static function user(): ?array {
    return $_SESSION['user'] ?? null;
  }
 

}
