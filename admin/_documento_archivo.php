<?php
/**
 * _documento_archivo.php – Un archivo del expediente (miniatura, datos, ver/descargar/eliminar).
 * Incluido desde empleados.php. Variables: $d (fila de empleado_documentos), $editEmp, $csrf.
 */
if (!isset($d, $editEmp, $csrf)) { http_response_code(404); exit; }
$_url   = 'empleado_documento.php?id=' . (int)$d['id'];
$_esPdf = $d['mime'] === 'application/pdf';
$_tit   = $d['nombre'] ?: $d['nombre_original'];
?>
<div class="file">
  <a href="<?= h($_url) ?>" target="_blank" rel="noopener">
    <?php if ($_esPdf): ?>
      <span class="file-thumb">PDF</span>
    <?php else: ?>
      <img class="file-thumb" src="<?= h($_url) ?>" alt="" loading="lazy">
    <?php endif ?>
  </a>
  <div class="file-info">
    <a href="<?= h($_url) ?>" target="_blank" rel="noopener" title="<?= h($d['nombre_original']) ?>"><?= h($_tit) ?></a>
    <span><?= kb((int)$d['bytes']) ?> · <?= fd($d['created_at']) ?><?= $d['subido_por_nombre'] ? ' · ' . h($d['subido_por_nombre']) : '' ?></span>
  </div>
  <a href="<?= h($_url) ?>&amp;dl=1" class="x" title="Descargar" aria-label="Descargar">↓</a>
  <form method="post" onsubmit="return confirm(<?= h(json_encode('¿Eliminar «' . $_tit . '» del expediente?', JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="doc_delete">
    <input type="hidden" name="id" value="<?= (int)$editEmp['id'] ?>">
    <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
    <button type="submit" class="x" title="Eliminar" aria-label="Eliminar">×</button>
  </form>
</div>
