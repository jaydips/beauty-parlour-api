<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1>Customers</h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/customers/new') ?>">+ નવો</a>
</div>

<form method="get" action="<?= site_url('app/customers') ?>" class="searchbar">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="નામ અથવા mobile નંબર" aria-label="શોધો">
    <button class="btn btn-ghost">શોધો</button>
</form>

<section class="block">
    <?php if ($customers === []): ?>
        <p class="empty"><?= $q !== '' ? 'કોઈ customer મળ્યો નહીં.' : 'હજુ કોઈ customer નથી.' ?></p>
    <?php else: ?>
        <?php foreach ($customers as $c): ?>
            <a class="card card-link" href="<?= site_url('app/customers/' . (int) $c['id']) ?>">
                <div class="card-top">
                    <span class="card-title"><?= esc($c['name']) ?></span>
                    <span class="muted"><?= esc($c['phone']) ?></span>
                </div>
            </a>
        <?php endforeach ?>
        <?php if ($pager->getPageCount() > 1): ?>
            <div class="pager">
                <?php if ($pager->getCurrentPage() > 1): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('app/customers') ?>?q=<?= urlencode($q) ?>&page=<?= $pager->getCurrentPage() - 1 ?>">&#8249; પાછળ</a>
                <?php endif ?>
                <span class="muted"><?= $pager->getCurrentPage() ?> / <?= $pager->getPageCount() ?></span>
                <?php if ($pager->getCurrentPage() < $pager->getPageCount()): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('app/customers') ?>?q=<?= urlencode($q) ?>&page=<?= $pager->getCurrentPage() + 1 ?>">આગળ &#8250;</a>
                <?php endif ?>
            </div>
        <?php endif ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
