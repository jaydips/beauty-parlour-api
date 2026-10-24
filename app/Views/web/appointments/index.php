<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<section class="daynav">
    <a class="btn btn-ghost" href="<?= site_url('app/appointments') ?>?date=<?= esc($prev) ?>" aria-label="આગલો દિવસ">&#8249;</a>
    <h1><?= esc(fmt_day_title($date)) ?></h1>
    <a class="btn btn-ghost" href="<?= site_url('app/appointments') ?>?date=<?= esc($next) ?>" aria-label="પછીનો દિવસ">&#8250;</a>
</section>

<form class="daypick" method="get" action="<?= site_url('app/appointments') ?>">
    <input type="date" name="date" value="<?= esc($date) ?>" onchange="this.form.submit()" aria-label="તારીખ પસંદ કરો">
    <a class="btn btn-primary" href="<?= site_url('app/appointments/new') ?>?date=<?= esc($date) ?>">+ નવી appointment</a>
</form>

<section class="block">
    <?php if ($appointments === []): ?>
        <p class="empty">આ દિવસે કોઈ appointment નથી.</p>
    <?php else: ?>
        <?php foreach ($appointments as $a): ?>
            <?= view('web/partials/appointment_card', ['a' => $a]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
