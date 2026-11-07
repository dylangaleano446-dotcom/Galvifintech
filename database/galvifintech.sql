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
-- Tabla: usuarios  (clientes PyME y personal de GalviFintech)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(100)     NOT NULL,
  email             VARCHAR(150)     NOT NULL,
  password_hash     VARCHAR(255)     NOT NULL,
  empresa           VARCHAR(150)     NOT NULL,
  cuit              CHAR(11)         NULL,
  rol               ENUM('cliente','staff') NOT NULL DEFAULT 'cliente',
  plan              ENUM('basico','pyme','empresa') NULL,  -- NULL para el personal de GalviFintech
  abono_hasta       DATE             NULL,  -- ultimo dia pagado; al dia siguiente se suspende
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
-- Tabla: pagos  (abonos cobrados; cada pago extiende abono_hasta)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED      NOT NULL,
  plan            ENUM('basico','pyme','empresa') NOT NULL,
  meses           TINYINT UNSIGNED  NOT NULL,
  monto_usd       DECIMAL(10,2)     NOT NULL,
  medio_pago      ENUM('transferencia','mercadopago','efectivo','otro') NOT NULL DEFAULT 'transferencia',
  comprobante     VARCHAR(50)       NULL,
  periodo_desde   DATE              NOT NULL,
  periodo_hasta   DATE              NOT NULL,
  registrado_por  INT UNSIGNED      NULL,
  creado_en       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_pagos PRIMARY KEY (id),
  CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pagos_registrado_por FOREIGN KEY (registrado_por)
    REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_pagos_meses CHECK (meses IN (1, 6, 12)),
  CONSTRAINT chk_pagos_monto CHECK (monto_usd > 0),
  INDEX idx_pagos_usuario (usuario_id, periodo_hasta)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Datos de demostracion (claves: Demo1234!, Moroso1234! y Admin1234!)
-- ---------------------------------------------------------------------
-- Las fechas del abono se calculan desde el dia en que se importa el script,
-- asi la cuenta demo siempre queda activa y la morosa siempre suspendida.
INSERT INTO usuarios (nombre, email, password_hash, empresa, cuit, rol, plan, abono_hasta) VALUES
('Cliente Demo', 'demo@galvifintech.com',
 '$2y$10$LGx.O9NRdIlSxtIaj79vpOOxGAzqPUn9lkiCIpR.OlyL.Kw5a7Rna',
 'Ferreteria El Tornillo SRL', '30712345678', 'cliente', 'pyme', DATE_ADD(CURDATE(), INTERVAL 25 DAY)),
('Cliente Moroso', 'moroso@galvifintech.com',                     -- clave: Moroso1234!
 '$2y$10$WtunlMwDMCfxkoMgIDO7ReVLrbnG8E07fpaPqm5FdCK3YixhUL9yq',
 'Almacen Don Pepe', '20301234567', 'cliente', 'basico', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
('Administrador GalviFintech', 'admin@galvifintech.com',          -- clave: Admin1234!
 '$2y$10$J1jEEFWeUUFDHI8glsgCQud2EEKiZ6DJTLZrPkguvSq8yRRi0pWRC',
 'GalviFintech', NULL, 'staff', NULL, NULL);

INSERT INTO pagos (usuario_id, plan, meses, monto_usd, medio_pago, comprobante, periodo_desde, periodo_hasta, registrado_por) VALUES
(1, 'pyme',   1, 54.45, 'transferencia', 'TRF-0001', DATE_SUB(CURDATE(), INTERVAL 5 DAY),  DATE_ADD(CURDATE(), INTERVAL 25 DAY), 3),
(2, 'basico', 1, 30.25, 'efectivo',      'REC-0001', DATE_SUB(CURDATE(), INTERVAL 33 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY),  3);

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
