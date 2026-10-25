<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1><?= esc(lang('App.title_campaigns')) ?></h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/campaigns/new') ?>"><?= esc(lang('App.new_f')) ?></a>
</div>
<p class="muted"><?= esc(lang('App.campaigns_intro')) ?></p>

<section class="block">
    <?php if ($campaigns === []): ?>
        <p class="empty"><?= esc(lang('App.no_campaigns_yet')) ?></p>
    <?php else: ?>
        <?php foreach ($campaigns as $c): ?>
            <a class="card card-link" href="<?= site_url('app/campaigns/' . (int) $c['id']) ?>">
                <span>
                    <span class="card-title"><?= esc($c['title']) ?></span><br>
                    <span class="muted"><?= esc(lang('App.campaign_stats', [(int) $c['recipients'], (int) $c['sent'], (int) $c['failed'], fmt_date($c['created_at'])])) ?></span>
                </span>
                <span class="badge <?= esc(campaign_status_class($c['status'])) ?>"><?= esc(campaign_status_label($c['status'])) ?></span>
            </a>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
