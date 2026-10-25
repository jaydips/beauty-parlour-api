<?php /** @var array $r reminder row with customer + wa_link */ ?>
<div class="card">
    <div class="card-top">
        <span class="card-title"><?= esc($r['customer']['name'] ?? '-') ?></span>
        <span class="badge badge-amber"><?= esc(reminder_type_label($r['type'])) ?></span>
    </div>
    <div class="muted"><?= esc(fmt_datetime($r['due_at'])) ?> · <?= esc($r['customer']['phone'] ?? '') ?></div>
    <p class="msg"><?= esc(mb_strimwidth((string) $r['message'], 0, 140, '…')) ?></p>
    <div class="actions">
        <?php if (! empty($r['wa_link'])): ?>
            <a class="btn btn-wa" href="<?= esc($r['wa_link'], 'attr') ?>" target="_blank" rel="noopener"><?= esc(lang('App.whatsapp')) ?></a>
        <?php endif ?>
        <form method="post" action="<?= site_url('app/reminders/' . (int) $r['id'] . '/sent') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"><?= esc(lang('App.mark_sent')) ?></button>
        </form>
        <form method="post" action="<?= site_url('app/reminders/' . (int) $r['id'] . '/skip') ?>" data-confirm="<?= esc(lang('App.confirm_skip_reminder'), 'attr') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost"><?= esc(lang('App.skip')) ?></button>
        </form>
    </div>
</div>
