<button
    class="<?= e($buttonClass ?? 'button button-primary') ?>"
    type="<?= e($buttonType ?? 'submit') ?>"
    <?= !empty($buttonDisabled) ? 'disabled' : '' ?>
    <?= isset($buttonLoadingLabel) ? 'data-loading-label="' . e($buttonLoadingLabel) . '"' : '' ?>>
    <?= e($buttonLabel) ?>
</button>
