<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$id       = (int) $a['id'];
$open     = in_array($a['status'], ['scheduled', 'confirmed'], true);
$customer = $a['customer'] ?? null;
?>
<div class="hdr">
    <div>
        <h1><?= esc(fmt_date($a['starts_at'])) ?></h1>
        <div class="muted"><?= esc(fmt_time($a['starts_at'])) ?> – <?= esc(fmt_time($a['ends_at'])) ?></div>
    </div>
    <span class="badge <?= esc(status_class($a['status'])) ?>"><?= esc(status_label($a['status'])) ?></span>
</div>

<?php if ($customer): ?>
<div class="card">
    <div class="card-top">
        <a class="card-title" href="<?= site_url('app/customers/' . (int) $customer['id']) ?>"><?= esc($customer['name']) ?></a>
        <span class="muted"><?= esc($customer['phone']) ?></span>
    </div>
    <div class="actions">
        <a class="btn btn-ghost btn-sm" href="tel:<?= esc($customer['phone'], 'attr') ?>"><?= esc(lang('App.call')) ?></a>
        <a class="btn btn-wa btn-sm" href="<?= esc(service('whatsapp')->link($customer['phone'], ''), 'attr') ?>" target="_blank" rel="noopener"><?= esc(lang('App.whatsapp')) ?></a>
    </div>
</div>
<?php endif ?>

<div class="card">
    <?php foreach ($a['items'] as $item): ?>
        <div class="row">
            <span><?= esc($item['service_name']) ?><?= (int) $item['quantity'] > 1 ? ' ×' . (int) $item['quantity'] : '' ?> <small class="muted"><?= (int) $item['duration_minutes'] ?> <?= esc(lang('App.minutes_short')) ?></small></span>
            <span><?= esc(rupees((int) $item['price'] * (int) $item['quantity'])) ?></span>
        </div>
    <?php endforeach ?>
    <?php if ((int) $a['discount'] > 0): ?>
        <div class="row sub"><span><?= esc(lang('App.discount_label')) ?></span><span>- <?= esc(rupees($a['discount'])) ?></span></div>
    <?php endif ?>
    <div class="row"><strong><?= esc(lang('App.total')) ?></strong><strong><?= esc(rupees($a['total'])) ?></strong></div>
    <div class="row sub"><span><?= esc(lang('App.paid')) ?></span><span><?= esc(rupees($a['paid'])) ?></span></div>
    <div class="row"><span><?= esc(lang('App.balance')) ?></span><strong class="<?= $a['balance'] > 0 ? 'due' : '' ?>"><?= esc(rupees($a['balance'])) ?></strong></div>
    <?php if (! empty($a['notes'])): ?><p class="msg"><?= esc($a['notes']) ?></p><?php endif ?>
</div>

<?php if ($open): ?>
<div class="actions">
    <?php if ($a['status'] === 'scheduled'): ?>
    <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>"><?= csrf_field() ?><input type="hidden" name="status" value="confirmed"><button class="btn btn-ghost"><?= esc(lang('App.confirm')) ?></button></form>
    <?php endif ?>
    <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>"><?= csrf_field() ?><input type="hidden" name="status" value="completed"><button class="btn btn-primary"><?= esc(lang('App.mark_completed')) ?></button></form>
    <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>" data-confirm="<?= esc(lang('App.confirm_no_show'), 'attr') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="no_show"><button class="btn btn-ghost"><?= esc(lang('App.no_show')) ?></button></form>
    <a class="btn btn-ghost" href="<?= site_url("app/appointments/{$id}/edit") ?>"><?= esc(lang('App.edit')) ?></a>
</div>
<form class="card inline-form" method="post" action="<?= site_url("app/appointments/{$id}/status") ?>" data-confirm="<?= esc(lang('App.confirm_cancel_appointment'), 'attr') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="status" value="cancelled">
    <label for="reason"><?= esc(lang('App.cancel_reason')) ?></label>
    <div class="inline">
        <input id="reason" name="reason" type="text" maxlength="255" placeholder="<?= esc(lang('App.reason'), 'attr') ?>">
        <button class="btn btn-danger"><?= esc(lang('App.cancel_action')) ?></button>
    </div>
