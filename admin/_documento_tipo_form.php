<?php
/**
 * _documento_tipo_form.php – Formulario alta/edición de un tipo de documento.
 * Incluido desde documentos_catalogo.php. Variables: $t (fila o defaults), $csrf.
 */
if (!isset($t, $csrf)) { http_response_code(404); exit; }
$_fid = 'dt' . (int)$t['id'];
?>
<form method="post" class="edit-form">
  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
  <div>
    <label for="<?= $_fid ?>n">Nombre del documento</label>
    <input type="text" id="<?= $_fid ?>n" name="nombre" maxlength="120" required value="<?= h($t['nombre']) ?>">
  </div>
  <div>
    <label for="<?= $_fid ?>c">Categoría</label>
    <input type="text" id="<?= $_fid ?>c" name="categoria" maxlength="60" list="cats" required value="<?= h($t['categoria']) ?>">
  </div>
  <div>
    <label for="<?= $_fid ?>o">Orden</label>
    <input type="number" id="<?= $_fid ?>o" name="orden" min="0" max="999" value="<?= (int)$t['orden'] ?>">
  </div>
  <div class="full">
    <label for="<?= $_fid ?>d">Indicación <span style="text-transform:none;font-weight:400">(opcional, se muestra al capturar)</span></label>
    <input type="text" id="<?= $_fid ?>d" name="descripcion" maxlength="255" value="<?= h($t['descripcion'] ?? '') ?>" placeholder="Ej. Frente y reverso, no mayor a 3 meses…">
  </div>
  <label class="chk full"><input type="checkbox" name="obligatorio" value="1" <?= $t['obligatorio'] ? 'checked' : '' ?>> Obligatorio (cuenta para el avance del expediente)</label>
  <div class="foot"><button type="submit" class="btn btn-primary btn-sm"><?= $t['id'] ? 'Guardar cambios' : 'Agregar' ?></button></div>
</form>
