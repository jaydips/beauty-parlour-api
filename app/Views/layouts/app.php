<!doctype html>
<html lang="gu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#b23a73">
<title><?= esc($title) ?> · <?= esc($parlour) ?></title>
<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= base_url('assets/icon-192.png') ?>">
<link rel="apple-touch-icon" href="<?= base_url('assets/icon-192.png') ?>">
<link rel="stylesheet" href="<?= base_url('assets/app.css?v=1') ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= site_url('app') ?>"><?= esc($parlour) ?></a>
    <span class="who"><?= esc($owner['name'] ?? '') ?></span>
</header>

<main class="page">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="flash flash-error" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="flash flash-success" role="status"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif ?>

    <?= $this->renderSection('content') ?>
</main>

<nav class="tabbar" aria-label="મુખ્ય મેનુ">
    <a href="<?= site_url('app') ?>" class="<?= $active === 'dashboard' ? 'on' : '' ?>">
        <span class="ico">&#9679;</span><span>Dashboard</span>
    </a>
    <a href="<?= site_url('app/appointments') ?>" class="<?= $active === 'appointments' ? 'on' : '' ?>">
        <span class="ico">&#128197;</span><span>Appointments</span>
    </a>
    <a href="<?= site_url('app/customers') ?>" class="<?= $active === 'customers' ? 'on' : '' ?>">
        <span class="ico">&#128100;</span><span>Customers</span>
    </a>
    <a href="<?= site_url('app/more') ?>" class="<?= $active === 'more' ? 'on' : '' ?>">
        <span class="ico">&#9776;</span><span>વધુ</span>
    </a>
</nav>

<script src="<?= base_url('assets/app.js?v=1') ?>" defer></script>
</body>
</html>
