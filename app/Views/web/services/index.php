<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1>Services</h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/services/new') ?>">+ નવી</a>
</div>

<section class="block">
    <?php if ($services === []): ?>
        <p class="empty">હજુ કોઈ service નથી. "+ નવી" થી ઉમેરો.</p>
    <?php else: ?>
        <?php foreach ($services as $s): ?>
            <a class="card card-link" href="<?= site_url('app/services/' . (int) $s['id'] . '/edit') ?>">
                <div class="card-top">
                    <span class="card-title"><?= esc($s['name']) ?></span>
                    <strong><?= esc(rupees($s['price'])) ?></strong>
                </div>
                <div class="card-bottom">
                    <span class="muted"><?= (int) $s['duration_minutes'] ?> min<?= $s['category'] ? ' · ' . esc($s['category']) : '' ?></span>
                    <?php if ((int) $s['is_active'] !== 1): ?><span class="badge badge-grey">બંધ</span><?php endif ?>
                </div>
            </a>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
