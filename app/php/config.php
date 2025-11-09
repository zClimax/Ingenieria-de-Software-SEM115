<?php
declare(strict_types=1);

final class Config {
  // === Ajusta estos valores a tu entorno local ===
  public const DB_SERVER   = 'localhost';          // o 'localhost\\SQLEXPRESS'
  public const DB_NAME     = 'SIGED';
  public const DB_USER     = 'usuario_demo';       
  public const DB_PASSWORD = 'pass_demo';         
  public const APP_ENV     = 'local';             
  public const APP_NAME    = 'SIGED';

  // === Mapeo de tablas/campos 
  public const MAP = [
    'USUARIOS' => [
      'TABLE'    => 'dbo.USUARIOS',
      'ID'       => 'ID_USUARIO',
      'ID_ROL'   => 'ID_ROL',            // 1=DOCENTE, 2=JEFE_DEPARTAMENTO
      'DEP'      => 'ID_DEPARTAMENTO',
      'USER'     => 'NOMBRE_USUARIO',    // login para JEFE
      'PASS'     => 'CONTRASENA',        // texto plano en dev (luego hasheamos)
      'ACTIVO'   => 'ACTIVO',
      'NOMBRE_COMPLETO' => 'NOMBRE_COMPLETO',
    ],
    'DOCENTE' => [
      'TABLE'     => 'dbo.DOCENTE',
      'ID'        => 'ID_DOCENTE',
      'ID_USR'    => 'ID_USUARIO',
      'NOMBRE'    => 'NOMBRE_DOCENTE',
      'AP_PAT'    => 'APELLIDO_PATERNO_DOCENTE',
      'AP_MAT'    => 'APELLIDO_MATERNO_DOCENTE',
      'RFC'       => 'RFC',
      'CURP'      => 'CURP',
      'CORREO'    => 'CORREO',           // login para DOCENTE
      'TEL'       => 'TELEFONO_DOCENTE',
      'ACTIVO'    => 'ACTIVO',
    ],
    'SOLICITUD' => [
      'TABLE'   => 'dbo.SOLICITUD_DOCUMENTO',
      'ID'      => 'ID_SOLICITUD',
      'DOC'     => 'ID_DOCENTE',
      'DEP'     => 'ID_DEPARTAMENTO',
      'TIPO'    => 'TIPO_DOCUMENTO',
      'ESTADO'  => 'ESTADO',
      'F_CRE'   => 'FECHA_CREACION',
      'F_ENV'   => 'FECHA_ENVIO',
      'F_DEC'   => 'FECHA_DECISION',
      'COM_J'   => 'COMENTARIO_JEFE',
      'PDF_PATH' => 'RUTA_PDF',
      'FOLIO'    => 'FOLIO',
      'HASH'     => 'HASH_QR',
      'DEP_APROB' => 'ID_DEPARTAMENTO_APROBADOR',
    ],
    'EVID' => [
      'TABLE'   => 'dbo.SOLICITUD_EVIDENCIA',
      'ID'      => 'ID_EVIDENCIA',
      'SOL'     => 'ID_SOLICITUD',
      'NOM'     => 'NOMBRE_ARCHIVO',
      'RUTA'    => 'RUTA_SISTEMA',
      'MIME'    => 'TIPO_MIME',
      'BYTES'   => 'PESO_BYTES',
      'F_SUB'   => 'SUBIDO_EN',
    ],
    'RUTEADOR' => [
      'TABLE' => 'dbo.RUTEADOR_DOCUMENTO',
      'TIPO'  => 'TIPO_DOCUMENTO',
      'DEP'   => 'ID_DEPARTAMENTO',
    ],
'CONVOCATORIA' => [
  'TABLE' => 'dbo.CONVOCATORIA',
  'ID'    => 'ID_CONVOCATORIA',
  'CLAVE' => 'CLAVE',
  'NOM'   => 'NOMBRE_CONVOCATORIA',
  'ANIO'  => 'ANIO',
  'INI'   => 'FECHA_INICIO',
  'FIN'   => 'FECHA_FIN',
  'ACT'   => 'ACTIVO',
],
  'ACK' => [
    'TABLE' => 'dbo.DOCENTE_CONVOCATORIA_ACK',
    'ID'    => 'ID_ACK',
    'DOC'   => 'ID_DOCENTE',
    'CONV'  => 'ID_CONVOCATORIA',
    'F'     => 'FECHA_ACK',
  ],
  'REQ' => [
  'TABLE'=>'dbo.REQUISITO','ID'=>'ID_REQUISITO','CLAVE'=>'CLAVE',
  'NOM'=>'NOMBRE','ORD'=>'ORDEN','ACT'=>'ACTIVO'
],
'CONV_REQ' => [
  'TABLE'=>'dbo.CONVOCATORIA_REQUISITO','ID'=>'ID_CONVOCATORIA_REQUISITO',
  'CONV'=>'ID_CONVOCATORIA','REQ'=>'ID_REQUISITO','OBL'=>'OBLIGATORIO'
],
'DRC' => [
  'TABLE'=>'dbo.DOCENTE_REQUISITO_CONV','ID'=>'ID_DRC',
  'DOC'=>'ID_DOCENTE','CONV'=>'ID_CONVOCATORIA','REQ'=>'ID_REQUISITO',
  'OK'=>'CUMPLE','DET'=>'DETALLE','F'=>'FECHA_EVAL'
],

  ];
  
}

final class DB {
  private static ?\PDO $pdo = null;

  public static function conn(): \PDO {
    if (self::$pdo === null) {
      $dsn = "sqlsrv:Server=" . Config::DB_SERVER . ";Database=" . Config::DB_NAME;
      $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
      ];
      self::$pdo = new \PDO($dsn, Config::DB_USER, Config::DB_PASSWORD, $options);
    }
    return self::$pdo;
  }
}
