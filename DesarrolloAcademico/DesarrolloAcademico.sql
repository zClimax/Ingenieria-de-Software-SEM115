CREATE TABLE [DEPARTAMENTO] (
  [ID_Departamento] integer PRIMARY KEY,
  [Nombre_Departamento] nvarchar(255)
)
GO

CREATE TABLE [ASIGNATURAS] (
  [ID_Asignaturas] integer PRIMARY KEY,
  [Nombre_Asignatura] nvarchar(255),
  [Creditos_Asignatura] integer,
  [Descripcion_Asignatura] text,
  [ID_Departamento_A] integer
)
GO

CREATE TABLE [DOCENTE] (
  [ID_Docente] integer PRIMARY KEY,
  [Nombre_Docente] nvarchar(255),
  [AP_Docente] nvarchar(255),
  [AM_Docente] nvarchar(255),
  [Correo_Electronico_Docente] nvarchar(255),
  [Fecha_Contratacion] date,
  [Especialidad] nvarchar(255),
  [Estatus] nvarchar(255),
  [ID_Departamento_D] integer
)
GO

CREATE TABLE [CVS_DOCENTE] (
  [ID_CV] integer PRIMARY KEY,
  [Fecha_Actualizacion_CVS] date,
  [Grado_Estudios] nvarchar(255),
  [Experiencia_Profesional] text,
  [Proyecto_Investigacion] text,
  [ID_Docente_CVS] integer
)
GO

CREATE TABLE [EVALUACION_DOCENTE] (
  [ID_Evaluacion] integer PRIMARY KEY,
  [Fecha_Evaluacion] date,
  [Calificacion_Global] decimal,
  [ID_Docente_E] integer,
  [ID_Estudiante_E] integer
)
GO

CREATE TABLE [ACTIVIDADES_TUTORIAS] (
  [ID_Evaluacion] integer PRIMARY KEY,
  [Fecha_Tut] date,
  [Hora_Inicio_Tut] time,
  [Hora_Final_Tut] time,
  [Tipo_Actividad] nvarchar(255),
  [Lugar] nvarchar(255),
  [Duracion] integer,
  [Resultado] nvarchar(255),
  [Observaciones] text,
  [Num_Tutorados] integer,
  [ID_Docente_Tut] integer
)
GO

ALTER TABLE [ASIGNATURAS] ADD FOREIGN KEY ([ID_Departamento_A]) REFERENCES [DEPARTAMENTO] ([ID_Departamento])
GO

ALTER TABLE [DOCENTE] ADD FOREIGN KEY ([ID_Departamento_D]) REFERENCES [DEPARTAMENTO] ([ID_Departamento])
GO

ALTER TABLE [CVS_DOCENTE] ADD FOREIGN KEY ([ID_Docente_CVS]) REFERENCES [DOCENTE] ([ID_Docente])
GO

ALTER TABLE [EVALUACION_DOCENTE] ADD FOREIGN KEY ([ID_Docente_E]) REFERENCES [DOCENTE] ([ID_Docente])
GO

ALTER TABLE [ACTIVIDADES_TUTORIAS] ADD FOREIGN KEY ([ID_Docente_Tut]) REFERENCES [DOCENTE] ([ID_Docente])
GO
