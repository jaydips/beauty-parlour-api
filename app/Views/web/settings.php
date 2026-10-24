<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1>Settings</h1>

<form method="post" action="<?= site_url('app/settings') ?>">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => $label): $value = (string) old($key, $values[$key] ?? ''); ?>
        <label for="<?= esc($key) ?>"><?= esc($label) ?></label>
        <?php if (str_starts_with($key, 'tpl_') || $key === 'parlour_address'): ?>
            <textarea id="<?= esc($key) ?>" name="<?= esc($key) ?>" rows="3"><?= esc($value) ?></textarea>
        <?php elseif (in_array($key, ['slot_minutes', 'reminder_lead_hours', 'followup_days'], true)): ?>
            <input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="number" inputmode="numeric" min="0" step="1" value="<?= esc($value) ?>">
        <?php elseif ($key === 'parlour_phone' || $key === 'country_code'): ?>
            <input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="tel" inputmode="numeric" value="<?= esc($value) ?>">
        <?php else: ?>
            <input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="text" value="<?= esc($value) ?>">
        <?php endif ?>
    <?php endforeach ?>

    <p class="muted">Templates માં વાપરી શકાય: {customer_name} {parlour_name} {date} {time} {services}</p>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/more') ?>">રદ</a>
        <button type="submit" class="btn btn-primary">સાચવો</button>
    </div>
</form>

<?= $this->endSection() ?>
