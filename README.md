# Proyecto: Sistema de Gestión Universitaria Integral

## Descripción General del Proyecto

Este repositorio contiene el diseño y los scripts SQL para un **Sistema de Gestión Universitaria Integral**. El proyecto está diseñado para administrar de forma modular las operaciones clave de una institución de educación superior, separando las responsabilidades en dominios de negocio claros.

El sistema se descompone en cuatro bases de datos (módulos) independientes pero lógicamente relacionadas:

1.  **Servicios Escolares:** Gestión de alumnos, oferta académica e inscripciones.
2.  **Desarrollo Académico:** Gestión de docentes, tutorías y evaluaciones.
3.  **Recursos Humanos:** Gestión de todo el personal, puestos y nómina.
4.  **Investigación:** Gestión de proyectos científicos y publicaciones.

## Arquitectura del Sistema

El diseño sigue un enfoque de **Bases de Datos por Módulo** (similar a una arquitectura de microservicios). Cada base de datos tiene una responsabilidad única y bien definida, lo que permite el desacoplamiento, la escalabilidad independiente y un mantenimiento más sencillo.



La interconexión entre módulos (por ejemplo, vincular a un `DOCENTE` con un `INVESTIGADOR` o un `EMPLEADO`) no se maneja con llaves foráneas directas entre las bases de datos. Esta sincronización debe ser gestionada por la **capa de aplicación** o un bus de eventos.

Por ejemplo, un `ID_Empleado` de la base de datos `RecursosHumanos` puede ser el identificador único maestro que se replica en las tablas `DOCENTE` e `INVESTIGADOR` para mantener una identidad coherente a través del sistema.

---

## Módulos de la Base de Datos

A continuación, se describe cada uno de los módulos que componen el sistema.

### 1. Módulo: Servicios Escolares
* **Base de Datos:** `serviciosescolares.sql`
* **Propósito:** Administra el núcleo académico y estudiantil. Es responsable de la vida del alumno, desde su admisión hasta su egreso.
* **Entidades Clave:**
    * `Carrera`: La oferta académica (ej. "Ing. de Software").
    * `Plan_Estudios`: La currícula de una carrera (ej. "Plan 2024").
    * `Materia`: Catálogo maestro de asignaturas.
    * `Periodo_Escolar`: Los ciclos o semestres (ej. "Otoño 2025").
    * `Alumnos`: Información demográfica y académica de los estudiantes.
    * `Grupos`: La oferta de una materia en un periodo específico.
    * `Alumno_Grupo`: La inscripción de un alumno a un grupo (calificaciones).

### 2. Módulo: Desarrollo Académico
* **Base de Datos:** `desarrolloacademico.sql`
* **Propósito:** Gestiona el perfil y las actividades del personal docente, enfocándose en su desempeño académico y su interacción con los alumnos fuera del aula.
* **Entidades Clave:**
    * `DEPARTAMENTO`: Divisiones académicas (ej. "Sistemas y Computación").
    * `DOCENTE`: Perfil profesional del personal docente.
    * `ASIGNATURAS`: Catálogo de asignaturas (vinculadas a `DEPARTAMENTO`).
    * `CVS_DOCENTE`: Perfil curricular extendido (relación 1:1 con `DOCENTE`).
    * `ACTIVIDADES_TUTORIAS`: Registro de sesiones de tutoría impartidas.
    * `EVALUACION_DOCENTE`: Registro de las evaluaciones de desempeño docente.

### 3. Módulo: Recursos Humanos
* **Base de Datos:** `recursoshumanos.sql`
* **Propósito:** Administra el ciclo de vida de *todo* el personal de la institución (docentes, administrativos, etc.). Es responsable de la contratación, la estructura organizacional y el pago.
* **Entidades Clave:**
    * `Empleados`: Tabla maestra de todo el personal.
    * `Puestos`: Catálogo de todos los roles en la institución.
    * `EmpleadoPuesto`: Asignación de un empleado a un puesto (relación M:N).
    * `Asistencia`: Registros de entradas y salidas.
    * `Nomina`: Registros de pago, deducciones y bonos.

### 4. Módulo: Investigación
* **Base de Datos:** `investigacion.sql`
* **Propósito:** Realiza un seguimiento de la producción científica y los proyectos de desarrollo tecnológico de la institución.
* **Entidades Clave:**
    * `INVESTIGADORES`: Perfil del personal dedicado a la investigación.
    * `PROYECTO`: Ficha técnica de un proyecto de investigación.
    * `PUBLICACIONES`: Catálogo de artículos, libros y ponencias generadas.
    * `PROYECTO_COLABORADOR`: Vincula investigadores a un proyecto (relación M:N).
    * `INVESTIGADOR_PUBLICACION`: Vincula investigadores a una publicación (gestión de autoría, M:N).

---

## Consideraciones Generales del Diseño

* **Normalización:** Todos los módulos aplican principios de normalización (mayoritariamente 3NF), utilizando tablas de unión para resolver relaciones de muchos-a-muchos y evitando la redundancia de datos.
* **Integridad de Datos:** Se hace un uso extensivo de `FOREIGN KEY` constraints para la integridad referencial. Además, se emplean `UNIQUE` constraints para llaves de negocio (ej. `num_control`, `clave_materia`) y `CHECK` constraints para validar reglas de negocio (ej. rangos de calificaciones, fechas válidas).
* **Claves Primarias:** Se estandariza el uso de `INT IDENTITY(1,1)` como llaves primarias (surrogate keys) en la mayoría de las tablas, lo cual simplifica la inserción de datos y mejora el rendimiento de los `JOINs`.
* **Tipos de Datos:** Se emplean tipos de datos modernos y apropiados (`VARCHAR(MAX)` para texto largo, `DATE`/`TIME` para fechas, `DECIMAL` para moneda, y `BIT` para valores booleanos).

## Visión a Futuro y Sincronización

El principal desafío de esta arquitectura modular es mantener la consistencia de los datos maestros que se comparten entre dominios.

1.  **Sincronización de Identidad (Persona):**
    * Existe una superposición conceptual entre `Alumnos`, `Empleados`, `DOCENTE`, e `INVESTIGADORES`.
    * **Mejora Recomendada:** Implementar un **Servicio de Identidad** centralizado o designar a `RecursosHumanos.Empleados` como la tabla maestra para todo el personal. Cuando un nuevo `Empleado` es creado en RRHH con el rol 'Docente', la capa de aplicación debería disparar la creación del registro correspondiente en `DesarrolloAcademico.DOCENTE` y `Investigacion.INVESTIGADORES`, utilizando el `IDEmpleado` como llave compartida.

2.  **Sincronización de Catálogos Centrales:**
    * Ciertos catálogos son de naturaleza global. Por ejemplo, `DEPARTAMENTO` existe en `DesarrolloAcademico` pero también es necesario en `RecursosHumanos` (para `Puestos`) y `ServiciosEscolares` (para `Carrera`).
    * `Periodo_Escolar` se define en `ServiciosEscolares`, pero `DesarrolloAcademico` lo necesita para las `EVALUACION_DOCENTE`.
    * **Mejora Recomendada:** Centralizar estos catálogos en un módulo "Maestro" o "General" del cual dependan los demás, o definir un módulo "dueño" de cada catálogo (ej. `ServiciosEscolares` es dueño de `Periodo_Escolar`) y consumir esa información vía servicios en los otros módulos.
