<?php
/** @var array $a appointment row with customer + items */
$services = implode(', ', array_map(static fn (array $i): string => $i['service_name'] . ((int) $i['quantity'] > 1 ? ' ×' . (int) $i['quantity'] : ''), $a['items'] ?? []));
?>
<a class="card card-link" href="<?= site_url('app/appointments/' . (int) $a['id']) ?>">
    <div class="card-top">
        <span class="time"><?= esc(fmt_time($a['starts_at'])) ?></span>
        <span class="badge <?= esc(status_class($a['status'])) ?>"><?= esc(status_label($a['status'])) ?></span>
    </div>
    <div class="card-title"><?= esc($a['customer']['name'] ?? '-') ?></div>
    <div class="muted"><?= esc($services !== '' ? $services : '-') ?></div>
    <div class="card-bottom">
        <span class="muted"><?= esc(fmt_time($a['starts_at'])) ?> – <?= esc(fmt_time($a['ends_at'])) ?></span>
        <strong><?= esc(rupees($a['total'])) ?></strong>
    </div>
</a>
