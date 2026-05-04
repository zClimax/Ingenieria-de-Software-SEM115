# SIGED — Sistema de Gestión de Documentos

Sistema web para la gestión de solicitudes de documentos académicos del personal docente: constancias, oficios, firmas digitales, tickets y generación automática de PDFs.

Desarrollado como proyecto de la materia **Ingeniería de Software (SEM115)**.

---

## Stack tecnológico

- **PHP 8.x** — backend y generación de vistas
- **SQL Server** — base de datos (PDO + driver `sqlsrv`)
- **TCPDF** — generación de documentos PDF
- **HTML / CSS / JavaScript** — frontend sin framework

---

## Módulos del sistema

| Módulo | Descripción |
|---|---|
| **Auth** | Login por rol: Docente o Jefe de Departamento |
| **Solicitudes** | Alta, edición, envío y seguimiento de solicitudes de documentos |
| **PDF** | Generación automática de constancias y oficios firmados (30+ tipos) |
| **Docente** | Panel del docente: historial, firma, foto de perfil |
| **Jefe** | Bandeja de aprobación/rechazo con comentarios |
| **Tickets** | Sistema de soporte interno entre docente y jefe |
| **Convocatoria** | Gestión de convocatorias y requisitos por departamento |
| **Usuarios/Tools** | CRUD de usuarios y configuración administrativa |

---

## Requisitos previos

- PHP ≥ 8.0 con extensiones: `pdo_sqlsrv`, `gd`, `mbstring`
- SQL Server o SQL Server Express
- Driver ODBC de SQL Server para PHP
- Apache/IIS con DocumentRoot apuntando a `public/`
- Composer 2.x

---

## Instalación

```bash
git clone https://github.com/zClimax/Ingenieria-de-Software-SEM115.git
cd Ingenieria-de-Software-SEM115

# Instalar dependencias PHP
composer install

# Configurar credenciales de base de datos
cp .env.example .env
# Editar app/php/config.php con tus datos de conexión

# Crear la base de datos
# Importar database/seeds.sql en SQL Server Management Studio
# o con sqlcmd:
sqlcmd -S localhost -U sa -P tu_password -i database/seeds.sql
```

Apunta el DocumentRoot del servidor web a la carpeta `public/`.

> **Aviso de seguridad:** Las credenciales de base de datos están actualmente hardcodeadas en `app/php/config.php`. En producción deben moverse a variables de entorno.

---

## Estructura del proyecto

```
SIGED/
├── public/                    # DocumentRoot — entry point web
│   ├── index.php              # Front controller único
│   ├── css/                   # Hojas de estilo por vista
│   ├── js/                    # Scripts por vista
│   ├── img/                   # Imágenes e iconos
│   └── storage/               # Archivos servidos públicamente (no versionados)
│       ├── firmas/            # Imágenes de firmas
│       ├── fotos/             # Fotos de perfil
│       └── pdfs/              # PDFs generados
│
├── app/                       # Lógica de la aplicación
│   ├── php/                   # Código PHP de negocio
│   │   ├── config.php         # Configuración de BD y constantes
│   │   ├── routes.php         # Router principal (despacha por ?action=)
│   │   ├── utils/             # Session.php, roles.php
│   │   ├── auth/              # Login, logout, elegir rol
│   │   ├── docente/           # Vistas y lógica del docente
│   │   ├── solicitudes/       # CRUD de solicitudes + generación PDF
│   │   ├── tickets/           # Sistema de tickets (docente)
│   │   ├── tickets_jefe/      # Sistema de tickets (jefe)
│   │   ├── convocatoria/      # Gestión de convocatorias
│   │   ├── usuarios/          # Paneles de usuario por rol
│   │   ├── tools/             # Herramientas administrativas
│   │   └── pdf/               # Utilidades de PDF (firma_pdf.php)
│   ├── css/                   # CSS adicional
│   ├── js/                    # JS adicional
│   ├── pdf/
│   │   ├── plantillas/        # HTML de constancias y oficios
│   │   └── salidas/           # PDFs generados temporales (no versionados)
│   └── uploads/               # Archivos subidos por usuarios (no versionados)
│       └── evidencias/
│
├── pdf/                       # Assets y plantillas principales de PDF
│   ├── assets/                # Logos y firmas institucionales
│   └── plantillas/            # Plantillas HTML para generación de PDFs (30+)
│
├── database/
│   └── seeds.sql              # Esquema y datos iniciales de la BD
│
├── docs/                      # Documentación del proyecto
│   ├── IEEE830/               # Especificación de requisitos (IEEE 830)
│   │   ├── README.md
│   │   └── Actividad_1_FER_IEEE830.pdf
│   └── CHANGELOG.md           # Historial de cambios
│
├── tests/
│   └── test_pdo.php           # Script de prueba de conexión a BD
│
├── vendor/                    # Dependencias Composer (no versionado)
├── composer.json
├── composer.lock
├── .env.example               # Plantilla de variables de entorno
└── .gitignore
```

---

## Uso

Una vez levantado el servidor, accede a `http://localhost/siged/`. El sistema redirige al login según el rol:

- **Docente**: Accede con correo electrónico institucional
- **Jefe de Departamento**: Accede con usuario y contraseña

---

## Documentación

- [Especificación de Requisitos IEEE 830](docs/IEEE830/README.md)
- [Historial de cambios](docs/CHANGELOG.md)

---

## Autores

Proyecto académico — Ingeniería de Software SEM115  
[zClimax](https://github.com/zClimax)
