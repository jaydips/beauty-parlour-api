<?php /** @var array $m outbox message row with customer */ ?>
<div class="card">
    <div class="card-top">
        <span class="card-title"><?= esc($m['customer']['name'] ?? $m['phone']) ?></span>
        <span class="badge <?= esc(message_status_class($m['status'])) ?>"><?= esc(message_status_label($m['status'])) ?></span>
    </div>
    <div class="muted">
        <?= esc(message_type_label($m['type'])) ?> · <?= esc($m['phone']) ?> ·
        <?php if ($m['status'] === 'sent'): ?>
            <?= esc(lang('App.sent_at')) ?> <?= esc(fmt_datetime($m['sent_at'])) ?>
        <?php else: ?>
            <?= esc(lang('App.scheduled_for')) ?> <?= esc(fmt_datetime($m['scheduled_at'])) ?>
        <?php endif ?>
    </div>
    <p class="msg"><?= esc(mb_strimwidth((string) $m['body'], 0, 160, '…')) ?></p>
    <?php if (! empty($m['last_error'])): ?>
        <div class="muted"><?= esc(lang('App.error')) ?>: <?= esc($m['last_error']) ?> (<?= esc(lang('App.attempt')) ?> <?= (int) $m['attempts'] ?>)</div>
    <?php endif ?>
    <?php if ($m['status'] === 'queued'): ?>
        <div class="actions">
            <form method="post" action="<?= site_url('app/whatsapp/messages/' . (int) $m['id'] . '/cancel') ?>" data-confirm="<?= esc(lang('App.confirm_cancel_message'), 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-sm"><?= esc(lang('App.cancel_action')) ?></button>
            </form>
        </div>
    <?php elseif ($m['status'] === 'failed' || $m['status'] === 'cancelled'): ?>
        <div class="actions">
            <form method="post" action="<?= site_url('app/whatsapp/messages/' . (int) $m['id'] . '/retry') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm"><?= esc(lang('App.retry')) ?></button>
            </form>
        </div>
    <?php endif ?>
</div>
