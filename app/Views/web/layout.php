<?php
/**
 * Common shell for the web UI: top bar, flash messages, page content, bottom tab bar.
 * Variables: $title, $content, $tab, $back, $loggedIn, $parlourName, $assetVersion
 */
$title       ??= '';
$tab         ??= null;
$back        ??= null;
$loggedIn    ??= false;
$parlourName ??= 'Beauty Parlour';
$assetVersion ??= '1';
$session     = session();
$flashes     = [];

foreach (['success', 'error', 'warning', 'info'] as $kind) {
    $msg = $session->getFlashdata($kind);

    if (is_string($msg) && $msg !== '') {
        $flashes[] = [$kind, $msg];
    }
}

$tabs = [
    'home'      => ['app', 'હોમ', 'M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    'calendar'  => ['app/appointments', 'બુકિંગ', 'M7 2v3M17 2v3M3 9h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'],
    'customers' => ['app/customers', 'ગ્રાહકો', 'M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 20v-2a4 4 0 0 0-3-3.9M15 3.1a4 4 0 0 1 0 7.8'],
    'payments'  => ['app/payments', 'પેમેન્ટ', 'M2 7h20v12H2zM2 11h20M6 15h4'],
    'more'      => ['app/more', 'વધુ', 'M4 6h16M4 12h16M4 18h16'],
];
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= esc($title !== '' ? $title . ' · ' : '') ?><?= esc($parlourName) ?></title>
    <meta name="theme-color" content="#b4236b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= esc($parlourName, 'attr') ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <link rel="icon" type="image/png" href="<?= base_url('assets/icon-192.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/icon-192.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>?v=<?= esc($assetVersion, 'attr') ?>">
</head>
<body class="<?= $loggedIn ? 'has-tabbar' : 'no-tabbar' ?>">
<header class="topbar">
    <?php if ($back): ?>
        <a class="topbar-btn" href="<?= esc($back) ?>" aria-label="પાછળ">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>
        </a>
    <?php elseif ($loggedIn): ?>
        <span class="topbar-brand" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="5" r="2.4"/><circle cx="18.1" cy="8.5" r="2.4"/><circle cx="18.1" cy="15.5" r="2.4"/><circle cx="12" cy="19" r="2.4"/><circle cx="5.9" cy="15.5" r="2.4"/><circle cx="5.9" cy="8.5" r="2.4"/></svg>
        </span>
    <?php endif; ?>
    <h1 class="topbar-title"><?= esc($title !== '' ? $title : $parlourName) ?></h1>
    <?php if ($loggedIn): ?>
        <a class="topbar-btn" href="<?= site_url('app/appointments/new') ?>" aria-label="નવી અપોઇન્ટમેન્ટ" title="નવી અપોઇન્ટમેન્ટ">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </a>
    <?php endif; ?>
</header>

<main class="page">
    <?php foreach ($flashes as [$kind, $msg]): ?>
        <div class="flash flash-<?= esc($kind, 'attr') ?>" role="status"><?= esc($msg) ?></div>
    <?php endforeach; ?>

    <?= $content ?? '' ?>
</main>

<?php if ($loggedIn): ?>
<nav class="tabbar" aria-label="મુખ્ય મેનુ">
    <?php foreach ($tabs as $key => [$url, $label, $path]): ?>
        <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="<?= site_url($url) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $path ?>"/></svg>
            <span><?= esc($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<script src="<?= base_url('assets/app.js') ?>?v=<?= esc($assetVersion, 'attr') ?>" defer></script>
</body>
</html>
