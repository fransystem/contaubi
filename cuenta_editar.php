<?php
/**
 * ContaUBI — Editar cuenta del plan de cuentas
 *
 * Reglas:
 *  - Cuentas del PUCT (es_puct=1): sólo se permite editar descripción y estado.
 *    El nombre, naturaleza y código son fijos según el SIN.
 *  - Cuentas analíticas del contribuyente (es_puct=0): se pueden editar
 *    nombre, descripción, naturaleza y estado. El código no se modifica.
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Editar Cuenta';
$pageIcon   = 'bi-pencil';
$activePage = 'cuentas';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM cuentas WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { flash_set('Cuenta no encontrada.','danger'); header('Location: cuentas.php'); exit; }

$es_puct = (int)$row['es_puct'] === 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activa      = ($_POST['activa'] ?? '0') === '1' ? 1 : 0;

    if ($es_puct) {
        // Solo descripción y estado
        $stmt = $conn->prepare("UPDATE cuentas SET descripcion=?, activa=? WHERE id=?");
        $stmt->bind_param('sii', $descripcion, $activa, $id);
        $stmt->execute();
        flash_set("Cuenta PUCT {$row['codigo']} actualizada (descripción/estado).", 'success');
        header('Location: cuentas.php'); exit;
    }

    // Cuenta analítica del contribuyente: edición completa
    $nombre     = trim($_POST['nombre'] ?? '');
    $naturaleza = $_POST['naturaleza'] ?? '';
    $imputable  = ($_POST['es_imputable'] ?? '0') === '1' ? 1 : 0;

    if ($nombre === '')                                            $error = 'Nombre obligatorio.';
    elseif (mb_strlen($nombre) > 160)                              $error = 'Nombre supera 160 caracteres.';
    elseif (!in_array($naturaleza, ['DEUDORA','ACREEDORA'], true)) $error = 'Naturaleza inválida.';
    else {
        $stmt = $conn->prepare("SELECT id FROM cuentas
                                WHERE clase=? AND grupo=? AND subgrupo=? AND cuenta_principal=?
                                  AND LOWER(nombre)=LOWER(?) AND id<>? LIMIT 1");
        $stmt->bind_param('iiiisi',
            $row['clase'], $row['grupo'], $row['subgrupo'], $row['cuenta_principal'],
            $nombre, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Ya existe otra cuenta con el nombre \"$nombre\" en el mismo nivel.";
        } else {
            if (!$imputable && (int)$row['es_imputable'] === 1) {
                $cnt = (int)$conn->query("SELECT COUNT(*) c FROM movimientos WHERE cuenta_id=$id")
                                 ->fetch_assoc()['c'];
                if ($cnt > 0) {
                    $error = "No se puede convertir a cabecera: la cuenta tiene $cnt movimiento(s).";
                }
            }
            if (!$error) {
                $stmt = $conn->prepare("UPDATE cuentas
                    SET nombre=?, descripcion=?, naturaleza=?, es_imputable=?, activa=?
                    WHERE id=?");
                $stmt->bind_param('sssiii', $nombre, $descripcion, $naturaleza, $imputable, $activa, $id);
                $stmt->execute();
                flash_set("Cuenta {$row['codigo']} actualizada.", 'success');
                header('Location: cuentas.php'); exit;
            }
        }
    }
    $row['nombre']=$nombre; $row['descripcion']=$descripcion; $row['naturaleza']=$naturaleza;
    $row['activa']=$activa; $row['es_imputable']=$imputable;
}

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem"><i class="bi bi-arrow-left"></i> Volver</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<?php if ($es_puct): ?>
<div class="alert anim" style="background:rgba(200,166,72,.10);border:1px solid rgba(200,166,72,.4);border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.86rem;line-height:1.5">
  <i class="bi bi-shield-lock" style="color:var(--gold,#c8a648)"></i>
  <strong>Cuenta del PUCT</strong> — el SIN define el código, nombre y naturaleza
  de esta cuenta. Sólo se puede editar la <em>descripción</em> y el <em>estado</em>.
</div>
<?php endif; ?>

<div class="card anim" style="max-width:760px">
  <div class="card-header">
    <i class="bi bi-pencil"></i> Editando: <span class="chip"><?= h($row['codigo']) ?></span>
    &nbsp;<small class="text-muted"><?= h($row['nombre']) ?> · <?= nombre_nivel((int)$row['nivel']) ?></small>
  </div>
  <div class="card-body">
    <form method="POST">

      <?php if (!$es_puct): ?>
      <div class="form-group">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" maxlength="160" required value="<?= h($row['nombre']) ?>">
      </div>
      <?php else: ?>
      <div class="form-group">
        <label class="form-label">Nombre (PUCT, sólo lectura)</label>
        <input type="text" class="form-control" disabled value="<?= h($row['nombre']) ?>">
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control form-textarea" placeholder="Opcional"><?= h($row['descripcion']) ?></textarea>
      </div>

      <?php if (!$es_puct): ?>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" class="form-control" required>
            <option value="DEUDORA"  <?= $row['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA</option>
            <option value="ACREEDORA"<?= $row['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tipo</label>
          <select name="es_imputable" class="form-control">
            <option value="1" <?= $row['es_imputable']?'selected':'' ?>>Imputable</option>
            <option value="0" <?= !$row['es_imputable']?'selected':'' ?>>Cabecera (no acepta movimientos)</option>
          </select>
        </div>
      </div>
      <?php else: ?>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Naturaleza (PUCT)</label>
          <input type="text" class="form-control" disabled value="<?= h($row['naturaleza']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Tipo (PUCT)</label>
          <input type="text" class="form-control" disabled value="<?= $row['es_imputable']?'Imputable':'Cabecera / agrupación' ?>">
        </div>
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Estado</label>
        <select name="activa" class="form-control">
          <option value="1" <?= $row['activa']?'selected':'' ?>>Activa</option>
          <option value="0" <?= !$row['activa']?'selected':'' ?>>Inactiva</option>
        </select>
      </div>

      <button class="btn btn-primary btn-block"><i class="bi bi-check-lg"></i> Guardar cambios</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
