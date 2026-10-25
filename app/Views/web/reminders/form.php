<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$customerId  = old('customer_id', $customer['id'] ?? '');
$customerTxt = old('customer_query', $customer ? $customer['name'] . ' · ' . $customer['phone'] : '');
?>
<h1><?= esc($title) ?></h1>

<form method="post" action="<?= site_url('app/reminders') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <label for="customer_query"><?= esc(lang('App.customer')) ?></label>
    <input id="customer_query" name="customer_query" type="search" placeholder="<?= esc(lang('App.search_name_or_phone'), 'attr') ?>"
           value="<?= esc($customerTxt) ?>" data-search-url="<?= site_url('app/customers/search') ?>">
    <input id="customer_id" name="customer_id" type="hidden" value="<?= esc((string) $customerId) ?>">
    <div id="customer_suggestions" class="suggestions" hidden></div>

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

    <label for="message"><?= esc(lang('App.message_whatsapp')) ?></label>
    <textarea id="message" name="message" rows="4" maxlength="2000" required><?= esc(old('message', '')) ?></textarea>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/reminders') ?>"><?= esc(lang('App.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= esc(lang('App.save')) ?></button>
    </div>
</form>

<?= $this->endSection() ?>
