-- Crear la base de datos
CREATE DATABASE recursoshumanos;
GO

-- Usar la base de datos
USE recursoshumanos;
GO

CREATE TABLE Empleados (
  IDEmpleadoPK INT PRIMARY KEY,
  Nombre VARCHAR(50) NOT NULL,
  Apaterno VARCHAR(50) NOT NULL,
  Amaterno VARCHAR(50),
  fechanac DATE,
  telefono VARCHAR(15),
  correoelectronico VARCHAR(100),
  fechacontratacion DATE NOT NULL,
  ciudad VARCHAR(60),
  colonia VARCHAR(80),
  numcasa VARCHAR(10),
  calle VARCHAR(100),
  codigopostal CHAR(5),
  estadocivil VARCHAR(20),
  RFCEmp CHAR(13),
  NSSEmp CHAR(11)
);
GO

CREATE TABLE Puestos (
  IDpuestoPK INT PRIMARY KEY,
  NombrePuesto VARCHAR(80) NOT NULL,
  IDDepartamentoFK INT,
  Jefeinmediato VARCHAR(100),
  SueldoBase DECIMAL(10,2) NOT NULL,
  TipoCompensacion VARCHAR(30),
  Horario VARCHAR(50),
  Tipocontrato VARCHAR(30),
  Estado VARCHAR(20)
);
GO

CREATE TABLE EmpleadoPuesto (
  IDAsignacionPK INT PRIMARY KEY,
  IDEmpleadoFK INT NOT NULL,
  IDPuestosFK INT NOT NULL
);
GO

CREATE TABLE Asistencia (
  IdasistenciaPK INT PRIMARY KEY,
  HoraEntrada TIME(0),
  Fecha DATE NOT NULL,
  TipoAsistencia VARCHAR(30),
  HoraSalida TIME(0),
  Diasferiados VARCHAR(100),
  Estado BIT NOT NULL,
  IDAsignacionFK INT NOT NULL
);
GO

CREATE TABLE Nomina (
  IDNominaPK INT PRIMARY KEY,
  IDAsignacionFK INT NOT NULL,
  fechapago DATE NOT NULL,
  Periodoinicio DATE NOT NULL,
  Periodofin DATE NOT NULL,
  SalarioBase DECIMAL(10,2) NOT NULL,
  Bonos DECIMAL(10,2),
  Totaldeducciones DECIMAL(10,2),
  MetodoPago VARCHAR(30),
  SueldoporHora DECIMAL(8,2),
  TipoContrato VARCHAR(30)
);
GO

ALTER TABLE EmpleadoPuesto ADD FOREIGN KEY (IDEmpleadoFK) REFERENCES Empleados (IDEmpleadoPK);
GO

ALTER TABLE EmpleadoPuesto ADD FOREIGN KEY (IDPuestosFK) REFERENCES Puestos (IDpuestoPK);
GO

ALTER TABLE Asistencia ADD FOREIGN KEY (IDAsignacionFK) REFERENCES EmpleadoPuesto (IDAsignacionPK);
GO

ALTER TABLE Nomina ADD FOREIGN KEY (IDAsignacionFK) REFERENCES EmpleadoPuesto (IDAsignacionPK);
GO
