-- Módulo de Investigación - BD Independiente
-- SQL Server

CREATE DATABASE INVESTIGACION;
GO
USE INVESTIGACION;
GO

CREATE TABLE INVESTIGADORES (
  ID_Investigador integer PRIMARY KEY IDENTITY(1, 1),
  nombre varchar(50) NOT NULL,
  apellido_paterno varchar(50) NOT NULL,
  apellido_materno varchar(50),
  Genero char(1),
  telefono varchar(15),
  Especialidad varchar(100),
  Proyectos_Activos tinyint DEFAULT 0,
  Nacionalidad varchar(50),
  Fecha_Creacion datetime2 DEFAULT GETDATE()
);

CREATE TABLE PROYECTO (
  ID_Proyecto integer PRIMARY KEY IDENTITY(1, 1),
  Objetivo_General varchar(500) NOT NULL,
  Nombre_Proyecto varchar(150) NOT NULL,
  Descripcion varchar(max),
  Fecha_Inicio date NOT NULL,
  Fecha_Finalizacion date,
  Estado varchar(30) NOT NULL DEFAULT 'En Proceso',
  Colaboradores varchar(max),
  Fecha_Creacion datetime2 DEFAULT GETDATE()
);

CREATE TABLE EVIDENCIA_PROYECTO (
  ID_Evidencia integer PRIMARY KEY IDENTITY(1, 1),
  ID_Proyecto integer NOT NULL,
  Tipo_Evidencia varchar(50) NOT NULL,
  Ruta_Archivo varchar(255),
  Nombre_Archivo varchar(100) NOT NULL,
  Fecha_Carga date DEFAULT GETDATE(),
  Descripcion varchar(max)
);

CREATE TABLE PUBLICACIONES (
  ID_Publicacion integer PRIMARY KEY IDENTITY(1, 1),
  Titulo varchar(200) NOT NULL,
  Tipo_Publicacion varchar(50) NOT NULL,
  Fecha_Publicacion date NOT NULL,
  Resumen varchar(max),
  Idioma varchar(30),
  DOI varchar(100),
  Editorial varchar(100),
  Fecha_Creacion datetime2 DEFAULT GETDATE()
);

CREATE TABLE INVESTIGADOR_PUBLICACION (
  ID_Investigador_Publicacion integer PRIMARY KEY IDENTITY(1, 1),
  ID_Investigador integer NOT NULL,
  ID_Publicacion integer NOT NULL,
  Rol_Autor varchar(30) DEFAULT 'Coautor',
  Orden_Autoria tinyint
);

CREATE TABLE PROYECTO_COLABORADOR (
  ID_Proyecto_Colaborador integer PRIMARY KEY IDENTITY(1, 1),
  ID_Proyecto integer NOT NULL,
  ID_Investigador integer NOT NULL,
  Rol varchar(50),
  Fecha_Inicio_Colaboracion date NOT NULL,
  Fecha_Fin_Colaboracion date
);



-- Relaciones (Foreign Keys)
ALTER TABLE EVIDENCIA_PROYECTO 
  ADD FOREIGN KEY (ID_Proyecto) REFERENCES PROYECTO(ID_Proyecto);

ALTER TABLE INVESTIGADOR_PUBLICACION 
  ADD FOREIGN KEY (ID_Investigador) REFERENCES INVESTIGADORES(ID_Investigador);

ALTER TABLE INVESTIGADOR_PUBLICACION 
  ADD FOREIGN KEY (ID_Publicacion) REFERENCES PUBLICACIONES(ID_Publicacion);

ALTER TABLE PROYECTO_COLABORADOR 
  ADD FOREIGN KEY (ID_Proyecto) REFERENCES PROYECTO(ID_Proyecto);

ALTER TABLE PROYECTO_COLABORADOR 
  ADD FOREIGN KEY (ID_Investigador) REFERENCES INVESTIGADORES(ID_Investigador);

