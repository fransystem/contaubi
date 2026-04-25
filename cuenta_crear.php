<?php
/**
 * ContaUBI — Crear cuenta analítica (5to nivel del PUCT)
 *
 * Reglas del PUCT (Bolivia):
 *  - Los niveles Clase/Grupo/Subgrupo/Cuenta Principal son CERRADOS por
 *    el SIN. El contribuyente NO puede agregar cuentas en esos niveles
 *    sin previa autorización.
 *  - Sólo el nivel 5 (Cuenta Analítica = CA, 3 dígitos) está abierto al
 *    contribuyente; aquí registra sus cuentas según su actividad.
 *
 * Validaciones:
 *  - Cuenta principal padre obligatoria y existente (PUCT, nivel 4).
 *  - CA entre 1 y 999 (3 dígitos).
 *  - Nombre obligatorio (máx 160 chars), único en el mismo nivel.
 *  - Naturaleza obligatoria (DEUDORA / ACREEDORA).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Nueva Cuenta Analítica';
$pageIcon   = 'bi-plus-circle';
$activePage = 'cuentas';

$error = '';
$old = [
    'parent_id'        => '',
    'cuenta_analitica' => '',
    'nombre'           => '',
    'descripcion'      => '',
    'naturaleza'       => '',
    'activa'           => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k=>$_) { $old[$k] = trim($_POST[$k] ?? $old[$k]); }

    $parent_id = (int)$old['parent_id'];
    $ca        = $old['cuenta_analitica'];

    if ($parent_id <= 0) {
        $error = 'Debe seleccionar una Cuenta Principal padre.';
    } elseif (!preg_match('/^[0-9]{1,3}$/', $ca) || (int)$ca < 1 || (int)$ca > 999) {
        $error = 'La Cuenta Analítica debe ser un número entre 1 y 999.';
    } elseif ($old['nombre'] === '') {
        $error = 'El nombre es obligatorio.';
    } elseif (mb_strlen($old['nombre']) > 160) {
        $error = 'El nombre supera los 160 caracteres.';
    } elseif (!in_array($old['naturaleza'], ['DEUDORA','ACREEDORA'], true)) {
        $error = 'La naturaleza es obligatoria.';
    } else {
        // Cargar el padre y verificar que sea nivel 4 (CP) del PUCT
        $stmt = $conn->prepare("SELECT clase, grupo, subgrupo, cuenta_principal, nivel, naturaleza
                                FROM cuentas WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $parent_id);
        $stmt->execute();
        $padre = $stmt->get_result()->fetch_assoc();

        if (!$padre || (int)$padre['nivel'] !== 4) {
            $error = 'La cuenta padre seleccionada no es una Cuenta Principal válida.';
        } else {
            $clase    = (int)$padre['clase'];
            $grupo    = (int)$padre['grupo'];
            $subgrupo = (int)$padre['subgrupo'];
            $cp       = (int)$padre['cuenta_principal'];
            $can      = (int)$ca;
            $codigo   = armar_codigo($clase, $grupo, $subgrupo, $cp, $can);

            // Verificar duplicado por nombre dentro del mismo CP padre
            $stmt = $conn->prepare("SELECT id FROM cuentas
                                    WHERE clase=? AND grupo=? AND subgrupo=? AND cuenta_principal=?
                                      AND LOWER(nombre)=LOWER(?) LIMIT 1");
            $stmt->bind_param('iiiis', $clase, $grupo, $subgrupo, $cp, $old['nombre']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Ya existe una cuenta con el nombre \"{$old['nombre']}\" bajo {$clase}.{$grupo}.".sprintf('%02d',$subgrupo).".".sprintf('%03d',$cp).".";
            } else {
                $act = $old['activa'] === '1' ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO cuentas
                    (codigo, clase, grupo, subgrupo, cuenta_principal, cuenta_analitica, nivel,
                     nombre, descripcion, naturaleza, es_imputable, es_puct, activa)
                    VALUES (?, ?, ?, ?, ?, ?, 5, ?, ?, ?, 1, 0, ?)");
                $stmt->bind_param('siiiiisssi',
                    $codigo, $clase, $grupo, $subgrupo, $cp, $can,
                    $old['nombre'], $old['descripcion'], $old['naturaleza'], $act);

                try {
                    $stmt->execute();
                    flash_set("Cuenta analítica {$codigo} creada.", 'success');
                    header('Location: cuentas.php'); exit;
                } catch (mysqli_sql_exception $e) {
                    if ($conn->errno === 1062) {
                        $error = "Ya existe otra cuenta con código {$codigo}.";
                    } else {
                        $error = 'Error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

/* Cargar todas las CP (cuenta principal del PUCT) para el selector padre */
$cps = $conn->query("SELECT id, codigo, clase, grupo, subgrupo, cuenta_principal,
                            nombre, naturaleza
                     FROM cuentas
                     WHERE nivel = 4 AND es_puct = 1 AND activa = 1
                     ORDER BY codigo")->fetch_all(MYSQLI_ASSOC);

/* Cargar el catálogo completo (para el JS de hints jerárquicos) */
$catalogo = [];
$rs = $conn->query("SELECT id, codigo, clase, grupo, subgrupo, cuenta_principal,
                           cuenta_analitica, nivel, nombre, naturaleza, es_imputable
                    FROM cuentas ORDER BY codigo");
while ($r = $rs->fetch_assoc()) {
    $catalogo[] = [
        'id'         => (int)$r['id'],
        'codigo'     => $r['codigo'],
        'clase'      => (int)$r['clase'],
        'grupo'      => (int)$r['grupo'],
        'subgrupo'   => (int)$r['subgrupo'],
        'cp'         => (int)$r['cuenta_principal'],
        'ca'         => (int)$r['cuenta_analitica'],
        'nivel'      => (int)$r['nivel'],
        'nombre'     => $r['nombre'],
        'naturaleza' => $r['naturaleza'],
        'imputable'  => (int)$r['es_imputable'],
    ];
}

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem"><i class="bi bi-arrow-left"></i> Volver</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="alert anim" style="background:rgba(13,138,79,.08);border:1px solid rgba(13,138,79,.3);border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.86rem;line-height:1.5">
  <i class="bi bi-info-circle" style="color:var(--accent-2,#0d8a4f)"></i>
  <strong>Plan Único de Cuentas Tributario (PUCT):</strong>
  Los niveles <strong>Clase / Grupo / Subgrupo / Cuenta Principal</strong> son
  <em>cerrados</em> por el SIN. Aquí sólo creas <strong>Cuentas Analíticas</strong>
  (5to nivel · CA) bajo una Cuenta Principal del PUCT.
</div>

<div class="card anim" style="max-width:820px">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Nueva Cuenta Analítica (CA)</div>
  <div class="card-body">

    <div style="background:rgba(20,184,106,.07);border:1px dashed rgba(20,184,106,.35);border-radius:10px;padding:.85rem 1rem;margin-bottom:.75rem;display:flex;align-items:center;gap:1rem">
      <div>
        <div class="form-label" style="margin:0">Código generado</div>
        <div id="codigoPrev" class="num" style="font-size:1.55rem;color:var(--accent-2);font-weight:700;letter-spacing:.05em">— — — — — — — — — —</div>
      </div>
      <div class="text-muted" style="font-size:.78rem;margin-left:auto;text-align:right;line-height:1.3">
        Estructura: <strong>C·G·SG·CP·CA</strong><br>
        1 + 1 + 2 + 3 + 3 = 10 dígitos
      </div>
    </div>

    <div id="rutaJerarquia" style="background:rgba(200,166,72,.08);border:1px solid rgba(200,166,72,.3);border-radius:10px;padding:.6rem .9rem;margin-bottom:1.25rem;font-size:.86rem;line-height:1.55;display:none">
      <i class="bi bi-diagram-3" style="color:var(--gold,#c8a648)"></i>
      <span class="text-muted">Pertenece a:</span>
      <span id="rutaTexto"></span>
    </div>

    <form method="POST" id="frmCuenta">
      <div class="form-group">
        <label class="form-label">Cuenta Principal padre (PUCT, nivel 4)</label>
        <select name="parent_id" id="f_parent" class="form-control" required onchange="actualizar()">
          <option value="">— Seleccione la Cuenta Principal del PUCT —</option>
          <?php foreach ($cps as $cp): ?>
            <option value="<?= $cp['id'] ?>"
                    data-clase="<?= $cp['clase'] ?>"
                    data-grupo="<?= $cp['grupo'] ?>"
                    data-subgrupo="<?= $cp['subgrupo'] ?>"
                    data-cp="<?= $cp['cuenta_principal'] ?>"
                    data-naturaleza="<?= $cp['naturaleza'] ?>"
                    <?= (string)$old['parent_id']===(string)$cp['id']?'selected':'' ?>>
              <?= h($cp['codigo']) ?> &nbsp;·&nbsp; <?= h($cp['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">
          Total CP cargadas del PUCT: <strong><?= count($cps) ?></strong>.
          Buscá por código (ej. <code>111001</code>) o por nombre.
        </div>
      </div>

      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Código de la Cuenta Analítica</label>
          <input type="text" inputmode="numeric" name="cuenta_analitica" id="f_ca"
                 class="form-control" maxlength="3" pattern="[0-9]{1,3}" required
                 value="<?= h($old['cuenta_analitica']) ?>"
                 placeholder="001 a 999"
                 oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,3); actualizar()">
          <div class="form-hint">3 dígitos (001-999) · <span class="hintNivel" id="hint_ca" style="color:var(--accent-2);font-weight:600">—</span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" id="f_naturaleza" class="form-control" required>
            <option value="">— Seleccione —</option>
            <option value="DEUDORA"  <?= $old['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA — saldo en el debe</option>
            <option value="ACREEDORA"<?= $old['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA — saldo en el haber</option>
          </select>
          <div class="form-hint">Por defecto se sugiere la naturaleza del CP padre.</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nombre de la cuenta</label>
        <input type="text" name="nombre" class="form-control" maxlength="160" required
               placeholder="Ej: Caja Moneda Nacional" value="<?= h($old['nombre']) ?>">
        <div class="form-hint">Máx 160 caracteres. No se permiten nombres duplicados bajo el mismo CP.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control form-textarea" placeholder="Opcional"><?= h($old['descripcion']) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Estado</label>
        <select name="activa" class="form-control">
          <option value="1" <?= $old['activa']==='1'?'selected':'' ?>>Activa</option>
          <option value="0" <?= $old['activa']==='0'?'selected':'' ?>>Inactiva (oculta en listas de movimiento)</option>
        </select>
      </div>

      <button class="btn btn-primary btn-block"><i class="bi bi-check-lg"></i> Guardar Cuenta Analítica</button>
    </form>
  </div>
</div>

<script>
const CATALOGO = <?= json_encode($catalogo, JSON_UNESCAPED_UNICODE) ?>;
const NOMBRES_CLASE = {1:'ACTIVO',2:'PASIVO',3:'PATRIMONIO',4:'INGRESOS',5:'EGRESOS'};

function pad(s,n){ s=String(s); while(s.length<n)s='0'+s; return s; }

function buscarHeader(level, c, g, sg, cp){
  if (level === 'clase')    return CATALOGO.find(x=>x.nivel===1 && x.clase===c);
  if (level === 'grupo')    return CATALOGO.find(x=>x.nivel===2 && x.clase===c && x.grupo===g);
  if (level === 'subgrupo') return CATALOGO.find(x=>x.nivel===3 && x.clase===c && x.grupo===g && x.subgrupo===sg);
  if (level === 'cp')       return CATALOGO.find(x=>x.nivel===4 && x.clase===c && x.grupo===g && x.subgrupo===sg && x.cp===cp);
  return null;
}

function pintar(id, valor, color) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = valor;
  el.style.color = color || 'var(--accent-2,#0d8a4f)';
}

function actualizar(){
  const sel    = document.getElementById('f_parent');
  const opt    = sel.selectedOptions[0];
  const caStr  = document.getElementById('f_ca').value;
  const ca     = caStr === '' ? 0 : parseInt(caStr,10);

  if (!opt || !opt.value) {
    document.getElementById('codigoPrev').textContent = '— — — — — — — — — —';
    document.getElementById('rutaJerarquia').style.display = 'none';
    pintar('hint_ca', 'pendiente', 'var(--text-muted,#8e98a8)');
    return;
  }
  const c  = parseInt(opt.dataset.clase, 10);
  const g  = parseInt(opt.dataset.grupo, 10);
  const sg = parseInt(opt.dataset.subgrupo, 10);
  const cp = parseInt(opt.dataset.cp, 10);

  // Pre-rellena la naturaleza con la del CP padre si aún no se eligió
  const fnat = document.getElementById('f_naturaleza');
  if (fnat.value === '' && opt.dataset.naturaleza) fnat.value = opt.dataset.naturaleza;

  // Código generado
  const codigo = '' + c + pad(g,1) + pad(sg,2) + pad(cp,3) + pad(ca,3);
  document.getElementById('codigoPrev').textContent = codigo;

  // Hint del CA
  if (caStr === '') {
    pintar('hint_ca', 'pendiente', 'var(--text-muted,#8e98a8)');
  } else if (ca < 1) {
    pintar('hint_ca', '⚠ debe ser entre 1 y 999', '#e85a5a');
  } else {
    const existe = CATALOGO.find(x => x.codigo === codigo);
    if (existe) pintar('hint_ca', '⚠ código en uso: ' + existe.nombre, '#e85a5a');
    else        pintar('hint_ca', 'libre', 'var(--accent-2,#0d8a4f)');
  }

  // Breadcrumb jerárquica
  const partes = [];
  const hC  = buscarHeader('clase', c);
  const hG  = buscarHeader('grupo', c, g);
  const hSg = buscarHeader('subgrupo', c, g, sg);
  const hCp = buscarHeader('cp', c, g, sg, cp);
  if (hC)  partes.push(`<strong>${c}</strong> · ${hC.nombre}`);
  if (hG)  partes.push(`<strong>${g}</strong> · ${hG.nombre}`);
  if (hSg) partes.push(`<strong>${pad(sg,2)}</strong> · ${hSg.nombre}`);
  if (hCp) partes.push(`<strong>${pad(cp,3)}</strong> · ${hCp.nombre}`);
  if (caStr !== '' && ca > 0) {
    partes.push(`<strong>${pad(ca,3)}</strong> · <span style="color:var(--gold,#c8a648)">(nueva CA)</span>`);
  }
  const ruta = document.getElementById('rutaJerarquia');
  if (partes.length > 0) {
    document.getElementById('rutaTexto').innerHTML = ' ' + partes.join(' <span style="color:var(--gold,#c8a648)">›</span> ');
    ruta.style.display = 'block';
  } else {
    ruta.style.display = 'none';
  }
}
actualizar();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
