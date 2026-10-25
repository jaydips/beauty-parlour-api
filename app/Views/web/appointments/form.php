<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$isEdit      = $appointment !== null;
$action      = $isEdit ? site_url('app/appointments/' . (int) $appointment['id']) : site_url('app/appointments');
$customerId  = old('customer_id', $customer['id'] ?? '');
$customerTxt = old('customer_query', $customer ? $customer['name'] . ' · ' . $customer['phone'] : '');
?>
<h1><?= esc($title) ?></h1>

<form id="appointment_form" method="post" action="<?= $action ?>" autocomplete="off">
    <?= csrf_field() ?>

    <label for="customer_query"><?= esc(lang('App.customer')) ?></label>
    <input id="customer_query" name="customer_query" type="search" placeholder="<?= esc(lang('App.search_name_or_phone'), 'attr') ?>"
           value="<?= esc($customerTxt) ?>" data-search-url="<?= site_url('app/customers/search') ?>">
    <input id="customer_id" name="customer_id" type="hidden" value="<?= esc((string) $customerId) ?>">
    <div id="customer_suggestions" class="suggestions" hidden></div>

    <div id="new_customer" class="subform" <?= $customerId !== '' ? 'hidden' : '' ?>>
        <p class="muted"><?= esc(lang('App.customer_not_found_add_new')) ?></p>
        <label for="new_name"><?= esc(lang('App.name')) ?></label>
        <input id="new_name" name="new_name" type="text" value="<?= esc(old('new_name', '')) ?>" maxlength="100">
        <label for="new_phone"><?= esc(lang('App.mobile_10')) ?></label>
        <input id="new_phone" name="new_phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc(old('new_phone', '')) ?>">
    </div>

    <div class="grid2">
        <div>
            <label for="date"><?= esc(lang('App.date')) ?></label>
            <input id="date" name="date" type="date" value="<?= esc($date) ?>" required>
        </div>
        <div>
            <label for="time"><?= esc(lang('App.time')) ?></label>
            <input id="time" name="time" type="time" value="<?= esc($time) ?>" step="900" required>
        </div>
    </div>

    <label><?= esc(lang('App.services')) ?></label>
    <?php if ($services === []): ?>
        <p class="empty"><?= esc(lang('App.add_services_first')) ?></p>
    <?php endif ?>
    <?php foreach ($services as $s): $sid = (int) $s['id']; $qty = $selected[$sid] ?? 1; ?>
        <div class="svc-row" data-price="<?= (int) $s['price'] ?>">
            <input type="checkbox" id="svc_<?= $sid ?>" name="svc[<?= $sid ?>]" value="<?= (int) $qty ?>" <?= isset($selected[$sid]) ? 'checked' : '' ?>>
            <label for="svc_<?= $sid ?>">
                <span><?= esc($s['name']) ?></span>
                <small class="muted"><?= (int) $s['duration_minutes'] ?> <?= esc(lang('App.minutes_short')) ?> · <?= esc(rupees($s['price'])) ?></small>
            </label>
            <input class="qty" type="number" min="1" max="20" value="<?= (int) $qty ?>" aria-label="<?= esc(lang('App.quantity'), 'attr') ?>">
        </div>
    <?php endforeach ?>

    <div class="grid2">
        <div>
            <label for="discount"><?= esc(lang('App.discount')) ?></label>
            <input id="discount" name="discount" type="number" min="0" step="1" value="<?= esc(old('discount', (string) ($appointment['discount'] ?? 0))) ?>">
        </div>
        <div>
            <label for="source"><?= esc(lang('App.source')) ?></label>
            <select id="source" name="source">
                <option value="">-</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= esc($src) ?>" <?= old('source', $appointment['source'] ?? '') === $src ? 'selected' : '' ?>><?= esc(source_label($src)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>

    <label for="notes"><?= esc(lang('App.notes')) ?></label>
    <textarea id="notes" name="notes" rows="2"><?= esc(old('notes', $appointment['notes'] ?? '')) ?></textarea>

    <div class="total"><?= esc(lang('App.total')) ?>: <strong id="live_total">₹0</strong></div>

    <label class="check"><input type="checkbox" name="force" value="1" <?= old('force') ? 'checked' : '' ?>> <?= esc(lang('App.force_book')) ?></label>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $isEdit ? site_url('app/appointments/' . (int) $appointment['id']) : site_url('app/appointments') . '?date=' . esc($date) ?>"><?= esc(lang('App.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= esc($isEdit ? lang('App.save') : lang('App.book')) ?></button>
    </div>
</form>

<?= $this->endSection() ?>
