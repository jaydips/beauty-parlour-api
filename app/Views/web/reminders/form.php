<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$customerId  = old('customer_id', $customer['id'] ?? '');
$customerTxt = old('customer_query', $customer ? $customer['name'] . ' · ' . $customer['phone'] : '');
?>
<h1><?= esc($title) ?></h1>

<form method="post" action="<?= site_url('app/reminders') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <label for="customer_query">Customer</label>
    <input id="customer_query" name="customer_query" type="search" placeholder="નામ અથવા mobile નંબર લખો"
           value="<?= esc($customerTxt) ?>" data-search-url="<?= site_url('app/customers/search') ?>">
    <input id="customer_id" name="customer_id" type="hidden" value="<?= esc((string) $customerId) ?>">
    <div id="customer_suggestions" class="suggestions" hidden></div>

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

    <label for="message">Message (WhatsApp માં જશે)</label>
    <textarea id="message" name="message" rows="4" maxlength="2000" required><?= esc(old('message', '')) ?></textarea>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/reminders') ?>">રદ</a>
        <button type="submit" class="btn btn-primary">સાચવો</button>
    </div>
</form>

<?= $this->endSection() ?>
