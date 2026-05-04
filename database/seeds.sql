-- ROLES si aplica una tabla independiente
-- INSERT INTO ROL (IdRol, Nombre) VALUES (1,'DOCENTE'),(2,'JEFE_DEPARTAMENTO');

-- USUARIOS (campos de ejemplo; ajusta a tu estructura real)
-- Contraseña "demo123"
DECLARE @hash NVARCHAR(255) = '$2y$10$USE_PHP_PASSWORD_HASH_EN_RUNTIME';

INSERT INTO USUARIOS (Nombre, Correo, ContrasenaHash, Rol)
VALUES
  ('Juan Pérez', 'juan.perez@culiacan.tenm.mx', NULL, 'DOCENTE'),
  ('Ana Jefa', 'ana.jefa@culiacan.tecnm.mx', NULL, 'JEFE_DEPARTAMENTO');
