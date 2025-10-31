# Documentación de la Base de Datos: Desarrollo Académico

## Descripción general del proyecto

Esta base de datos está diseñada para gestionar la información relacionada con el desarrollo académico y la administración del personal docente dentro de una institución educativa.

El modelo se centra en la entidad **Docente** y rastrea sus afiliaciones departamentales, las asignaturas relacionadas con dichos departamentos, y las actividades de apoyo clave del docente, como su currículum (CV), sus evaluaciones de desempeño y las actividades de tutoría que imparte.

## Estructura del modelo

El modelo de datos se compone de 6 tablas principales:

* **DEPARTAMENTO**: Tabla maestra que cataloga las divisiones académicas o administrativas de la institución.
* **ASIGNATURAS**: Catálogo de todas las materias o cursos que ofrece la institución, vinculadas a un departamento.
* **DOCENTE**: Tabla central que contiene la información personal y profesional de cada miembro del personal docente.
* **CVS_DOCENTE**: Tabla de detalle que almacena la información curricular extendida de un docente (formación, experiencia, etc.).
* **EVALUACION_DOCENTE**: Registro transaccional de las evaluaciones de desempeño realizadas a los docentes.
* **ACTIVIDADES_TUTORIAS**: Registro transaccional de las sesiones de tutoría y actividades de apoyo que un docente realiza.

## Relaciones entre tablas

El diseño sigue un modelo relacional centrado en las tablas `DEPARTAMENTO` y `DOCENTE`, estableciendo las siguientes relaciones:

* **Uno a Muchos (1:N)**
    * `DEPARTAMENTO` a `DOCENTE`: Un departamento puede tener múltiples docentes asignados.
        * *Llave:* `DOCENTE.ID_Departamento_D` -> `DEPARTAMENTO.ID_Departamento`
    * `DEPARTAMENTO` a `ASIGNATURAS`: Un departamento imparte múltiples asignaturas.
        * *Llave:* `ASIGNATURAS.ID_Departamento_A` -> `DEPARTAMENTO.ID_Departamento`
    * `DOCENTE` a `EVALUACION_DOCENTE`: Un docente puede recibir múltiples evaluaciones a lo largo del tiempo.
        * *Llave:* `EVALUACION_DOCENTE.ID_Docente_E` -> `DOCENTE.ID_Docente`
    * `DOCENTE` a `ACTIVIDADES_TUTORIAS`: Un docente puede registrar múltiples actividades de tutoría.
        * *Llave:* `ACTIVIDADES_TUTORIAS.ID_Docente_Tut` -> `DOCENTE.ID_Docente`

* **Uno a Uno (1:1)**
    * `DOCENTE` a `CVS_DOCENTE`: Se espera que cada docente tenga un único registro de CV asociado.
        * *Llave:* `CVS_DOCENTE.ID_Docente_CVS` -> `DOCENTE.ID_Docente`
        * *(Nota: Para forzar esta relación a nivel de BD, la columna `ID_Docente_CVS` debería tener una restricción `UNIQUE`)*.

## Descripción de cada tabla

A continuación, se detalla la estructura de cada tabla basada en el script SQL proporcionado.

### 1. DEPARTAMENTO

Almacena los departamentos académicos de la institución.

| Atributo | Tipo de Dato | Descripción |
| :--- | :--- | :--- |
| **ID_Departamento** | **integer** | **Llave Primaria (PK).** Identificador único del departamento. |
| Nombre_Departamento | nvarchar(255) | Nombre oficial del departamento. |

### 2. ASIGNATURAS

Catálogo de materias, cursos o asignaturas.

| Atributo | Tipo de Dato | Descripción |
| :--- | :--- | :--- |
| **ID_Asignaturas** | **integer** | **Llave Primaria (PK).** Identificador único de la asignatura. |
| Nombre_Asignatura | nvarchar(255) | Nombre oficial de la asignatura. |
| Creditos_Asignatura | integer | Número de créditos que otorga la asignatura. |
| Descripcion_Asignatura | text | Descripción o temario de la asignatura. |
| **ID_Departamento_A** | **integer** | **Llave Foránea (FK).** Referencia a `DEPARTAMENTO(ID_Departamento)`. |

### 3. DOCENTE

Información central del personal docente.

