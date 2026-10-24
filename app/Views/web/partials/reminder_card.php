<?php
/**
 * One reminder with WhatsApp (click-to-chat), "sent" and "skip" buttons.
 * Variables: $r (decorated reminder row), $return (URL to come back to after a status change)
 */
$return ??= current_url();
$customer = $r['customer'] ?? null;
$pending  = $r['status'] === 'pending';
$overdue  = $pending && $r['due_at'] < \CodeIgniter\I18n\Time::now()->toDateTimeString();
?>
<article class="card reminder<?= $pending ? '' : ' is-done' ?>" id="reminder-<?= (int) $r['id'] ?>">
    <div class="row-between">
        <div class="grow">
            <div class="card-title">
                <?php if ($customer): ?>
                    <a href="<?= site_url('app/customers/' . (int) $customer['id']) ?>"><?= esc($customer['name']) ?></a>
                    <span class="muted small"><?= esc($customer['phone']) ?></span>
                <?php else: ?>
                    <span class="muted">ગ્રાહક મળ્યો નહીં</span>
                <?php endif; ?>
            </div>
            <div class="muted small">
                <?= esc(reminder_type_label($r['type'])) ?>
                · <?= $overdue ? '<span class="text-danger">' : '' ?><?= esc(fmt_dt($r['due_at'])) ?><?= $overdue ? '</span>' : '' ?>
                <?php if (! empty($r['appointment'])): ?>
                    · <a href="<?= site_url('app/appointments/' . (int) $r['appointment']['id']) ?>">અપોઇન્ટમેન્ટ <?= esc(fmt_dt($r['appointment']['starts_at'])) ?></a>
                <?php endif; ?>
            </div>
        </div>
        <span class="badge badge-rem-<?= esc($r['status'], 'attr') ?>"><?= esc(reminder_status_label($r['status'])) ?></span>
    </div>

    <p class="reminder-msg"><?= nl2br(esc($r['message'])) ?></p>

    <?php if ($pending && $customer): ?>
        <div class="actions">
            <a class="btn btn-wa" href="<?= esc($r['wa_link']) ?>" target="_blank" rel="noopener" data-wa-open="reminder-<?= (int) $r['id'] ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.6.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4 5.3 5.3 0 0 0 3.2.7 2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>
                WhatsApp પર મોકલો
            </a>
            <form method="post" action="<?= site_url('app/reminders/' . (int) $r['id'] . '/status') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="sent">
                <input type="hidden" name="return" value="<?= esc($return) ?>">
                <button type="submit" class="btn btn-outline" data-sent-btn>✓ મોકલાઈ ગયું</button>
            </form>
            <form method="post" action="<?= site_url('app/reminders/' . (int) $r['id'] . '/status') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="skipped">
                <input type="hidden" name="return" value="<?= esc($return) ?>">
                <button type="submit" class="btn btn-ghost">છોડો</button>
            </form>
        </div>
    <?php elseif (! $pending): ?>
        <div class="actions">
            <form method="post" action="<?= site_url('app/reminders/' . (int) $r['id'] . '/status') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="pending">
                <input type="hidden" name="return" value="<?= esc($return) ?>">
                <button type="submit" class="btn btn-ghost small">ફરી બાકીમાં મૂકો</button>
            </form>
        </div>
    <?php endif; ?>
</article>
