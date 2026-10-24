<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1>Payments</h1>

<form method="get" action="<?= site_url('app/payments') ?>" class="grid2 block">
    <div>
        <label for="from">થી</label>
        <input id="from" name="from" type="date" value="<?= esc($from) ?>" onchange="this.form.submit()">
    </div>
    <div>
        <label for="to">સુધી</label>
        <input id="to" name="to" type="date" value="<?= esc($to) ?>" onchange="this.form.submit()">
    </div>
</form>

<section class="stats">
    <div class="stat"><span class="num"><?= esc(rupees($totals['net'])) ?></span><span class="lbl">ચોખ્ખી આવક</span></div>
    <div class="stat"><span class="num"><?= (int) $totals['count'] ?></span><span class="lbl">Payments</span></div>
</section>
<?php if ($totals['by_method'] !== [] || $totals['refunds'] > 0): ?>
<div class="card">
    <?php foreach ($totals['by_method'] as $m => $amt): ?>
        <div class="row"><span><?= esc(method_label($m)) ?></span><span><?= esc(rupees($amt)) ?></span></div>
    <?php endforeach ?>
    <?php if ($totals['refunds'] > 0): ?>
        <div class="row"><span>Refund</span><span>- <?= esc(rupees($totals['refunds'])) ?></span></div>
    <?php endif ?>
</div>
<?php endif ?>

<section class="block">
    <?php if ($payments === []): ?>
        <p class="empty">આ ગાળામાં કોઈ payment નથી.</p>
    <?php else: ?>
        <?php foreach ($payments as $p): ?>
            <a class="card card-link" href="<?= $p['appointment_id'] ? site_url('app/appointments/' . (int) $p['appointment_id']) : site_url('app/customers/' . (int) $p['customer_id']) ?>">
                <div class="card-top">
                    <span class="card-title"><?= esc($p['customer']['name'] ?? '-') ?></span>
                    <strong class="<?= $p['type'] === 'refund' ? 'due' : '' ?>"><?= $p['type'] === 'refund' ? '- ' : '' ?><?= esc(rupees($p['amount'])) ?></strong>
                </div>
                <div class="muted"><?= esc(fmt_datetime($p['paid_at'])) ?> · <?= esc(method_label($p['method'])) ?><?= $p['reference'] ? ' · ' . esc($p['reference']) : '' ?></div>
            </a>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
