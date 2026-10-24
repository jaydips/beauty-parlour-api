<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1><?= esc($campaign['title']) ?></h1>
    <span class="badge <?= $campaign['status'] === 'done' ? 'badge-green' : ($campaign['status'] === 'cancelled' ? 'badge-grey' : 'badge-amber') ?>"><?= esc(campaign_status_label($campaign['status'])) ?></span>
</div>

<div class="card">
    <p class="msg"><?= nl2br(esc($campaign['body'])) ?></p>
    <div class="row"><span>કોને</span><span><?= esc(match ($campaign['audience']) { 'recent' => 'છેલ્લા ' . (int) $campaign['audience_days'] . ' દિવસમાં આવેલા', 'inactive' => (int) $campaign['audience_days'] . '+ દિવસથી ન આવેલા', default => 'બધા opted-in' }) ?></span></div>
    <div class="row"><span>Customers</span><span><?= (int) $campaign['recipients'] ?></span></div>
    <div class="row"><span>કતારમાં</span><span><?= (int) $counts['queued'] ?></span></div>
    <div class="row"><span>મોકલાયાં</span><span><?= (int) $counts['sent'] ?></span></div>
    <div class="row"><span>નિષ્ફળ</span><span><?= (int) $counts['failed'] ?></span></div>
    <div class="row"><span>બનાવ્યું</span><span><?= esc(fmt_datetime($campaign['created_at'])) ?></span></div>
    <?php if ($campaign['status'] === 'queued'): ?>
        <div class="actions">
            <form method="post" action="<?= site_url('app/campaigns/' . (int) $campaign['id'] . '/cancel') ?>" data-confirm="બાકી messages રદ કરવા?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm">બાકી રદ કરો</button>
            </form>
        </div>
    <?php endif ?>
</div>

<section class="block">
    <div class="block-head"><h2>Customers</h2><a href="<?= site_url('app/campaigns') ?>">&#8249; બધી offers</a></div>
    <?php if ($messages === []): ?>
        <p class="empty">કોઈ customer નથી.</p>
    <?php else: ?>
        <?php foreach ($messages as $m): ?>
            <?= view('web/partials/message_card', ['m' => $m]) ?>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
