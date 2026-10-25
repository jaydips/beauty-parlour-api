<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1><?= esc($campaign['title']) ?></h1>
    <span class="badge <?= esc(campaign_status_class($campaign['status'])) ?>"><?= esc(campaign_status_label($campaign['status'])) ?></span>
</div>

<div class="card">
    <?php if (! empty($campaign['image_url'])): ?>
        <img src="<?= esc($campaign['image_url'], 'attr') ?>" alt="" style="display:block;max-width:100%;max-height:260px;border-radius:8px;margin-bottom:8px">
    <?php endif ?>
    <p class="msg"><?= nl2br(esc($campaign['body'])) ?></p>
    <?php if (! empty($campaign['scheduled_at'])): ?>
        <div class="row"><span><?= esc(lang('App.campaign_start')) ?></span><span><?= esc(fmt_datetime($campaign['scheduled_at'])) ?></span></div>
    <?php endif ?>
    <?php if (! empty($warmupUntil)): ?>
        <p class="muted"><?= esc(lang('App.campaign_warmup_notice', [fmt_datetime($warmupUntil)])) ?></p>
    <?php endif ?>
    <div class="row"><span><?= esc(lang('App.to_whom')) ?></span><span><?= esc(match ($campaign['audience']) { 'recent' => lang('App.aud_recent', [(int) $campaign['audience_days']]), 'inactive' => lang('App.aud_inactive', [(int) $campaign['audience_days']]), default => lang('App.aud_all') }) ?></span></div>
    <div class="row"><span><?= esc(lang('App.customers')) ?></span><span><?= (int) $campaign['recipients'] ?></span></div>
    <div class="row"><span><?= esc(lang('App.queued')) ?></span><span><?= (int) $counts['queued'] ?></span></div>
    <div class="row"><span><?= esc(lang('App.sent')) ?></span><span><?= (int) $counts['sent'] ?></span></div>
    <div class="row"><span><?= esc(lang('App.failed')) ?></span><span><?= (int) $counts['failed'] ?></span></div>
    <div class="row"><span><?= esc(lang('App.created')) ?></span><span><?= esc(fmt_datetime($campaign['created_at'])) ?></span></div>
    <?php if ($campaign['status'] === 'queued'): ?>
        <div class="actions">
            <form method="post" action="<?= site_url('app/campaigns/' . (int) $campaign['id'] . '/cancel') ?>" data-confirm="<?= esc(lang('App.confirm_cancel_remaining'), 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm"><?= esc(lang('App.cancel_remaining')) ?></button>
            </form>
        </div>
    <?php endif ?>
</div>

<section class="block">
    <div class="block-head"><h2><?= esc(lang('App.customers')) ?></h2><a href="<?= site_url('app/campaigns') ?>">&#8249; <?= esc(lang('App.all_offers')) ?></a></div>
    <?php if ($messages === []): ?>
        <p class="empty"><?= esc(lang('App.no_customers')) ?></p>
    <?php else: ?>
        <?php foreach ($messages as $m): ?>
            <?= view('web/partials/message_card', ['m' => $m]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
