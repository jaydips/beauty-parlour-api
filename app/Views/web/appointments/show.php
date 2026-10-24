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
        <a class="btn btn-ghost btn-sm" href="tel:<?= esc($customer['phone'], 'attr') ?>">Call</a>
        <a class="btn btn-wa btn-sm" href="<?= esc(service('whatsapp')->link($customer['phone'], ''), 'attr') ?>" target="_blank" rel="noopener">WhatsApp</a>
    </div>
</div>
<?php endif ?>

<div class="card">
    <?php foreach ($a['items'] as $item): ?>
        <div class="row">
            <span><?= esc($item['service_name']) ?><?= (int) $item['quantity'] > 1 ? ' ×' . (int) $item['quantity'] : '' ?> <small class="muted"><?= (int) $item['duration_minutes'] ?> min</small></span>
            <span><?= esc(rupees((int) $item['price'] * (int) $item['quantity'])) ?></span>
        </div>
    <?php endforeach ?>
    <?php if ((int) $a['discount'] > 0): ?>
        <div class="row sub"><span>Discount</span><span>- <?= esc(rupees($a['discount'])) ?></span></div>
    <?php endif ?>
    <div class="row"><strong>કુલ</strong><strong><?= esc(rupees($a['total'])) ?></strong></div>
    <div class="row sub"><span>ચૂકવ્યા</span><span><?= esc(rupees($a['paid'])) ?></span></div>
    <div class="row"><span>બાકી</span><strong class="<?= $a['balance'] > 0 ? 'due' : '' ?>"><?= esc(rupees($a['balance'])) ?></strong></div>
    <?php if (! empty($a['notes'])): ?><p class="msg"><?= esc($a['notes']) ?></p><?php endif ?>
</div>

<?php if ($open): ?>
<div class="actions">
    <?php if ($a['status'] === 'scheduled'): ?>
    <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>"><?= csrf_field() ?><input type="hidden" name="status" value="confirmed"><button class="btn btn-ghost">Confirm</button></form>
    <?php endif ?>
    <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>"><?= csrf_field() ?><input type="hidden" name="status" value="completed"><button class="btn btn-primary">પૂરું થયું</button></form>
    <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>" data-confirm="Customer ન આવ્યા તરીકે નોંધવું?"><?= csrf_field() ?><input type="hidden" name="status" value="no_show"><button class="btn btn-ghost">ન આવ્યા</button></form>
    <a class="btn btn-ghost" href="<?= site_url("app/appointments/{$id}/edit") ?>">સુધારો</a>
</div>
<form class="card inline-form" method="post" action="<?= site_url("app/appointments/{$id}/status") ?>" data-confirm="Appointment રદ કરવી?">
    <?= csrf_field() ?>
    <input type="hidden" name="status" value="cancelled">
    <label for="reason">રદ કરવાનું કારણ (વૈકલ્પિક)</label>
    <div class="inline">
        <input id="reason" name="reason" type="text" maxlength="255" placeholder="કારણ">
        <button class="btn btn-danger">રદ કરો</button>
    </div>
</form>
<?php elseif (in_array($a['status'], ['cancelled', 'no_show'], true)): ?>
<div class="card">
    <div class="muted"><?= esc(status_label($a['status'])) ?><?= $a['cancel_reason'] ? ' · ' . esc($a['cancel_reason']) : '' ?></div>
    <div class="actions">
        <form method="post" action="<?= site_url("app/appointments/{$id}/status") ?>"><?= csrf_field() ?><input type="hidden" name="status" value="scheduled"><button class="btn btn-ghost btn-sm">ફરી નક્કી કરો</button></form>
    </div>
</div>
<?php endif ?>

<section class="block">
    <div class="block-head"><h2>Payments</h2></div>
    <?php foreach ($a['payments'] as $p): ?>
        <div class="card">
            <div class="row">
                <span><?= esc(method_label($p['method'])) ?> <small class="muted"><?= esc(fmt_datetime($p['paid_at'])) ?><?= $p['reference'] ? ' · ' . esc($p['reference']) : '' ?></small></span>
                <strong><?= $p['type'] === 'refund' ? '- ' : '' ?><?= esc(rupees($p['amount'])) ?></strong>
            </div>
            <form method="post" action="<?= site_url('app/payments/' . (int) $p['id'] . '/delete') ?>" data-confirm="આ payment દૂર કરવું?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">દૂર કરો</button></form>
        </div>
    <?php endforeach ?>
    <form class="card" method="post" action="<?= site_url('app/payments') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="appointment_id" value="<?= $id ?>">
        <div class="grid2">
            <div>
                <label for="amount">રકમ (₹)</label>
                <input id="amount" name="amount" type="number" inputmode="numeric" min="1" step="1" value="<?= $a['balance'] > 0 ? (int) $a['balance'] : '' ?>" required>
            </div>
            <div>
                <label for="method">Method</label>
                <select id="method" name="method">
                    <option value="cash">Cash</option>
                    <option value="upi" selected>UPI</option>
                    <option value="card">Card</option>
                    <option value="other">બીજું</option>
                </select>
            </div>
        </div>
        <label for="reference">Reference (UPI id, વૈકલ્પિક)</label>
        <input id="reference" name="reference" type="text" maxlength="100">
        <label class="check"><input type="checkbox" name="type" value="refund"> આ refund છે</label>
        <button class="btn btn-primary btn-block">Payment નોંધો</button>
    </form>
</section>

<section class="block">
    <div class="block-head"><h2>Reminders</h2></div>
    <?php if ($a['reminders'] === []): ?>
        <p class="empty">કોઈ reminder નથી.</p>
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
<form method="post" action="<?= site_url("app/appointments/{$id}/delete") ?>" data-confirm="આ appointment કાયમ માટે delete કરવી?" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block">Delete</button>
</form>
<?php endif ?>

<?= $this->endSection() ?>
