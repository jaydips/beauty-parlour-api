<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1><?= esc(lang('App.title_whatsapp')) ?></h1>
<p class="muted"><?= esc(lang('App.wa_intro')) ?></p>

<div class="card">
    <div class="card-top">
        <span class="card-title"><?= esc(lang('App.bridge')) ?></span>
        <span class="badge <?= esc($state[1]) ?>"><?= esc($state[0]) ?></span>
    </div>
    <?php if (! $configured): ?>
        <p class="msg"><?= esc(lang('App.bridge_not_configured')) ?></p>
    <?php else: ?>
        <?php if (! empty($status['me'])): ?>
            <div class="row"><span><?= esc(lang('App.linked_number')) ?></span><span>+<?= esc($status['me']) ?></span></div>
        <?php endif ?>
        <div class="row"><span><?= esc(lang('App.sent_since_start')) ?></span><span><?= (int) ($status['sent_total'] ?? 0) ?></span></div>
        <?php if (! empty($status['last_error'])): ?>
            <div class="muted"><?= esc(lang('App.last_error')) ?>: <?= esc($status['last_error']) ?></div>
        <?php elseif ($state[2] === 'down'): ?>
            <div class="muted"><?= esc(lang('App.bridge_down_note')) ?></div>
        <?php endif ?>
        <div class="actions">
            <?php if ($state[2] === 'open' || $state[2] === 'connecting'): ?>
                <form method="post" action="<?= site_url('app/whatsapp/logout') ?>" data-confirm="<?= esc(lang('App.confirm_unlink'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm"><?= esc(lang('App.unlink')) ?></button>
                </form>
            <?php endif ?>
            <?php if ($state[2] !== 'down'): ?>
                <form method="post" action="<?= site_url('app/whatsapp/restart') ?>" data-confirm="<?= esc(lang('App.confirm_restart'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= esc(lang('App.restart')) ?></button>
                </form>
            <?php endif ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('app/whatsapp') ?>"><?= esc(lang('App.refresh')) ?></a>
        </div>
    <?php endif ?>
</div>

<?php if ($configured && $state[2] === 'unlinked'): ?>
    <div class="card">
        <div class="card-title"><?= esc(lang('App.link_number')) ?></div>
        <?php if ($pairingCode): ?>
            <p class="muted"><?= esc(lang('App.pairing_instructions')) ?></p>
            <p class="total" style="text-align:center;font-size:1.8rem;letter-spacing:.25em"><?= esc($pairingCode) ?></p>
            <p class="muted"><?= esc(lang('App.pairing_code_expires')) ?></p>
        <?php endif ?>
        <form method="post" action="<?= site_url('app/whatsapp/pair') ?>">
            <?= csrf_field() ?>
            <label for="phone"><?= esc(lang('App.whatsapp_mobile_10')) ?></label>
            <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc($defaultPhone) ?>" required>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= esc($pairingCode ? lang('App.new_code') : lang('App.get_pairing_code')) ?></button>
            </div>
        </form>
        <?php if (! empty($status['qr_png'])): ?>
            <p class="muted"><?= esc(lang('App.or_scan_qr')) ?></p>
            <img src="<?= esc($status['qr_png'], 'attr') ?>" alt="WhatsApp QR" style="display:block;width:220px;max-width:100%;margin:0 auto;padding:8px;background:#fff;border-radius:8px">
        <?php endif ?>
    </div>
<?php endif ?>

<div class="card">
    <div class="card-top">
        <span class="card-title"><?= esc(lang('App.auto_send')) ?></span>
        <span class="badge <?= $autoSend ? 'badge-green' : 'badge-grey' ?>"><?= esc($autoSend ? lang('App.on') : lang('App.off')) ?></span>
    </div>
    <p class="muted"><?= esc(lang('App.auto_send_help', [$window[0], $window[1]])) ?></p>
    <?php if ($autoSend && $state[2] !== 'open' && $state[2] !== 'connecting'): ?>
        <p class="muted"><?= esc(lang('App.auto_wa_not_linked_warning')) ?></p>
    <?php endif ?>
    <form method="post" action="<?= site_url('app/whatsapp/auto-send') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="enabled" value="<?= $autoSend ? '0' : '1' ?>">
        <button type="submit" class="btn <?= $autoSend ? 'btn-ghost' : 'btn-primary' ?> btn-block"><?= esc($autoSend ? lang('App.turn_off_auto_send') : lang('App.turn_on_auto_send')) ?></button>
    </form>
</div>

<?php if ($configured): ?>
    <div class="card">
        <div class="card-title"><?= esc(lang('App.test_message')) ?></div>
        <form method="post" action="<?= site_url('app/whatsapp/test') ?>">
            <?= csrf_field() ?>
            <label for="test_phone"><?= esc(lang('App.to_which_number')) ?></label>
            <input id="test_phone" name="phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc($owner['phone'] ?? '') ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-ghost"><?= esc(lang('App.send_test')) ?></button>
            </div>
        </form>
    </div>
<?php endif ?>

<div class="chips">
    <a class="chip" href="<?= site_url('app/whatsapp/messages') ?>?status=queued"><?= esc(lang('App.queued')) ?> <?= (int) $counts['queued'] ?></a>
    <a class="chip" href="<?= site_url('app/whatsapp/messages') ?>?status=sent"><?= esc(lang('App.sent')) ?> <?= (int) $counts['sent'] ?></a>
    <a class="chip" href="<?= site_url('app/whatsapp/messages') ?>?status=failed"><?= esc(lang('App.failed')) ?> <?= (int) $counts['failed'] ?></a>
    <a class="chip" href="<?= site_url('app/campaigns') ?>"><?= esc(lang('App.offers')) ?></a>
</div>

<details class="card guide">
    <summary class="card-title"><?= esc(lang('App.guide_title')) ?></summary>
    <ol>
        <?php foreach (lang('App.guide_steps') as $step): ?>
            <li><?= esc($step) ?></li>
        <?php endforeach ?>
    </ol>
</details>

<section class="block">
    <div class="block-head"><h2><?= esc(lang('App.recent_messages')) ?></h2><a href="<?= site_url('app/whatsapp/messages') ?>"><?= esc(lang('App.all')) ?> &#8250;</a></div>
    <?php if ($recent === []): ?>
        <p class="empty"><?= esc(lang('App.no_messages_yet')) ?></p>
    <?php else: ?>
        <?php foreach ($recent as $m): ?>
            <?= view('web/partials/message_card', ['m' => $m]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
