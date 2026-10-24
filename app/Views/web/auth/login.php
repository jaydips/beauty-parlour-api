<?php
/** @var string|null $error  @var string $phone  @var bool $loggedOut  @var string $parlourName */
?>
<section class="login">
    <div class="login-logo" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="5" r="2.4"/><circle cx="18.1" cy="8.5" r="2.4"/><circle cx="18.1" cy="15.5" r="2.4"/><circle cx="12" cy="19" r="2.4"/><circle cx="5.9" cy="15.5" r="2.4"/><circle cx="5.9" cy="8.5" r="2.4"/></svg>
    </div>
    <h2 class="login-title"><?= esc($parlourName) ?></h2>
    <p class="muted center">માલિક લોગિન</p>

    <?php if ($loggedOut && $error === null): ?>
        <div class="flash flash-info">તમે લોગઆઉટ થઈ ગયા છો.</div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="flash flash-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('login') ?>" class="card form" autocomplete="on">
        <?= csrf_field() ?>
        <label class="field">
            <span class="label">ફોન નંબર</span>
            <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" placeholder="10 અંક" value="<?= esc($phone, 'attr') ?>" autocomplete="username" required autofocus>
        </label>
        <label class="field">
            <span class="label">પાસવર્ડ</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block">લોગિન કરો</button>
    </form>

    <p class="muted small center">30 દિવસ સુધી લોગિન યાદ રહેશે. બ્રાઉઝરના મેનુમાંથી "Add to Home Screen" કરો.</p>
</section>
