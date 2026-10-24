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

    <label for="customer_query">Customer</label>
    <input id="customer_query" name="customer_query" type="search" placeholder="નામ અથવા mobile નંબર લખો"
           value="<?= esc($customerTxt) ?>" data-search-url="<?= site_url('app/customers/search') ?>">
    <input id="customer_id" name="customer_id" type="hidden" value="<?= esc((string) $customerId) ?>">
    <div id="customer_suggestions" class="suggestions" hidden></div>

    <div id="new_customer" class="subform" <?= $customerId !== '' ? 'hidden' : '' ?>>
        <p class="muted">Customer ન મળે તો નવો ઉમેરો:</p>
        <label for="new_name">નામ</label>
        <input id="new_name" name="new_name" type="text" value="<?= esc(old('new_name', '')) ?>" maxlength="100">
        <label for="new_phone">Mobile (10 અંક)</label>
        <input id="new_phone" name="new_phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc(old('new_phone', '')) ?>">
    </div>

    <div class="grid2">
        <div>
            <label for="date">તારીખ</label>
            <input id="date" name="date" type="date" value="<?= esc($date) ?>" required>
        </div>
        <div>
            <label for="time">સમય</label>
            <input id="time" name="time" type="time" value="<?= esc($time) ?>" step="900" required>
        </div>
    </div>

    <label>Services</label>
    <?php if ($services === []): ?>
        <p class="empty">પહેલાં "વધુ → Services" માં services ઉમેરો.</p>
    <?php endif ?>
    <?php foreach ($services as $s): $sid = (int) $s['id']; $qty = $selected[$sid] ?? 1; ?>
        <div class="svc-row" data-price="<?= (int) $s['price'] ?>">
            <input type="checkbox" id="svc_<?= $sid ?>" name="svc[<?= $sid ?>]" value="<?= (int) $qty ?>" <?= isset($selected[$sid]) ? 'checked' : '' ?>>
            <label for="svc_<?= $sid ?>">
                <span><?= esc($s['name']) ?></span>
                <small class="muted"><?= (int) $s['duration_minutes'] ?> min · <?= esc(rupees($s['price'])) ?></small>
            </label>
            <input class="qty" type="number" min="1" max="20" value="<?= (int) $qty ?>" aria-label="કેટલી વાર">
        </div>
    <?php endforeach ?>

    <div class="grid2">
        <div>
            <label for="discount">Discount (₹)</label>
            <input id="discount" name="discount" type="number" min="0" step="1" value="<?= esc(old('discount', (string) ($appointment['discount'] ?? 0))) ?>">
        </div>
        <div>
            <label for="source">કઈ રીતે આવ્યા</label>
            <select id="source" name="source">
                <option value="">-</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= esc($src) ?>" <?= old('source', $appointment['source'] ?? '') === $src ? 'selected' : '' ?>><?= esc(match ($src) { 'walk_in' => 'Walk-in', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', default => 'App' }) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>

    <label for="notes">નોંધ</label>
    <textarea id="notes" name="notes" rows="2"><?= esc(old('notes', $appointment['notes'] ?? '')) ?></textarea>

    <div class="total">કુલ: <strong id="live_total">₹0</strong></div>

    <label class="check"><input type="checkbox" name="force" value="1" <?= old('force') ? 'checked' : '' ?>> slot ટકરાય તો પણ book કરો</label>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $isEdit ? site_url('app/appointments/' . (int) $appointment['id']) : site_url('app/appointments') . '?date=' . esc($date) ?>">રદ</a>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'સાચવો' : 'Book કરો' ?></button>
    </div>
</form>

<?= $this->endSection() ?>
