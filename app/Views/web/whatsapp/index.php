<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1>WhatsApp auto-send</h1>
<p class="muted">Booking confirmation, reminders, follow-up, જન્મદિવસ અને offers આપમેળે મોકલવા માટે parlour નો WhatsApp નંબર link કરો. Bridge unofficial (Baileys) છે: parlour માટે અલગ નંબર વાપરવો સારો.</p>

<div class="card">
    <div class="card-top">
        <span class="card-title">Bridge</span>
        <span class="badge <?= esc($state[1]) ?>"><?= esc($state[0]) ?></span>
    </div>
    <?php if (! $configured): ?>
        <p class="msg">Server પર bridge configure થયું નથી (<code>whatsapp.bridgeKey</code> ખાલી છે). Setup: <code>docs/whatsapp.md</code>.</p>
    <?php else: ?>
        <?php if (! empty($status['me'])): ?>
            <div class="row"><span>Linked નંબર</span><span>+<?= esc($status['me']) ?></span></div>
        <?php endif ?>
        <div class="row"><span>મોકલેલા (bridge શરૂ થયા પછી)</span><span><?= (int) ($status['sent_total'] ?? 0) ?></span></div>
        <?php if (! empty($status['last_error'])): ?>
            <div class="muted">છેલ્લી ભૂલ: <?= esc($status['last_error']) ?></div>
        <?php elseif ($state[2] === 'down'): ?>
            <div class="muted">Bridge process ચાલતું નથી; cron watchdog 1 મિનિટમાં ફરી ચાલુ કરશે.</div>
        <?php endif ?>
        <div class="actions">
            <?php if ($state[2] === 'open' || $state[2] === 'connecting'): ?>
                <form method="post" action="<?= site_url('app/whatsapp/logout') ?>" data-confirm="WhatsApp નંબર unlink કરવો? ફરી link કરવું પડશે.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Unlink</button>
                </form>
            <?php endif ?>
            <?php if ($state[2] !== 'down'): ?>
                <form method="post" action="<?= site_url('app/whatsapp/restart') ?>" data-confirm="Bridge restart કરવું?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Restart</button>
                </form>
            <?php endif ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('app/whatsapp') ?>">Refresh</a>
        </div>
    <?php endif ?>
</div>

<?php if ($configured && $state[2] === 'unlinked'): ?>
    <div class="card">
        <div class="card-title">WhatsApp નંબર link કરો</div>
        <?php if ($pairingCode): ?>
            <p class="muted">ફોનમાં WhatsApp → Settings → Linked devices → Link a device → <strong>Link with phone number instead</strong> → આ code નાખો:</p>
            <p class="total" style="text-align:center;font-size:1.8rem;letter-spacing:.25em"><?= esc($pairingCode) ?></p>
            <p class="muted">Code થોડી મિનિટ ચાલે છે. Link થયા પછી આ page refresh કરો.</p>
        <?php endif ?>
        <form method="post" action="<?= site_url('app/whatsapp/pair') ?>">
            <?= csrf_field() ?>
            <label for="phone">WhatsApp વાળો mobile નંબર (10 અંક)</label>
            <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc($defaultPhone) ?>" required>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $pairingCode ? 'નવો code મેળવો' : 'Pairing code મેળવો' ?></button>
            </div>
        </form>
        <?php if (! empty($status['qr_png'])): ?>
            <p class="muted">અથવા બીજા ફોન/PC પરથી QR scan કરો (Linked devices → Link a device):</p>
            <img src="<?= esc($status['qr_png'], 'attr') ?>" alt="WhatsApp QR" style="display:block;width:220px;max-width:100%;margin:0 auto;padding:8px;background:#fff;border-radius:8px">
        <?php endif ?>
    </div>
<?php endif ?>

<div class="card">
    <div class="card-top">
        <span class="card-title">Auto-send</span>
        <span class="badge <?= $autoSend ? 'badge-green' : 'badge-grey' ?>"><?= $autoSend ? 'ચાલુ' : 'બંધ' ?></span>
    </div>
    <p class="muted">ચાલુ હોય ત્યારે due reminders, booking confirmation અને offers cron દ્વારા <?= esc($window[0]) ?>–<?= esc($window[1]) ?> વચ્ચે આપમેળે મોકલાય છે (Settings માં સમય બદલો). બંધ હોય ત્યારે reminders માં WhatsApp button થી જાતે મોકલો.</p>
    <form method="post" action="<?= site_url('app/whatsapp/auto-send') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="enabled" value="<?= $autoSend ? '0' : '1' ?>">
        <button type="submit" class="btn <?= $autoSend ? 'btn-ghost' : 'btn-primary' ?> btn-block"><?= $autoSend ? 'Auto-send બંધ કરો' : 'Auto-send ચાલુ કરો' ?></button>
    </form>
</div>

<?php if ($configured): ?>
    <div class="card">
        <div class="card-title">Test message</div>
        <form method="post" action="<?= site_url('app/whatsapp/test') ?>">
            <?= csrf_field() ?>
            <label for="test_phone">કયા નંબર પર (10 અંક)</label>
            <input id="test_phone" name="phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc($owner['phone'] ?? '') ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-ghost">Test મોકલો</button>
            </div>
        </form>
    </div>
<?php endif ?>

<div class="chips">
    <a class="chip" href="<?= site_url('app/whatsapp/messages') ?>?status=queued">કતારમાં <?= (int) $counts['queued'] ?></a>
    <a class="chip" href="<?= site_url('app/whatsapp/messages') ?>?status=sent">મોકલાયાં <?= (int) $counts['sent'] ?></a>
    <a class="chip" href="<?= site_url('app/whatsapp/messages') ?>?status=failed">નિષ્ફળ <?= (int) $counts['failed'] ?></a>
    <a class="chip" href="<?= site_url('app/campaigns') ?>">Offers</a>
</div>

<section class="block">
    <div class="block-head"><h2>છેલ્લા messages</h2><a href="<?= site_url('app/whatsapp/messages') ?>">બધા &#8250;</a></div>
    <?php if ($recent === []): ?>
        <p class="empty">હજુ કોઈ message નથી.</p>
    <?php else: ?>
        <?php foreach ($recent as $m): ?>
            <?= view('web/partials/message_card', ['m' => $m]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
