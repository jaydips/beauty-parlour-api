<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$tab = static fn (string $key, string $value, string $current, string $label): string =>
    '<a class="chip' . ($current === $value ? ' on' : '') . '" href="' . site_url('app/reminders') . '?' . http_build_query([$key => $value] + ($key === 'due' ? ['status' => $status] : ['due' => $due])) . '">' . esc($label) . '</a>';
?>
<div class="hdr">
    <h1><?= esc(lang('App.title_reminders')) ?></h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/reminders/new') ?>"><?= esc(lang('App.new_n')) ?></a>
</div>

<div class="chips">
    <?= $tab('due', 'today', $due, lang('App.due_today')) ?>
    <?= $tab('due', 'overdue', $due, lang('App.overdue')) ?>
    <?= $tab('due', 'all', $due, lang('App.all_n')) ?>
</div>
<div class="chips">
    <?= $tab('status', 'pending', $status, lang('App.status_pending')) ?>
    <?= $tab('status', 'sent', $status, lang('App.tab_sent')) ?>
    <?= $tab('status', 'skipped', $status, lang('App.tab_skipped')) ?>
    <?= $tab('status', 'all', $status, lang('App.all_n')) ?>
</div>

<section class="block">
    <?php if ($reminders === []): ?>
        <p class="empty"><?= esc(lang('App.no_reminders')) ?></p>
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
