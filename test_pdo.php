<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
try {
  $conn = new PDO("sqlsrv:Server=localhost;Database=SIGED", "usuario_demo", "pass_demo");
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "OK PDO_SQLSRV conectado a SIGED";
} catch (Throwable $e) {
  echo "Error de conexión: " . $e->getMessage();
}