</form>
<?php elseif (in_array($a['status'], ['cancelled', 'no_show'], true)): ?>
<div class="card">
    <div class="muted"><?= esc(status_label($a['status'])) ?><?= $a['cancel_reason'] ? ' · ' . esc($a['cancel_reason']) : '' ?></div>
    <div class="actions">
        <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>"><?= csrf_field() ?><input type="hidden" name="status" value="scheduled"><button class="btn btn-ghost btn-sm"><?= esc(lang('App.reschedule')) ?></button></form>
    </div>
</div>
<?php endif ?>

<section class="block">
    <div class="block-head"><h2><?= esc(lang('App.payments')) ?></h2></div>
    <?php foreach ($a['payments'] as $p): ?>
        <div class="card">
            <div class="row">
                <span><?= esc(method_label($p['method'])) ?> <small class="muted"><?= esc(fmt_datetime($p['paid_at'])) ?><?= $p['reference'] ? ' · ' . esc($p['reference']) : '' ?></small></span>
                <strong><?= $p['type'] === 'refund' ? '- ' : '' ?><?= esc(rupees($p['amount'])) ?></strong>
            </div>
            <form method="post" action="<?= site_url('app/payments/' . (int) $p['id'] . '/delete') ?>" data-confirm="<?= esc(lang('App.confirm_delete_payment'), 'attr') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= esc(lang('App.remove')) ?></button></form>
        </div>
    <?php endforeach ?>
    <form class="card" method="post" action="<?= site_url('app/payments') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="appointment_id" value="<?= $id ?>">
        <div class="grid2">
            <div>
                <label for="amount"><?= esc(lang('App.amount')) ?></label>
                <input id="amount" name="amount" type="number" inputmode="numeric" min="1" step="1" value="<?= $a['balance'] > 0 ? (int) $a['balance'] : '' ?>" required>
            </div>
            <div>
                <label for="method"><?= esc(lang('App.method')) ?></label>
                <select id="method" name="method">
                    <option value="cash"><?= esc(method_label('cash')) ?></option>
                    <option value="upi" selected><?= esc(method_label('upi')) ?></option>
                    <option value="card"><?= esc(method_label('card')) ?></option>
                    <option value="other"><?= esc(method_label('other')) ?></option>
                </select>
            </div>
        </div>
        <label for="reference"><?= esc(lang('App.reference')) ?></label>
        <input id="reference" name="reference" type="text" maxlength="100">
        <label class="check"><input type="checkbox" name="type" value="refund"> <?= esc(lang('App.is_refund')) ?></label>
        <button class="btn btn-primary btn-block"><?= esc(lang('App.record_payment')) ?></button>
    </form>
</section>

<section class="block">
    <div class="block-head"><h2><?= esc(lang('App.reminders')) ?></h2></div>
    <?php if ($a['reminders'] === []): ?>
        <p class="empty"><?= esc(lang('App.no_reminders')) ?></p>
    <?php else: ?>
        <?php foreach ($a['reminders'] as $r): ?>
            <?php if ($r['status'] === 'pending'): ?>
                <?= view('web/partials/reminder_card', ['r' => $r + ['customer' => $customer]]) ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-top"><span><?= esc(reminder_type_label($r['type'])) ?></span><span class="badge <?= esc(status_class($r['status'])) ?>"><?= esc(status_label($r['status'])) ?></span></div>
                    <div class="muted"><?= esc(fmt_datetime($r['due_at'])) ?></div>
                </div>
            <?php endif ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?php if ($a['payments'] === []): ?>
<form method="post" action="<?= site_url("app/appointments/{$id}/delete") ?>" data-confirm="<?= esc(lang('App.confirm_delete_appointment'), 'attr') ?>" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block"><?= esc(lang('App.delete')) ?></button>
</form>
<?php endif ?>

<?= $this->endSection() ?>
