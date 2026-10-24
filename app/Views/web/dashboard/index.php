<?php
/** @var array $d dashboard data  @var string $date */
$today    = \CodeIgniter\I18n\Time::now()->toDateString();
$isToday  = $date === $today;
$prev     = \CodeIgniter\I18n\Time::parse($date)->subDays(1)->toDateString();
$next     = \CodeIgniter\I18n\Time::parse($date)->addDays(1)->toDateString();
$counts   = $d['appointments']['counts'];
$return   = current_url();
?>
<section class="day-nav">
    <a class="btn btn-ghost icon" href="<?= site_url('app?date=' . $prev) ?>" aria-label="પહેલાંનો દિવસ">‹</a>
    <form method="get" action="<?= site_url('app') ?>" class="grow center">
        <strong class="day-title"><?= esc(day_title($date)) ?></strong>
        <input type="date" name="date" value="<?= esc($date, 'attr') ?>" class="date-inline" data-autosubmit aria-label="તારીખ પસંદ કરો">
    </form>
    <a class="btn btn-ghost icon" href="<?= site_url('app?date=' . $next) ?>" aria-label="પછીનો દિવસ">›</a>
</section>

<section class="tiles">
    <a class="tile" href="<?= site_url('app/appointments?date=' . $date) ?>">
        <span class="tile-label"><?= $isToday ? 'આજની' : 'આ દિવસની' ?> અપોઇન્ટમેન્ટ</span>
        <span class="tile-value"><?= (int) $counts['total'] ?></span>
        <span class="tile-sub"><?= (int) $counts['completed'] ?> પૂર્ણ · <?= (int) ($counts['scheduled'] + $counts['confirmed']) ?> બાકી</span>
    </a>
    <a class="tile" href="<?= site_url('app/payments?from=' . $date . '&to=' . $date) ?>">
        <span class="tile-label"><?= $isToday ? 'આજની' : 'આ દિવસની' ?> આવક</span>
        <span class="tile-value"><?= esc(money($d['revenue']['today']['net'])) ?></span>
        <span class="tile-sub"><?= (int) $d['revenue']['today']['count'] ?> પેમેન્ટ</span>
    </a>
    <a class="tile" href="<?= site_url('app/payments?from=' . substr($date, 0, 7) . '-01&to=' . $date) ?>">
        <span class="tile-label">આ મહિનાની આવક</span>
        <span class="tile-value"><?= esc(money($d['revenue']['month']['net'])) ?></span>
        <span class="tile-sub"><?= (int) $d['revenue']['month']['count'] ?> પેમેન્ટ</span>
    </a>
    <a class="tile<?= $d['dues']['count'] > 0 ? ' tile-warn' : '' ?>" href="#dues">
        <span class="tile-label">બાકી રકમ</span>
        <span class="tile-value"><?= esc(money($d['dues']['total'])) ?></span>
        <span class="tile-sub"><?= (int) $d['dues']['count'] ?> અપોઇન્ટમેન્ટ</span>
    </a>
</section>

<section class="quick-actions">
    <a class="btn btn-primary" href="<?= site_url('app/appointments/new' . ($isToday ? '' : '?date=' . $date)) ?>">+ નવી અપોઇન્ટમેન્ટ</a>
    <a class="btn btn-outline" href="<?= site_url('app/customers/new') ?>">+ નવો ગ્રાહક</a>
    <a class="btn btn-outline" href="<?= site_url('app/payments/new') ?>">+ પેમેન્ટ</a>
</section>

<section class="block">
    <div class="block-head">
        <h2><?= $isToday ? 'આજની' : 'આ દિવસની' ?> અપોઇન્ટમેન્ટ</h2>
        <a class="link" href="<?= site_url('app/appointments?date=' . $date) ?>">બધી જુઓ ›</a>
    </div>
    <?php if ($d['appointments']['list'] === []): ?>
        <div class="empty">આ દિવસે કોઈ અપોઇન્ટમેન્ટ નથી.</div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($d['appointments']['list'] as $a): ?>
                <?= view('web/partials/appointment_card', ['a' => $a]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($isToday): ?>
        <p class="muted small">આવતા 7 દિવસમાં <?= (int) $d['upcoming_7_days'] ?> અપોઇન્ટમેન્ટ છે.</p>
    <?php endif; ?>
</section>

<section class="block" id="reminders">
    <div class="block-head">
        <h2>WhatsApp રિમાઇન્ડર <span class="count"><?= (int) $d['reminders_due']['count'] ?></span></h2>
        <a class="link" href="<?= site_url('app/reminders') ?>">બધા જુઓ ›</a>
    </div>
    <?php if ($d['reminders_due']['list'] === []): ?>
        <div class="empty">કોઈ રિમાઇન્ડર બાકી નથી. 🎉</div>
    <?php else: ?>
        <p class="muted small">WhatsApp બટન દબાવો, મેસેજ મોકલો, પછી "મોકલાઈ ગયું" દબાવો.</p>
        <div class="list">
            <?php foreach ($d['reminders_due']['list'] as $r): ?>
                <?= view('web/partials/reminder_card', ['r' => $r, 'return' => $return]) ?>
            <?php endforeach; ?>
        </div>
        <?php if ($d['reminders_due']['count'] > count($d['reminders_due']['list'])): ?>
            <a class="btn btn-ghost btn-block" href="<?= site_url('app/reminders') ?>">બાકીના <?= (int) $d['reminders_due']['count'] - count($d['reminders_due']['list']) ?> જુઓ</a>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="block" id="dues">
    <div class="block-head">
        <h2>બાકી રકમ <span class="count"><?= (int) $d['dues']['count'] ?></span></h2>
    </div>
    <?php if ($d['dues']['list'] === []): ?>
        <div class="empty">કોઈ બાકી રકમ નથી.</div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($d['dues']['list'] as $due): ?>
                <a class="card row-between" href="<?= site_url('app/appointments/' . (int) $due['appointment_id']) ?>">
                    <div>
                        <div class="card-title"><?= esc($due['customer']['name'] ?? 'ગ્રાહક') ?></div>
                        <div class="small muted"><?= esc(fmt_dt($due['starts_at'])) ?> · કુલ <?= esc(money($due['total'])) ?> · ચૂકવેલ <?= esc(money($due['paid'])) ?></div>
                    </div>
                    <div class="text-danger strong"><?= esc(money($due['balance'])) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<p class="muted small center">ગ્રાહકો: <?= (int) $d['counts']['customers'] ?> · સક્રિય સર્વિસ: <?= (int) $d['counts']['active_services'] ?></p>