| Atributo | Tipo de Dato | Descripción |
| :--- | :--- | :--- |
| **ID_Docente** | **integer** | **Llave Primaria (PK).** Identificador único del docente. |
| Nombre_Docente | nvarchar(255) | Nombre(s) del docente. |
| AP_Docente | nvarchar(255) | Apellido paterno del docente. |
| AM_Docente | nvarchar(255) | Apellido materno del docente. |
| Correo_Electronico_Docente | nvarchar(255) | Correo institucional o de contacto. |
| Fecha_Contratacion | date | Fecha en que el docente se unió a la institución. |
| Especialidad | nvarchar(255) | Área principal de experiencia o estudio del docente. |
| Estatus | nvarchar(255) | Situación contractual (ej. 'Activo', 'Inactivo', 'Licencia'). |
| **ID_Departamento_D** | **integer** | **Llave Foránea (FK).** Referencia a `DEPARTAMENTO(ID_Departamento)`. |

### 4. CVS_DOCENTE

Detalles curriculares del docente.

| Atributo | Tipo de Dato | Descripción |
| :--- | :--- | :--- |
| **ID_CV** | **integer** | **Llave Primaria (PK).** Identificador único del registro de CV. |
| Fecha_Actualizacion_CVS | date | Fecha de la última modificación de este registro. |
| Grado_Estudios | nvarchar(255) | Máximo grado académico (ej. 'Licenciatura', 'Maestría', 'Doctorado'). |
| Experiencia_Profesional | text | Descripción de la experiencia laboral relevante. |
| Proyecto_Investigacion | text | Descripción de proyectos de investigación en los que ha participado. |
| **ID_Docente_CVS** | **integer** | **Llave Foránea (FK).** Referencia a `DOCENTE(ID_Docente)`. |

### 5. EVALUACION_DOCENTE

Registro de las evaluaciones de desempeño docente.

| Atributo | Tipo de Dato | Descripción |
| :--- | :--- | :--- |
| **ID_Evaluacion** | **integer** | **Llave Primaria (PK).** Identificador único de la evaluación. |
| Fecha_Evaluacion | date | Fecha en que se realizó la evaluación. |
| Calificacion_Global | decimal | Puntuación numérica obtenida en la evaluación. |
| **ID_Docente_E** | **integer** | **Llave Foránea (FK).** Referencia a `DOCENTE(ID_Docente)`. |
| ID_Estudiante_E | integer | Identificador del estudiante que evalúa. *(Nota: La tabla `ESTUDIANTE` no existe en el script)*. |

### 6. ACTIVIDADES_TUTORIAS

Registro de las actividades de tutoría.

| Atributo | Tipo de Dato | Descripción |
| :--- | :--- | :--- |
| **ID_Evaluacion** | **integer** | **Llave Primaria (PK).** Identificador único. *(Nota: Nombre de PK incorrecto en el script)*. |
| Fecha_Tut | date | Fecha de la tutoría. |
| Hora_Inicio_Tut | time | Hora de inicio de la actividad. |
| Hora_Final_Tut | time | Hora de finalización de la actividad. |
| Tipo_Actividad | nvarchar(255) | Categoría de la tutoría (ej. 'Académica', 'Personal', 'Grupal'). |
| Lugar | nvarchar(255) | Ubicación donde se realizó la tutoría. |
| Duracion | integer | Duración total en minutos (calculable, pero almacenada). |
| Resultado | nvarchar(255) | Breve descripción del resultado o seguimiento. |
| Observaciones | text | Notas adicionales sobre la sesión. |
| Num_Tutorados | integer | Cantidad de estudiantes atendidos en la sesión. |
| **ID_Docente_Tut** | **integer** | **Llave Foránea (FK).** Referencia a `DOCENTE(ID_Docente)`. |

## Diagrama lógico

![Diagrama Entidad-Relación de Desarrollo Académico](DesarrolloAcademico.png)

El diagrama lógico, visible en el archivo `DesarrolloAcademico.png`, muestra un modelo centrado en dos entidades principales: `DEPARTAMENTO` y `DOCENTE`.

* `DEPARTAMENTO` actúa como una tabla de dimensión que agrupa tanto a `DOCENTES` como a `ASIGNATURAS`.
* `DOCENTE` es la entidad central de la cual se desprenden múltiples tablas de detalle que registran eventos o atributos extendidos de un docente:
    1.  `CVS_DOCENTE` (Relación 1:1)
    2.  `EVALUACION_DOCENTE` (Relación 1:N)
    3.  `ACTIVIDADES_TUTORIAS` (Relación 1:N)

