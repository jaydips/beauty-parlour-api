<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1><?= esc(lang('App.title_more')) ?></h1>

<div class="menu block">
    <a class="card card-link" href="<?= site_url('app/services') ?>"><span><?= esc(lang('App.menu_services')) ?></span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/payments') ?>"><span><?= esc(lang('App.menu_payments')) ?></span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/reminders') ?>"><span><?= esc(lang('App.menu_reminders')) ?></span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/whatsapp') ?>"><span><?= esc(lang('App.menu_whatsapp')) ?></span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/campaigns') ?>"><span><?= esc(lang('App.menu_campaigns')) ?></span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/settings') ?>"><span><?= esc(lang('App.menu_settings')) ?></span><span>&#8250;</span></a>
    <a class="card card-link" href="<?= site_url('app/password') ?>"><span><?= esc(lang('App.menu_password')) ?></span><span>&#8250;</span></a>
</div>

<div class="card">
    <div class="row"><span><?= esc(lang('App.login')) ?></span><span><?= esc($owner['name'] ?? '') ?> · <?= esc($owner['phone'] ?? '') ?></span></div>
    <div class="row"><span><?= esc(lang('App.api')) ?></span><span class="muted"><?= esc(base_url()) ?></span></div>
</div>

<form method="post" action="<?= site_url('logout') ?>" class="block">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-block"><?= esc(lang('App.logout')) ?></button>
</form>

<?= $this->endSection() ?>
