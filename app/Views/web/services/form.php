<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $service !== null;
$action = $isEdit ? site_url('app/services/' . (int) $service['id']) : site_url('app/services');
$v      = static fn (string $f, string $d = ''): string => (string) old($f, $service[$f] ?? $d);
?>
<h1><?= esc($title) ?></h1>

<form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <label for="name"><?= esc(lang('App.name')) ?></label>
    <input id="name" name="name" type="text" maxlength="100" value="<?= esc($v('name')) ?>" required>

    <div class="grid2">
        <div>
            <label for="price"><?= esc(lang('App.price')) ?></label>
            <input id="price" name="price" type="number" inputmode="numeric" min="0" step="1" value="<?= esc($v('price', '0')) ?>" required>
        </div>
        <div>
            <label for="duration_minutes"><?= esc(lang('App.duration')) ?></label>
            <input id="duration_minutes" name="duration_minutes" type="number" inputmode="numeric" min="5" max="1440" step="5" value="<?= esc($v('duration_minutes', '30')) ?>" required>
        </div>
    </div>

    <div class="grid2">
        <div>
            <label for="category"><?= esc(lang('App.category')) ?></label>
            <input id="category" name="category" type="text" maxlength="50" value="<?= esc($v('category')) ?>" placeholder="<?= esc(lang('App.category_placeholder'), 'attr') ?>">
        </div>
        <div>
            <label for="sort_order"><?= esc(lang('App.sort_order')) ?></label>
            <input id="sort_order" name="sort_order" type="number" step="1" value="<?= esc($v('sort_order', '0')) ?>">
        </div>
    </div>

    <label class="check"><input type="checkbox" name="is_active" value="1" <?= old('is_active', $service['is_active'] ?? 1) ? 'checked' : '' ?>> <?= esc(lang('App.is_active')) ?></label>

    <label for="notes"><?= esc(lang('App.notes')) ?></label>
    <textarea id="notes" name="notes" rows="2"><?= esc($v('notes')) ?></textarea>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/services') ?>"><?= esc(lang('App.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= esc(lang('App.save')) ?></button>
    </div>
</form>

<?php if ($isEdit): ?>
<form method="post" action="<?= site_url('app/services/' . (int) $service['id'] . '/delete') ?>" data-confirm="<?= esc(lang('App.confirm_delete_service'), 'attr') ?>" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block"><?= esc(lang('App.delete_service')) ?></button>
</form>
<?php endif ?>

<?= $this->endSection() ?>
