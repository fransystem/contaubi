-- ============================================================
-- ContaUBI — Sistema Contable Universidad Boliviana de Informática
-- Schema de base de datos (MySQL 5.7+ / 8.0+)
--
-- Estructura del Plan de Cuentas según el PUCT (Plan Único de
-- Cuentas Tributario) - Bolivia
--
--   codigo = C G SG CP CA   (10 dígitos)
--   C   = Clase             (1 dígito)   cerrado por PUCT
--   G   = Grupo             (1 dígito)   cerrado por PUCT
--   SG  = Subgrupo          (2 dígitos)  cerrado por PUCT
--   CP  = Cuenta Principal  (3 dígitos)  cerrado por PUCT
--   CA  = Cuenta Analítica  (3 dígitos)  ABIERTO al contribuyente
-- ============================================================

CREATE DATABASE IF NOT EXISTS contaubi
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE contaubi;

DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS comprobantes;
DROP TABLE IF EXISTS cuentas;
DROP TABLE IF EXISTS empresa;

-- ------------------------------------------------------------
-- Empresa (configuración global, una sola fila)
-- ------------------------------------------------------------
CREATE TABLE empresa (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL DEFAULT 'Universidad Boliviana de Informática',
    nit         VARCHAR(20)  NOT NULL DEFAULT '0000000000',
    ciudad      VARCHAR(80)  NOT NULL DEFAULT 'La Paz',
    direccion   VARCHAR(200) DEFAULT '',
    telefono    VARCHAR(40)  DEFAULT '',
    email       VARCHAR(120) DEFAULT '',
    moneda      VARCHAR(10)  NOT NULL DEFAULT 'Bs.',
    ejercicio   INT          NOT NULL DEFAULT 2026,
    fecha_inicio_ejercicio DATE NOT NULL DEFAULT '2026-01-01',
    fecha_cierre_ejercicio DATE NOT NULL DEFAULT '2026-12-31',
    logo_texto  VARCHAR(10)  NOT NULL DEFAULT 'UBI',
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO empresa (nombre, nit, ciudad, direccion, telefono, email, moneda, ejercicio, fecha_inicio_ejercicio, fecha_cierre_ejercicio)
VALUES ('Universidad Boliviana de Informática', '1023456789', 'La Paz', 'Av. Arce N° 2799', '+591 2 2123456', 'contabilidad@ubi.edu.bo',
        'Bs.', 2026, '2026-01-01', '2026-12-31');

-- ------------------------------------------------------------
-- Plan de Cuentas (PUCT Bolivia, código de 10 dígitos)
--   clase, grupo:           1 dígito  (cerrados por PUCT)
--   subgrupo:               2 dígitos (cerrado por PUCT)
--   cuenta_principal:       3 dígitos (cerrado por PUCT)
--   cuenta_analitica:       3 dígitos (abierto al contribuyente)
-- ------------------------------------------------------------
CREATE TABLE cuentas (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    codigo             VARCHAR(10)  NOT NULL,
    clase              TINYINT      NOT NULL,
    grupo              TINYINT      NOT NULL DEFAULT 0,
    subgrupo           TINYINT      NOT NULL DEFAULT 0,
    cuenta_principal   SMALLINT     NOT NULL DEFAULT 0,
    cuenta_analitica   SMALLINT     NOT NULL DEFAULT 0,
    nivel              TINYINT      NOT NULL DEFAULT 5,  -- 1=C, 2=G, 3=SG, 4=CP, 5=CA
    nombre             VARCHAR(160) NOT NULL,
    descripcion        TEXT,
    naturaleza         ENUM('DEUDORA','ACREEDORA') NOT NULL,
    es_imputable       TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = acepta movimientos (sólo CA); 0 = sólo agrupación
    es_puct            TINYINT(1)   NOT NULL DEFAULT 1,  -- 1 = pertenece al PUCT (no editable estructuralmente)
    activa             TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_codigo (codigo),
    UNIQUE KEY uniq_nombre_nivel (clase, grupo, subgrupo, cuenta_principal, nombre),
    KEY idx_clase (clase),
    KEY idx_nivel (nivel),
    KEY idx_imputable (es_imputable, activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Comprobantes (cabecera del asiento)
-- ------------------------------------------------------------
CREATE TABLE comprobantes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    numero      VARCHAR(20) NOT NULL,
    tipo        ENUM('INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE') NOT NULL DEFAULT 'TRASPASO',
    fecha       DATE NOT NULL,
    glosa       VARCHAR(255) NOT NULL,
    moneda      VARCHAR(10) NOT NULL DEFAULT 'Bs.',
    estado      ENUM('BORRADOR','APROBADO','ANULADO') NOT NULL DEFAULT 'BORRADOR',
    total_debe  DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_haber DECIMAL(14,2) NOT NULL DEFAULT 0,
    creado_por  VARCHAR(80) DEFAULT 'sistema',
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_numero (numero),
    KEY idx_fecha (fecha),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Movimientos (líneas del asiento — partida doble)
-- ------------------------------------------------------------
CREATE TABLE movimientos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    comprobante_id  INT NOT NULL,
    cuenta_id       INT NOT NULL,
    debe            DECIMAL(14,2) NOT NULL DEFAULT 0,
    haber           DECIMAL(14,2) NOT NULL DEFAULT 0,
    glosa_linea     VARCHAR(255) DEFAULT '',
    orden           INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_mov_comp   FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_cuenta FOREIGN KEY (cuenta_id)      REFERENCES cuentas(id) ON DELETE RESTRICT,
    KEY idx_comp (comprobante_id),
    KEY idx_cuenta (cuenta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
