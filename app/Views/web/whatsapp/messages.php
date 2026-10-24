<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$tab = static fn (string $value, string $label): string =>
    '<a class="chip' . ($status === $value ? ' on' : '') . '" href="' . site_url('app/whatsapp/messages') . '?status=' . $value . '">' . esc($label) . '</a>';
?>
<div class="hdr">
    <h1>WhatsApp outbox</h1>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('app/whatsapp') ?>">Bridge</a>
</div>

<div class="chips">
    <?= $tab('all', 'બધા') ?>
    <?= $tab('queued', 'કતારમાં ' . (int) $counts['queued']) ?>
    <?= $tab('sent', 'મોકલાયાં ' . (int) $counts['sent']) ?>
    <?= $tab('failed', 'નિષ્ફળ ' . (int) $counts['failed']) ?>
    <?= $tab('cancelled', 'રદ ' . (int) $counts['cancelled']) ?>
</div>

<section class="block">
    <?php if ($messages === []): ?>
        <p class="empty">કોઈ message નથી.</p>
    <?php else: ?>
        <?php foreach ($messages as $m): ?>
            <?= view('web/partials/message_card', ['m' => $m]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
