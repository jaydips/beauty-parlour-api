<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $id = (int) $c['id']; ?>

<div class="hdr">
    <div>
        <h1><?= esc($c['name']) ?></h1>
        <div class="muted"><?= esc($c['phone']) ?><?= $c['alt_phone'] ? ' · ' . esc($c['alt_phone']) : '' ?></div>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= site_url("app/customers/{$id}/edit") ?>">સુધારો</a>
</div>

<div class="actions">
    <a class="btn btn-primary" href="<?= site_url('app/appointments/new') ?>?customer_id=<?= $id ?>">+ Appointment</a>
    <a class="btn btn-wa" href="<?= esc($wa_link, 'attr') ?>" target="_blank" rel="noopener">WhatsApp</a>
    <a class="btn btn-ghost" href="tel:<?= esc($c['phone'], 'attr') ?>">Call</a>
</div>

<section class="stats">
    <div class="stat"><span class="num"><?= (int) $stats['visits'] ?></span><span class="lbl">Visits</span></div>
    <div class="stat"><span class="num"><?= esc(rupees($stats['total_spent'])) ?></span><span class="lbl">કુલ ખર્ચ</span></div>
</section>

<div class="card">
    <div class="row"><span>છેલ્લી visit</span><span><?= $stats['last_visit_at'] ? esc(fmt_date($stats['last_visit_at'])) : '-' ?></span></div>
    <div class="row"><span>જન્મદિવસ</span><span><?= $c['birthday'] ? esc(fmt_date($c['birthday'])) : '-' ?></span></div>
    <div class="row"><span>WhatsApp reminders</span><span><?= (int) $c['whatsapp_opt_in'] === 1 ? 'હા' : 'ના' ?></span></div>
    <?php if (! empty($c['notes'])): ?><p class="msg"><?= esc($c['notes']) ?></p><?php endif ?>
</div>

<section class="block">
    <div class="block-head"><h2>Appointments</h2></div>
    <?php if ($recent === []): ?>
        <p class="empty">હજુ કોઈ appointment નથી.</p>
    <?php else: ?>
        <?php foreach ($recent as $a): ?>
            <a class="card card-link" href="<?= site_url('app/appointments/' . (int) $a['id']) ?>">
                <div class="card-top">
                    <span><?= esc(fmt_datetime($a['starts_at'])) ?></span>
                    <span class="badge <?= esc(status_class($a['status'])) ?>"><?= esc(status_label($a['status'])) ?></span>
                </div>
                <div class="card-bottom">
                    <span class="muted"><?= esc(implode(', ', array_map(static fn (array $i): string => $i['service_name'], $a['items'] ?? []))) ?></span>
                    <strong><?= esc(rupees($a['total'])) ?></strong>
                </div>
            </a>
        <?php endforeach ?>
    <?php endif ?>
</section>

<form method="post" action="<?= site_url("app/customers/{$id}/delete") ?>" data-confirm="આ customer દૂર કરવો? (history સચવાશે)" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block">Customer દૂર કરો</button>
</form>

<?= $this->endSection() ?>
