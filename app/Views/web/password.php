<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1>Password બદલો</h1>

<form method="post" action="<?= site_url('app/password') ?>" class="card">
    <?= csrf_field() ?>
    <label for="current_password">હાલનો password</label>
    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
    <label for="new_password">નવો password (ઓછામાં ઓછા 8 અક્ષર)</label>
    <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
    <label for="confirm_password">નવો password ફરી</label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
    <p class="muted">Password બદલતાં Flutter app ના બધા logins બંધ થશે; ત્યાં ફરી login કરવું પડશે.</p>
    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/more') ?>">રદ</a>
        <button type="submit" class="btn btn-primary">બદલો</button>
    </div>
</form>

<?= $this->endSection() ?>
