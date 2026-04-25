<?php
/**
 * ContaUBI — Plan de Cuentas (listado con filtros)
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Plan de Cuentas';
$pageIcon   = 'bi-list-columns-reverse';
$activePage = 'cuentas';

/* Filtros */
$fClase     = $_GET['clase']     ?? '';
$fNaturaleza= $_GET['naturaleza']?? '';
$fEstado    = $_GET['estado']    ?? '';
$fImputable = $_GET['imputable'] ?? '';
$fNivel     = $_GET['nivel']     ?? '';
$fOrigen    = $_GET['origen']    ?? '';
$fQ         = trim($_GET['q']    ?? '');

$where  = []; $params = []; $types = '';
if ($fClase !== '' && ctype_digit($fClase) && $fClase >= 1 && $fClase <= 5) { $where[]='clase = ?'; $types.='i'; $params[]=(int)$fClase; }
if ($fNaturaleza === 'DEUDORA' || $fNaturaleza === 'ACREEDORA')              { $where[]='naturaleza = ?'; $types.='s'; $params[]=$fNaturaleza; }
if ($fEstado === 'activa')   { $where[]='activa = 1'; }
if ($fEstado === 'inactiva') { $where[]='activa = 0'; }
if ($fImputable === 'si') { $where[]='es_imputable = 1'; }
if ($fImputable === 'no') { $where[]='es_imputable = 0'; }
if (ctype_digit($fNivel) && $fNivel >= 1 && $fNivel <= 5) { $where[]='nivel = ?'; $types.='i'; $params[]=(int)$fNivel; }
if ($fOrigen === 'puct')   { $where[]='es_puct = 1'; }
if ($fOrigen === 'propia') { $where[]='es_puct = 0'; }
if ($fQ !== '') { $where[]='(LOWER(nombre) LIKE ? OR codigo LIKE ?)'; $types.='ss'; $like='%'.mb_strtolower($fQ).'%'; $params[]=$like; $params[]=$like; }

$sql = "SELECT * FROM cuentas " . ($where ? 'WHERE '.implode(' AND ',$where) : '') . " ORDER BY codigo ASC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Estadísticas */
$stats = $conn->query("SELECT clase, COUNT(*) c FROM cuentas GROUP BY clase")->fetch_all(MYSQLI_ASSOC);
$byClase = [1=>0,2=>0,3=>0,4=>0,5=>0];
foreach ($stats as $r) { $byClase[(int)$r['clase']] = (int)$r['c']; }
$total = array_sum($byClase);

include __DIR__ . '/layout_top.php';
?>

<div class="kpi-grid no-print">
  <div class="kpi"><div class="kpi-label">Total</div><div class="kpi-value"><?= $total ?></div></div>
  <div class="kpi"><div class="kpi-label">Activos</div><div class="kpi-value" style="color:#6ee9a6"><?= $byClase[1] ?></div></div>
  <div class="kpi"><div class="kpi-label">Pasivos</div><div class="kpi-value" style="color:#ff8e8a"><?= $byClase[2] ?></div></div>
  <div class="kpi"><div class="kpi-label">Patrimonio</div><div class="kpi-value" style="color:#94b5ff"><?= $byClase[3] ?></div></div>
  <div class="kpi"><div class="kpi-label">Ingresos</div><div class="kpi-value" style="color:#6ee9a6"><?= $byClase[4] ?></div></div>
  <div class="kpi"><div class="kpi-label">Egresos</div><div class="kpi-value" style="color:#ffd28a"><?= $byClase[5] ?></div></div>
</div>

