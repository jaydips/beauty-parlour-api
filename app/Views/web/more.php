<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1>વધુ</h1>

<div class="menu block">
    <a class="card card-link" href="<?= site_url('app/services') ?>"><span>Services (price list)</span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/payments') ?>"><span>Payments</span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/reminders') ?>"><span>WhatsApp reminders</span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/whatsapp') ?>"><span>WhatsApp auto-send (bridge)</span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/campaigns') ?>"><span>Offers / broadcast messages</span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/settings') ?>"><span>Settings</span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/password') ?>"><span>Password બદલો</span><span>&#8250;</span></a>
</div>

<div class="card">
    <div class="row"><span>Login</span><span><?= esc($owner['name'] ?? '') ?> · <?= esc($owner['phone'] ?? '') ?></span></div>
    <div class="row"><span>API</span><span class="muted"><?= esc(base_url()) ?></span></div>
</div>

<form method="post" action="<?= site_url('logout') ?>" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block">Logout</button>
</form>

<?= $this->endSection() ?>
