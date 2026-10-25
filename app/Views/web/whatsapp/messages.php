<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$tab = static fn (string $value, string $label): string =>
    '<a class="chip' . ($status === $value ? ' on' : '') . '" href="' . site_url('app/whatsapp/messages') . '?status=' . $value . '">' . esc($label) . '</a>';
?>
<div class="hdr">
    <h1><?= esc(lang('App.title_outbox')) ?></h1>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('app/whatsapp') ?>"><?= esc(lang('App.bridge')) ?></a>
</div>

<div class="chips">
    <?= $tab('all', lang('App.all')) ?>
    <?= $tab('queued', lang('App.queued') . ' ' . (int) $counts['queued']) ?>
    <?= $tab('sent', lang('App.sent') . ' ' . (int) $counts['sent']) ?>
    <?= $tab('failed', lang('App.failed') . ' ' . (int) $counts['failed']) ?>
    <?= $tab('cancelled', lang('App.cancelled') . ' ' . (int) $counts['cancelled']) ?>
</div>

<section class="block">
    <?php if ($messages === []): ?>
        <p class="empty"><?= esc(lang('App.no_messages')) ?></p>
    <?php else: ?>
        <?php foreach ($messages as $m): ?>
            <?= view('web/partials/message_card', ['m' => $m]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
