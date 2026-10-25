<!doctype html>
<html lang="gu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#b23a73">
<title><?= esc(lang('App.title_login')) ?> · <?= esc($parlour) ?></title>
<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= base_url('assets/icon-192.png') ?>">
<link rel="apple-touch-icon" href="<?= base_url('assets/icon-192.png') ?>">
<link rel="stylesheet" href="<?= base_url('assets/app.css?v=2') ?>">
</head>
<body class="login-body">
<main class="login-card">
    <div class="login-logo">&#10048;</div>
    <h1><?= esc($parlour) ?></h1>
    <p class="muted"><?= esc(lang('App.owner_login')) ?></p>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="flash flash-error" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>
    <?php if (! empty($loggedOut)): ?>
        <div class="flash flash-success" role="status"><?= esc(lang('App.logged_out')) ?></div>
    <?php endif ?>

    <form method="post" action="<?= site_url('login') ?>" autocomplete="on">
        <?= csrf_field() ?>
        <label for="phone"><?= esc(lang('App.mobile_number')) ?></label>
        <input id="phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10"
               placeholder="<?= esc(lang('App.ten_digits'), 'attr') ?>" value="<?= esc(old('phone', '')) ?>" required autofocus autocomplete="username">
        <label for="password"><?= esc(lang('App.password')) ?></label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="submit" class="btn btn-primary btn-block"><?= esc(lang('App.login')) ?></button>
    </form>
</main>
</body>
</html>
