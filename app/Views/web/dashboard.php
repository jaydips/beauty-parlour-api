<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<section class="daynav">
    <a class="btn btn-ghost" href="<?= site_url('app') ?>?date=<?= esc($prev) ?>" aria-label="આગલો દિવસ">&#8249;</a>
    <h1><?= esc(fmt_day_title($date)) ?></h1>
    <a class="btn btn-ghost" href="<?= site_url('app') ?>?date=<?= esc($next) ?>" aria-label="પછીનો દિવસ">&#8250;</a>
</section>

<section class="stats">
    <div class="stat"><span class="num"><?= (int) $d['appointments']['counts']['total'] ?></span><span class="lbl">Appointments</span></div>
    <div class="stat"><span class="num"><?= (int) $d['reminders_due']['count'] ?></span><span class="lbl">Reminders બાકી</span></div>
    <div class="stat"><span class="num"><?= esc(rupees($d['revenue']['today']['net'])) ?></span><span class="lbl">આજની આવક</span></div>
    <div class="stat"><span class="num"><?= esc(rupees($d['revenue']['month']['net'])) ?></span><span class="lbl">આ મહિને</span></div>
</section>

<section class="block">
    <div class="block-head">
        <h2>Appointments</h2>
        <a class="btn btn-primary btn-sm" href="<?= site_url('app/appointments/new') ?>?date=<?= esc($date) ?>">+ નવી</a>
    </div>
    <?php if ($d['appointments']['list'] === []): ?>
        <p class="empty">આ દિવસે કોઈ appointment નથી.</p>
    <?php else: ?>
        <?php foreach ($d['appointments']['list'] as $a): ?>
            <?= view('web/partials/appointment_card', ['a' => $a]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<section class="block">
    <div class="block-head">
        <h2>WhatsApp reminders</h2>
        <span class="muted"><?= (int) $d['reminders_due']['count'] ?> બાકી</span>
    </div>
    <?php if ($d['reminders_due']['list'] === []): ?>
        <p class="empty">હમણાં કોઈ reminder બાકી નથી.</p>
    <?php else: ?>
        <?php foreach ($d['reminders_due']['list'] as $r): ?>
            <?= view('web/partials/reminder_card', ['r' => $r]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<section class="block">
    <div class="block-head"><h2>આવક</h2></div>
    <div class="card">
        <div class="row"><span>આજે</span><strong><?= esc(rupees($d['revenue']['today']['net'])) ?></strong></div>
        <?php foreach ($d['revenue']['today']['by_method'] as $m => $amt): ?>
            <div class="row sub"><span><?= esc(method_label($m)) ?></span><span><?= esc(rupees($amt)) ?></span></div>
        <?php endforeach ?>
        <div class="row"><span>આ મહિને</span><strong><?= esc(rupees($d['revenue']['month']['net'])) ?></strong></div>
        <?php if ($d['revenue']['month']['refunds'] > 0): ?>
            <div class="row sub"><span>Refund</span><span>- <?= esc(rupees($d['revenue']['month']['refunds'])) ?></span></div>
        <?php endif ?>
    </div>
</section>

<section class="block">
    <div class="block-head">
        <h2>બાકી રકમ</h2>
        <strong><?= esc(rupees($d['dues']['total'])) ?></strong>
    </div>
    <?php if ($d['dues']['list'] === []): ?>
        <p class="empty">કોઈ બાકી નથી.</p>
    <?php else: ?>
        <div class="card">
            <?php foreach (array_slice($d['dues']['list'], 0, 5) as $due): ?>
                <div class="row">
                    <span><?= esc($due['customer']['name'] ?? '-') ?> <small class="muted"><?= esc(fmt_date($due['starts_at'])) ?></small></span>
                    <strong><?= esc(rupees($due['balance'])) ?></strong>
                </div>
            <?php endforeach ?>
            <?php if ($d['dues']['count'] > 5): ?>
                <div class="row sub"><span class="muted">+ <?= (int) $d['dues']['count'] - 5 ?> વધુ</span></div>
            <?php endif ?>
        </div>
    <?php endif ?>
</section>

<section class="stats">
    <div class="stat"><span class="num"><?= (int) $d['upcoming_7_days'] ?></span><span class="lbl">આવતા 7 દિવસ</span></div>
    <div class="stat"><span class="num"><?= (int) $d['counts']['customers'] ?></span><span class="lbl">Customers</span></div>
</section>

<?= $this->endSection() ?>