Este flujo de datos permite a la institución mantener un registro centralizado de su personal docente y, al mismo tiempo, rastrear todas las actividades y métricas de desarrollo académico asociadas a ellos.

## Consideraciones del diseño

1.  **Normalización:** El modelo aplica principios de normalización. Por ejemplo, la información del CV (`CVS_DOCENTE`) se separa de la información principal del `DOCENTE` para evitar una tabla excesivamente ancha (muchas columnas) y permitir que los docentes nuevos existan sin un CV registrado (evitando valores nulos). Del mismo modo, `DEPARTAMENTO` se normaliza para evitar la redundancia de nombres de departamento en las tablas `DOCENTE` y `ASIGNATURAS`.
2.  **Integridad Referencial:** El uso de llaves foráneas (`FOREIGN KEY`) es crucial y está correctamente implementado para garantizar que no se puedan crear registros huérfanos. Un `DOCENTE` debe pertenecer a un `DEPARTAMENTO` existente, y una `EVALUACION` debe estar vinculada a un `DOCENTE` existente.
3.  **Tipos de Datos:** La elección de `nvarchar` es adecuada para campos de texto (como nombres), ya que soporta caracteres internacionales (Unicode). El uso de `text` para descripciones largas es funcional, aunque en versiones modernas de SQL Server se prefiere `nvarchar(max)`. Los tipos `date` y `time` son apropiados para el registro de eventos.

## Posibles mejoras

Si bien el modelo es funcional, se identifican varias áreas de mejora críticas y de optimización:

1.  **Corregir PK en `ACTIVIDADES_TUTORIAS`:** El script SQL define la llave primaria de `ACTIVIDADES_TUTORIAS` como `ID_Evaluacion`. Esto es un error semántico y probablemente un error de copiado de la tabla `EVALUACION_DOCENTE`. La llave primaria debería llamarse `ID_Actividad_Tutoria` (como sugiere el diagrama ERD) para representar unívocamente cada actividad.
2.  **Resolver Llave Foránea Huérfana:** La tabla `EVALUACION_DOCENTE` incluye una columna `ID_Estudiante_E`. Sin embargo, el script no proporciona una tabla `ESTUDIANTE`. Esto crea una "llave foránea huérfana" (un ID que no referencia a nada). Se debe crear la tabla `ESTUDIANTE` y establecer la relación formal, o eliminar la columna `ID_Estudiante_E` si las evaluaciones no son realizadas por estudiantes.
3.  **Forzar Cardinalidad 1:1:** Para asegurar que un docente solo pueda tener un CV, se debe agregar una restricción `UNIQUE` a la columna `ID_Docente_CVS` en la tabla `CVS_DOCENTE`.
4.  **Consistencia entre SQL y ERD:** Existen discrepancias entre el script SQL y el diagrama ERD proporcionado. Por ejemplo, el ERD muestra una columna `Comentarios` en `EVALUACION_DOCENTE` que no está en el SQL. El SQL y el diagrama deben sincronizarse para reflejar el estado real del modelo.
5.  **Modernizar Tipos de Datos `text`:** El tipo de dato `text` está obsoleto. Se recomienda reemplazar `Descripcion_Asignatura`, `Experiencia_Profesional`, `Proyecto_Investigacion` y `Observaciones` por el tipo `nvarchar(max)`.
6.  **Optimización (Índices):** Para mejorar el rendimiento de las consultas (especialmente `JOINs`), se deben crear índices no agrupados (non-clustered indexes) en todas las columnas de llave foránea (ej. `ID_Departamento_A`, `ID_Departamento_D`, `ID_Docente_E`, `ID_Docente_CVS`, `ID_Docente_Tut`).
7.  **Normalización de Estatus:** La columna `Estatus` en `DOCENTE` (tipo `nvarchar(255)`) es propensa a errores de ingreso de datos (ej. "Activo", "activo", "ACTIVO"). Se recomienda normalizar esto creando una tabla `CATALOGO_ESTATUS` o, como mínimo, aplicar una restricción `CHECK` (ej. `CHECK (Estatus IN ('Activo', 'Inactivo', 'Licencia'))`).
