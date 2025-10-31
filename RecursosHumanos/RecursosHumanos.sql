-- Crear la base de datos
CREATE DATABASE desarrolloacademico;
GO

-- Usar la base de datos
USE desarrolloacademico;
GO

CREATE TABLE DEPARTAMENTO (
  ID_Departamento INT PRIMARY KEY IDENTITY(1,1),
  Nombre_Departamento VARCHAR(80) NOT NULL
);
GO

CREATE TABLE ASIGNATURAS (
  ID_Asignaturas INT PRIMARY KEY IDENTITY(1,1),
  Nombre_Asignatura VARCHAR(100) NOT NULL,
  Creditos_Asignatura TINYINT NOT NULL,
  Descripcion_Asignatura VARCHAR(MAX),
  ID_Departamento_A INT NOT NULL,
  CONSTRAINT CK_Creditos_Positivos CHECK (Creditos_Asignatura > 0)
);
GO

CREATE TABLE DOCENTE (
  ID_Docente INT PRIMARY KEY IDENTITY(1,1),
  Nombre_Docente VARCHAR(50) NOT NULL,
  AP_Docente VARCHAR(50) NOT NULL,
  AM_Docente VARCHAR(50),
  Correo_Electronico_Docente VARCHAR(100),
  Fecha_Contratacion DATE NOT NULL,
  Especialidad VARCHAR(100),
  Estatus VARCHAR(20) NOT NULL DEFAULT 'Activo',
  ID_Departamento_D INT NOT NULL,
  CONSTRAINT CK_Estatus_Valido CHECK (Estatus IN ('Activo', 'Inactivo', 'Jubilado'))
);
GO

CREATE TABLE CVS_DOCENTE (
  ID_CV INT PRIMARY KEY IDENTITY(1,1),
  Fecha_Actualizacion_CVS DATE NOT NULL DEFAULT GETDATE(),
  Grado_Estudios VARCHAR(50) NOT NULL,
  Experiencia_Profesional VARCHAR(MAX),
  Proyecto_Investigacion VARCHAR(MAX),
  ID_Docente_CVS INT NOT NULL UNIQUE
);
GO

CREATE TABLE EVALUACION_DOCENTE (
  ID_Evaluacion INT PRIMARY KEY IDENTITY(1,1),
  Fecha_Evaluacion DATE NOT NULL DEFAULT GETDATE(),
  Calificacion_Global DECIMAL(4,2) NOT NULL,
  Comentarios VARCHAR(MAX),
  ID_Docente_E INT NOT NULL,
  ID_Estudiante_E INT NOT NULL,
  CONSTRAINT CK_Calificacion_Rango CHECK (Calificacion_Global BETWEEN 0 AND 10),
  CONSTRAINT UQ_Evaluacion_Docente_Alumno UNIQUE (ID_Docente_E, ID_Estudiante_E, Fecha_Evaluacion)
);
GO

CREATE TABLE ACTIVIDADES_TUTORIAS (
  ID_Actividad_Tutoria INT PRIMARY KEY IDENTITY(1,1),
  Fecha_Tut DATE NOT NULL,
  Hora_Inicio_Tut TIME(0) NOT NULL,
  Hora_Final_Tut TIME(0) NOT NULL,
  Tipo_Actividad VARCHAR(50) NOT NULL,
  Lugar VARCHAR(80),
  Resultado VARCHAR(MAX),
  Observaciones VARCHAR(MAX),
  Num_Tutorados TINYINT,
  ID_Docente_Tut INT NOT NULL,
  CONSTRAINT CK_Horas_Validas CHECK (Hora_Final_Tut > Hora_Inicio_Tut),
  CONSTRAINT CK_Num_Tutorados_Positivo CHECK (Num_Tutorados > 0)
);
GO


ALTER TABLE ASIGNATURAS ADD CONSTRAINT FK_Asignaturas_Departamento 
  FOREIGN KEY (ID_Departamento_A) REFERENCES DEPARTAMENTO (ID_Departamento);
GO

ALTER TABLE DOCENTE ADD CONSTRAINT FK_Docente_Departamento 
  FOREIGN KEY (ID_Departamento_D) REFERENCES DEPARTAMENTO (ID_Departamento);
GO

ALTER TABLE CVS_DOCENTE ADD CONSTRAINT FK_CVS_Docente 
  FOREIGN KEY (ID_Docente_CVS) REFERENCES DOCENTE (ID_Docente);
GO

ALTER TABLE EVALUACION_DOCENTE ADD CONSTRAINT FK_Evaluacion_Docente 
  FOREIGN KEY (ID_Docente_E) REFERENCES DOCENTE (ID_Docente);
GO

ALTER TABLE ACTIVIDADES_TUTORIAS ADD CONSTRAINT FK_ActividadesTutorias_Docente 
  FOREIGN KEY (ID_Docente_Tut) REFERENCES DOCENTE (ID_Docente);
GO
