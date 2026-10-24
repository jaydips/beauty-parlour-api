<?php
/**
 * Compact appointment row for lists. Variables: $a (appointment with customer + items), $showDate (bool)
 */
$showDate ??= false;
$customer = $a['customer'] ?? null;
?>
<a class="card appt appt-<?= esc($a['status'], 'attr') ?>" href="<?= site_url('app/appointments/' . (int) $a['id']) ?>">
    <div class="appt-time">
        <?php if ($showDate): ?><div class="small muted"><?= esc(fmt_date($a['starts_at'])) ?></div><?php endif; ?>
        <div class="appt-start"><?= esc(fmt_time($a['starts_at'])) ?></div>
        <div class="small muted">– <?= esc(fmt_time($a['ends_at'])) ?></div>
    </div>
    <div class="grow">
        <div class="card-title"><?= esc($customer['name'] ?? 'ગ્રાહક') ?></div>
        <div class="small muted"><?= esc(items_summary($a['items'] ?? [])) ?: '<em>કોઈ સર્વિસ નથી</em>' ?></div>
        <div class="row gap small">
            <?= status_badge($a['status']) ?>
            <?php if (! empty($a['source'])): ?><span class="muted"><?= esc(source_label($a['source'])) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="appt-total"><?= esc(money($a['total'])) ?></div>
</a>
