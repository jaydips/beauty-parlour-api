<?php /** @var array $owner  @var string $parlourName */ ?>
<section class="card profile">
    <div class="avatar" aria-hidden="true"><?= esc(mb_substr((string) ($owner['name'] ?? 'O'), 0, 1)) ?></div>
    <div class="grow">
        <div class="card-title"><?= esc($owner['name'] ?? '') ?></div>
        <div class="small muted"><?= esc($owner['phone'] ?? '') ?> · <?= esc($parlourName) ?></div>
    </div>
</section>

<nav class="menu">
    <a class="menu-item" href="<?= site_url('app/reminders') ?>">
        <span class="menu-icon">💬</span><span class="grow">WhatsApp રિમાઇન્ડર</span><span class="chev">›</span>
    </a>
    <a class="menu-item" href="<?= site_url('app/services') ?>">
        <span class="menu-icon">✂️</span><span class="grow">સર્વિસ અને ભાવ</span><span class="chev">›</span>
    </a>
    <a class="menu-item" href="<?= site_url('app/payments') ?>">
        <span class="menu-icon">₹</span><span class="grow">પેમેન્ટ અને આવક</span><span class="chev">›</span>
    </a>
    <a class="menu-item" href="<?= site_url('app/settings') ?>">
        <span class="menu-icon">⚙️</span><span class="grow">સેટિંગ્સ</span><span class="chev">›</span>
    </a>
</nav>

<form method="post" action="<?= site_url('logout') ?>" class="block">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline btn-block">લોગઆઉટ</button>
</form>

<p class="muted small center">API: <a href="<?= site_url('health') ?>">/health</a></p>
