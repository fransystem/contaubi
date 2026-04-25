<?php
/**
 * ContaUBI — Eliminar cuenta (sólo POST, con verificación de movimientos)
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: cuentas.php'); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { flash_set('ID inválido.','danger'); header('Location: cuentas.php'); exit; }

$row = $conn->query("SELECT codigo, es_puct FROM cuentas WHERE id=$id")->fetch_assoc();
if (!$row) { flash_set('Cuenta no encontrada.','danger'); header('Location: cuentas.php'); exit; }

if ((int)$row['es_puct'] === 1) {
    flash_set("La cuenta {$row['codigo']} pertenece al PUCT y no puede eliminarse.", 'danger');
    header('Location: cuentas.php'); exit;
}

$cnt = (int)$conn->query("SELECT COUNT(*) c FROM movimientos WHERE cuenta_id=$id")->fetch_assoc()['c'];
if ($cnt > 0) {
    flash_set("No se puede eliminar la cuenta {$row['codigo']}: tiene $cnt movimiento(s).", 'danger');
    header('Location: cuentas.php'); exit;
}

$stmt = $conn->prepare("DELETE FROM cuentas WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();

flash_set("Cuenta {$row['codigo']} eliminada.", 'success');
header('Location: cuentas.php'); exit;
