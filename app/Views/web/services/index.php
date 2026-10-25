<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1><?= esc(lang('App.title_services')) ?></h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/services/new') ?>"><?= esc(lang('App.new_f')) ?></a>
</div>

<section class="block">
    <?php if ($services === []): ?>
        <p class="empty"><?= esc(lang('App.no_services_yet')) ?></p>
    <?php else: ?>
        <?php foreach ($services as $s): ?>
            <a class="card card-link" href="<?= site_url('app/services/' . (int) $s['id'] . '/edit') ?>">
                <div class="card-top">
                    <span class="card-title"><?= esc($s['name']) ?></span>
                    <strong><?= esc(rupees($s['price'])) ?></strong>
                </div>
                <div class="card-bottom">
                    <span class="muted"><?= (int) $s['duration_minutes'] ?> <?= esc(lang('App.minutes_short')) ?><?= $s['category'] ? ' · ' . esc($s['category']) : '' ?></span>
                    <?php if ((int) $s['is_active'] !== 1): ?><span class="badge badge-grey"><?= esc(lang('App.inactive')) ?></span><?php endif ?>
                </div>
            </a>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
