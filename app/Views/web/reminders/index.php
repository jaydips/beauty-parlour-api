<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$tab = static fn (string $key, string $value, string $current, string $label): string =>
    '<a class="chip' . ($current === $value ? ' on' : '') . '" href="' . site_url('app/reminders') . '?' . http_build_query([$key => $value] + ($key === 'due' ? ['status' => $status] : ['due' => $due])) . '">' . esc($label) . '</a>';
?>
<div class="hdr">
    <h1>Reminders</h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/reminders/new') ?>">+ નવું</a>
</div>

<div class="chips">
    <?= $tab('due', 'today', $due, 'આજ સુધીનાં') ?>
    <?= $tab('due', 'overdue', $due, 'મોડાં') ?>
    <?= $tab('due', 'all', $due, 'બધાં') ?>
</div>
<div class="chips">
    <?= $tab('status', 'pending', $status, 'બાકી') ?>
    <?= $tab('status', 'sent', $status, 'મોકલ્યાં') ?>
    <?= $tab('status', 'skipped', $status, 'છોડ્યાં') ?>
    <?= $tab('status', 'all', $status, 'બધાં') ?>
</div>

<section class="block">
    <?php if ($reminders === []): ?>
        <p class="empty">કોઈ reminder નથી.</p>
    <?php else: ?>
        <?php foreach ($reminders as $r): ?>
            <?php if ($r['status'] === 'pending'): ?>
                <?= view('web/partials/reminder_card', ['r' => $r]) ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-top">
                        <span class="card-title"><?= esc($r['customer']['name'] ?? '-') ?></span>
                        <span class="badge <?= esc(status_class($r['status'])) ?>"><?= esc(status_label($r['status'])) ?></span>
                    </div>
                    <div class="muted"><?= esc(reminder_type_label($r['type'])) ?> · <?= esc(fmt_datetime($r['due_at'])) ?></div>
                </div>
            <?php endif ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
