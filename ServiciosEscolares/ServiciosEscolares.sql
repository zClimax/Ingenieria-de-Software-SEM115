-- Crear la base de datos
CREATE DATABASE serviciosescolares;
GO

-- Usar la base de datos
USE serviciosescolares;
GO

CREATE TABLE Alumnos (
  id_alumnos INT PRIMARY KEY IDENTITY(1,1),
  id_carrera INT NOT NULL,
  num_control CHAR(8) UNIQUE NOT NULL,
  nombre VARCHAR(50) NOT NULL,
  apellido_paterno VARCHAR(50) NOT NULL,
  apellido_materno VARCHAR(50),
  correo VARCHAR(100),
  telefono VARCHAR(15),
  calle VARCHAR(100),
  numero VARCHAR(10),
  colonia VARCHAR(80),
  ciudad VARCHAR(60),
  cp CHAR(5)
);
GO

CREATE TABLE Carrera (
  id_carrera INT PRIMARY KEY IDENTITY(1,1),
  clave_carrera VARCHAR(20) UNIQUE NOT NULL,
  nombre_carrera VARCHAR(100) NOT NULL,
  descripcion VARCHAR(MAX),
  nivel_academico VARCHAR(30),
  duracion_semestres TINYINT NOT NULL,
  activa BIT NOT NULL DEFAULT 1
);
GO

CREATE TABLE Plan_Estudios (
  id_plan_estudios INT PRIMARY KEY IDENTITY(1,1),
  id_carrera INT NOT NULL,
  clave_plan VARCHAR(20) UNIQUE NOT NULL,
  nombre_plan VARCHAR(100) NOT NULL,
  año_vigencia SMALLINT NOT NULL,
  total_creditos SMALLINT NOT NULL,
  activo BIT NOT NULL DEFAULT 1
);
GO

CREATE TABLE Materia (
  id_materia INT PRIMARY KEY IDENTITY(1,1),
  clave_materia VARCHAR(15) UNIQUE NOT NULL,
  nombre_materia VARCHAR(100) NOT NULL,
  creditos TINYINT NOT NULL,
  horas_teoria TINYINT,
  horas_practica TINYINT,
  descripcion_materia VARCHAR(MAX)
);
GO

CREATE TABLE Materia_Plan (
  id_materia_plan INT PRIMARY KEY IDENTITY(1,1),
  id_plan_estudios INT NOT NULL,
  id_materia INT NOT NULL,
  semestre_recomendado TINYINT,
  tipo_materia VARCHAR(20)
);
GO

CREATE TABLE Periodo_Escolar (
  id_periodo_escolar INT PRIMARY KEY IDENTITY(1,1),
  nombre_periodo VARCHAR(50) NOT NULL,
  numero_periodo SMALLINT NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  periodo_vacacional VARCHAR(100),
  estatus VARCHAR(20) NOT NULL
);
GO

CREATE TABLE Grupos (
  id_grupo INT PRIMARY KEY IDENTITY(1,1),
  clave_grupo VARCHAR(20) UNIQUE NOT NULL,
  id_materia INT NOT NULL,
  id_periodo_escolar INT NOT NULL,
  cantidad_maxima TINYINT NOT NULL,
  cantidad_inscritos TINYINT DEFAULT 0,
  modalidad VARCHAR(30),
  aula VARCHAR(20),
  docente VARCHAR(100)
);
GO

CREATE TABLE Alumno_Grupo (
  id_inscripcion INT PRIMARY KEY IDENTITY(1,1),
  id_grupo INT NOT NULL,
  id_alumno INT NOT NULL,
  calificacion DECIMAL(4,2),
  estatus_inscripcion VARCHAR(30) NOT NULL,
  fecha_inscripcion DATE NOT NULL
);
GO

ALTER TABLE Alumnos ADD FOREIGN KEY (id_carrera) REFERENCES Carrera (id_carrera);
GO

ALTER TABLE Plan_Estudios ADD FOREIGN KEY (id_carrera) REFERENCES Carrera (id_carrera);
GO

ALTER TABLE Materia_Plan ADD FOREIGN KEY (id_plan_estudios) REFERENCES Plan_Estudios (id_plan_estudios);
GO

ALTER TABLE Materia_Plan ADD FOREIGN KEY (id_materia) REFERENCES Materia (id_materia);
GO

ALTER TABLE Grupos ADD FOREIGN KEY (id_materia) REFERENCES Materia (id_materia);
GO

ALTER TABLE Grupos ADD FOREIGN KEY (id_periodo_escolar) REFERENCES Periodo_Escolar (id_periodo_escolar);
GO

ALTER TABLE Alumno_Grupo ADD FOREIGN KEY (id_grupo) REFERENCES Grupos (id_grupo);
GO

ALTER TABLE Alumno_Grupo ADD FOREIGN KEY (id_alumno) REFERENCES Alumnos (id_alumnos);
GO
