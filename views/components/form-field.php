<label class="form-field" for="<?= e($fieldId) ?>">
    <span><?= e($fieldLabel) ?></span>
    <input
        id="<?= e($fieldId) ?>"
        name="<?= e($fieldName) ?>"
        type="<?= e($fieldType ?? 'text') ?>"
        autocomplete="<?= e($fieldAutocomplete ?? 'off') ?>"
        <?= isset($fieldMaxLength) ? 'maxlength="' . e((string) $fieldMaxLength) . '"' : '' ?>
        <?= isset($fieldValue) ? 'value="' . e((string) $fieldValue) . '"' : '' ?>
        <?= isset($fieldDescribedBy) ? 'aria-describedby="' . e($fieldDescribedBy) . '"' : '' ?>
        <?= !empty($fieldInvalid) ? 'aria-invalid="true"' : 'aria-invalid="false"' ?>
        <?= !empty($fieldDisabled) ? 'disabled' : '' ?>
        <?= ($fieldRequired ?? true) ? 'required' : '' ?>>
</label>
