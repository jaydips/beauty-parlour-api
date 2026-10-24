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
    <label for="name">નામ</label>
    <input id="name" name="name" type="text" maxlength="100" value="<?= esc($v('name')) ?>" required>

    <div class="grid2">
        <div>
            <label for="price">ભાવ (₹)</label>
            <input id="price" name="price" type="number" inputmode="numeric" min="0" step="1" value="<?= esc($v('price', '0')) ?>" required>
        </div>
        <div>
            <label for="duration_minutes">સમય (મિનિટ)</label>
            <input id="duration_minutes" name="duration_minutes" type="number" inputmode="numeric" min="5" max="1440" step="5" value="<?= esc($v('duration_minutes', '30')) ?>" required>
        </div>
    </div>

    <div class="grid2">
        <div>
            <label for="category">Category</label>
            <input id="category" name="category" type="text" maxlength="50" value="<?= esc($v('category')) ?>" placeholder="Hair, Skin, Bridal…">
        </div>
        <div>
            <label for="sort_order">ક્રમ</label>
            <input id="sort_order" name="sort_order" type="number" step="1" value="<?= esc($v('sort_order', '0')) ?>">
        </div>
    </div>

    <label class="check"><input type="checkbox" name="is_active" value="1" <?= old('is_active', $service['is_active'] ?? 1) ? 'checked' : '' ?>> ચાલુ (booking માં દેખાય)</label>

    <label for="notes">નોંધ</label>
    <textarea id="notes" name="notes" rows="2"><?= esc($v('notes')) ?></textarea>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/services') ?>">રદ</a>
        <button type="submit" class="btn btn-primary">સાચવો</button>
    </div>
</form>

<?php if ($isEdit): ?>
<form method="post" action="<?= site_url('app/services/' . (int) $service['id'] . '/delete') ?>" data-confirm="આ service દૂર કરવી?" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block">Service દૂર કરો</button>
</form>
<?php endif ?>

<?= $this->endSection() ?>
