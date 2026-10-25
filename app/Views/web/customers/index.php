<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1><?= esc(lang('App.title_customers')) ?></h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/customers/new') ?>"><?= esc(lang('App.new_m')) ?></a>
</div>

<form method="get" action="<?= site_url('app/customers') ?>" class="searchbar">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="<?= esc(lang('App.name_or_phone'), 'attr') ?>" aria-label="<?= esc(lang('App.search'), 'attr') ?>">
    <button class="btn btn-ghost"><?= esc(lang('App.search')) ?></button>
</form>

<section class="block">
    <?php if ($customers === []): ?>
        <p class="empty"><?= esc($q !== '' ? lang('App.no_customer_found') : lang('App.no_customers_yet')) ?></p>
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
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('app/customers') ?>?q=<?= urlencode($q) ?>&page=<?= $pager->getCurrentPage() - 1 ?>">&#8249; <?= esc(lang('App.back')) ?></a>
                <?php endif ?>
                <span class="muted"><?= $pager->getCurrentPage() ?> / <?= $pager->getPageCount() ?></span>
                <?php if ($pager->getCurrentPage() < $pager->getPageCount()): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('app/customers') ?>?q=<?= urlencode($q) ?>&page=<?= $pager->getCurrentPage() + 1 ?>"><?= esc(lang('App.next')) ?> &#8250;</a>
                <?php endif ?>
            </div>
        <?php endif ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
