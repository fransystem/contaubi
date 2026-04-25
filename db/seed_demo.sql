-- ============================================================
-- ContaUBI — Comprobantes de demostración
-- (Opcional. Carga 4 asientos aprobados para tener datos en los reportes.)
-- ============================================================
USE contaubi;

-- 1) Aporte inicial de capital — 50,000 Bs en banco
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000001','APERTURA','2026-01-02','Aporte inicial de socios al banco','Bs.','APROBADO',50000,50000);
SET @c1 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c1, (SELECT id FROM cuentas WHERE codigo='1101002001'), 50000, 0, 'Depósito aporte socios', 1),
(@c1, (SELECT id FROM cuentas WHERE codigo='3101001001'), 0, 50000, 'Capital inicial', 2);

-- 2) Compra de mercadería — 8,400 Bs al contado
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000002','EGRESO','2026-01-15','Compra de mercadería para reventa','Bs.','APROBADO',8400,8400);
SET @c2 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c2, (SELECT id FROM cuentas WHERE codigo='1103001001'), 8400, 0, 'Compra mercadería', 1),
(@c2, (SELECT id FROM cuentas WHERE codigo='1101002001'), 0, 8400, 'Pago con cheque', 2);

-- 3) Venta — 12,000 Bs cobrados al contado
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000003','INGRESO','2026-02-05','Venta de mercadería al contado','Bs.','APROBADO',12000,12000);
SET @c3 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c3, (SELECT id FROM cuentas WHERE codigo='1101001001'), 12000, 0, 'Cobranza en efectivo', 1),
(@c3, (SELECT id FROM cuentas WHERE codigo='4101001001'), 0, 12000, 'Ingreso por venta', 2);

-- 4) Pago de sueldos — 5,000 Bs
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000004','EGRESO','2026-02-28','Sueldos del personal de febrero','Bs.','APROBADO',5000,5000);
SET @c4 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c4, (SELECT id FROM cuentas WHERE codigo='5201001001'), 5000, 0, 'Sueldos febrero', 1),
(@c4, (SELECT id FROM cuentas WHERE codigo='1101002001'), 0, 5000, 'Pago vía banco', 2);
