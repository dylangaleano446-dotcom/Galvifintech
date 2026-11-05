-- =====================================================================
--  GalviFintech Portal - Script de creacion de base de datos
--  Motor: MySQL 8 / MariaDB 10.4+ (XAMPP)   Charset: utf8mb4
--  Autor: Dylan Galeano - 7° Año E.E.S.T. N°4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS galvifintech
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE galvifintech;

-- ---------------------------------------------------------------------
-- Tabla: usuarios  (clientes PyME que operan la plataforma)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(100)     NOT NULL,
  email             VARCHAR(150)     NOT NULL,
  password_hash     VARCHAR(255)     NOT NULL,
  empresa           VARCHAR(150)     NOT NULL,
  cuit              CHAR(11)         NULL,
  rol               ENUM('admin','operador') NOT NULL DEFAULT 'admin',
  mfa_habilitado    TINYINT(1)       NOT NULL DEFAULT 0,
  mfa_secreto       VARCHAR(64)      NULL,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME         NULL,
  creado_en         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuarios PRIMARY KEY (id),
  CONSTRAINT uq_usuarios_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: transacciones  (ingresos y gastos de cada usuario)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transacciones (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED  NOT NULL,
  tipo         ENUM('ingreso','gasto') NOT NULL,
  categoria    VARCHAR(50)   NOT NULL,
  descripcion  VARCHAR(255)  NOT NULL,
  monto        DECIMAL(12,2) NOT NULL,
  fecha        DATE          NOT NULL,
  medio_pago   ENUM('efectivo','transferencia','debito','credito','otro')
                             NOT NULL DEFAULT 'efectivo',
  comprobante  VARCHAR(50)   NULL,
  creado_en    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_transacciones PRIMARY KEY (id),
  CONSTRAINT fk_transacciones_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_transacciones_monto CHECK (monto > 0),
  INDEX idx_trx_usuario_fecha (usuario_id, fecha),
  INDEX idx_trx_usuario_categoria (usuario_id, categoria)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: presupuestos  (limite mensual de gasto por categoria)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS presupuestos (
  id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  usuario_id    INT UNSIGNED      NOT NULL,
  categoria     VARCHAR(50)       NOT NULL,
  monto_limite  DECIMAL(12,2)     NOT NULL,
  anio          SMALLINT UNSIGNED NOT NULL,
  mes           TINYINT UNSIGNED  NOT NULL,
  creado_en     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_presupuestos PRIMARY KEY (id),
  CONSTRAINT fk_presupuestos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT uq_presupuesto_periodo UNIQUE (usuario_id, categoria, anio, mes),
  CONSTRAINT chk_presupuestos_mes CHECK (mes BETWEEN 1 AND 12),
  CONSTRAINT chk_presupuestos_monto CHECK (monto_limite > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Datos de demostracion (password de la cuenta demo: Demo1234!)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nombre, email, password_hash, empresa, cuit) VALUES
('Cliente Demo', 'demo@galvifintech.com',
 '$2y$10$LGx.O9NRdIlSxtIaj79vpOOxGAzqPUn9lkiCIpR.OlyL.Kw5a7Rna',
 'Ferreteria El Tornillo SRL', '30712345678');

INSERT INTO transacciones (usuario_id, tipo, categoria, descripcion, monto, fecha, medio_pago) VALUES
(1,'ingreso','Ventas','Ventas mostrador semana 1', 850000.00,'2026-10-07','efectivo'),
(1,'ingreso','Ventas','Ventas mostrador semana 2', 920000.00,'2026-10-14','transferencia'),
(1,'ingreso','Ventas','Ventas mostrador semana 3', 780000.00,'2026-10-21','debito'),
(1,'gasto','Proveedores','Compra de mercaderia',    1100000.00,'2026-10-03','transferencia'),
(1,'gasto','Sueldos','Sueldo empleado',              520000.00,'2026-10-05','transferencia'),
(1,'gasto','Alquiler','Alquiler del local',          350000.00,'2026-10-01','transferencia'),
(1,'gasto','Servicios','Luz y gas',                   95000.00,'2026-10-10','debito'),
(1,'gasto','Servicios','Internet',                    28000.00,'2026-10-12','debito'),
(1,'gasto','Impuestos','Monotributo / IIBB',         140000.00,'2026-10-20','transferencia'),
(1,'ingreso','Ventas','Ventas septiembre',          2300000.00,'2026-09-30','transferencia'),
(1,'gasto','Proveedores','Mercaderia septiembre',   1200000.00,'2026-09-15','transferencia'),
(1,'gasto','Sueldos','Sueldo septiembre',            500000.00,'2026-09-05','transferencia');

INSERT INTO presupuestos (usuario_id, categoria, monto_limite, anio, mes) VALUES
(1,'Proveedores',1000000.00,2026,10),
(1,'Sueldos',     550000.00,2026,10),
(1,'Servicios',   100000.00,2026,10),
(1,'Impuestos',   150000.00,2026,10);
