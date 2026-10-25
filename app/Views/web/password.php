<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1><?= esc(lang('App.title_password')) ?></h1>

<form method="post" action="<?= site_url('app/password') ?>" class="card">
    <?= csrf_field() ?>
    <label for="current_password"><?= esc(lang('App.current_password')) ?></label>
    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
    <label for="new_password"><?= esc(lang('App.new_password')) ?></label>
    <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
    <label for="confirm_password"><?= esc(lang('App.confirm_new_password')) ?></label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
    <p class="muted"><?= esc(lang('App.password_note')) ?></p>
    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/more') ?>"><?= esc(lang('App.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= esc(lang('App.change')) ?></button>
    </div>
</form>

<?= $this->endSection() ?>