<form method="GET" class="filtros no-print">
  <div class="form-group">
    <label class="form-label">Clase</label>
    <select name="clase" class="form-control">
      <option value="">Todas</option>
      <?php foreach ([1=>'ACTIVO',2=>'PASIVO',3=>'PATRIMONIO',4=>'INGRESOS',5=>'EGRESOS'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= (string)$fClase===(string)$k?'selected':'' ?>><?= $k ?> · <?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Naturaleza</label>
    <select name="naturaleza" class="form-control">
      <option value="">Todas</option>
      <option value="DEUDORA"  <?= $fNaturaleza==='DEUDORA' ?'selected':'' ?>>Deudora</option>
      <option value="ACREEDORA"<?= $fNaturaleza==='ACREEDORA'?'selected':'' ?>>Acreedora</option>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Imputable</label>
    <select name="imputable" class="form-control">
      <option value="">Cualquiera</option>
      <option value="si" <?= $fImputable==='si'?'selected':'' ?>>Solo cuentas de movimiento</option>
      <option value="no" <?= $fImputable==='no'?'selected':'' ?>>Solo cabeceras / agrupación</option>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Nivel jerárquico</label>
    <select name="nivel" class="form-control">
      <option value="">Todos</option>
      <?php foreach ([1=>'1 · Clase',2=>'2 · Grupo',3=>'3 · Subgrupo',4=>'4 · Cuenta Principal',5=>'5 · Cuenta Analítica'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= (string)$fNivel===(string)$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Origen</label>
    <select name="origen" class="form-control">
      <option value="">Todas</option>
      <option value="puct"   <?= $fOrigen==='puct'  ?'selected':'' ?>>Sólo PUCT (SIN)</option>
      <option value="propia" <?= $fOrigen==='propia'?'selected':'' ?>>Cuentas analíticas propias</option>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-control">
      <option value="">Cualquiera</option>
      <option value="activa"   <?= $fEstado==='activa'  ?'selected':'' ?>>Activas</option>
      <option value="inactiva" <?= $fEstado==='inactiva'?'selected':'' ?>>Inactivas</option>
    </select>
  </div>
  <div class="form-group" style="grid-column: span 2">
    <label class="form-label">Buscar</label>
    <input type="search" name="q" class="form-control" placeholder="Código o nombre…" value="<?= h($fQ) ?>">
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn" href="cuentas.php"><i class="bi bi-x-circle"></i> Limpiar</a>
  </div>
</form>

<div class="card-header" style="background:transparent;border:0;padding:0 0 .75rem 0">
  <span class="text-muted"><?= count($rows) ?> resultado(s)</span>
  <div class="no-print" style="display:flex;gap:.5rem">
    <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    <a class="btn btn-primary" href="cuenta_crear.php"><i class="bi bi-plus-circle"></i> Nueva Cuenta Analítica</a>
  </div>
</div>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>PLAN DE CUENTAS</strong></p>
  <p>Ejercicio: <?= h($EMPRESA['ejercicio']) ?> · Emitido: <?= date('d/m/Y H:i') ?></p>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($rows)): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><p>No hay cuentas que coincidan con los filtros.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="min-width:120px">Código</th>
            <th>Nombre / Descripción</th>
            <th>Clase</th>
            <th class="text-center">Nivel</th>
            <th>Nat.</th>
            <th class="text-center">Origen</th>
            <th class="text-center">Imputable</th>
            <th class="text-center">Activa</th>
            <th class="text-center no-print"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $isHeader = !$r['es_imputable'];
          ?>
          <tr<?= $isHeader ? ' class="fila-grupo"' : '' ?>>
            <td><span class="chip"><?= h($r['codigo']) ?></span></td>
            <td>
              <div class="fw-600"><?= h($r['nombre']) ?></div>
              <?php if (!empty($r['descripcion'])): ?>
                <div class="text-muted" style="font-size:.78rem"><?= h($r['descripcion']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= clase_badge((int)$r['clase']) ?>"><?= nombre_clase((int)$r['clase']) ?></span></td>
            <td class="text-center"><span class="text-muted" style="font-size:.78rem" title="<?= h(nombre_nivel((int)$r['nivel'])) ?>">N<?= (int)$r['nivel'] ?></span></td>
            <td><span class="text-muted" style="font-size:.78rem"><?= h(substr($r['naturaleza'],0,4)) ?>.</span></td>
            <td class="text-center">
              <?php if ($r['es_puct']): ?>
                <span class="text-muted" style="font-size:.7rem" title="Plan Único de Cuentas Tributario (SIN)">PUCT</span>
              <?php else: ?>
                <span style="font-size:.7rem;color:var(--gold,#c8a648)" title="Cuenta analítica del contribuyente">propia</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($r['es_imputable']): ?>
                <i class="bi bi-check-circle-fill" style="color:#6ee9a6" title="Acepta movimientos"></i>
              <?php else: ?>
                <i class="bi bi-folder2" style="color:var(--gold-2)" title="Sólo agrupación"></i>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($r['activa']): ?>
                <i class="bi bi-circle-fill" style="color:#6ee9a6;font-size:.6rem"></i>
              <?php else: ?>
                <i class="bi bi-circle" style="color:var(--muted);font-size:.6rem"></i>
              <?php endif; ?>
            </td>
            <td class="text-center no-print">
              <a class="btn btn-ghost btn-sm" href="cuenta_editar.php?id=<?= (int)$r['id'] ?>" title="Editar">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="cuenta_eliminar.php" style="display:inline" onsubmit="return confirm('¿Eliminar la cuenta <?= h($r['codigo']) ?>?\nSolo se puede borrar si no tiene movimientos.');">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-ghost btn-sm" title="Eliminar"><i class="bi bi-trash" style="color:var(--danger)"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
