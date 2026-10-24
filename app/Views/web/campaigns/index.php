<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="hdr">
    <h1>Offers / broadcast</h1>
    <a class="btn btn-primary btn-sm" href="<?= site_url('app/campaigns/new') ?>">+ નવી</a>
</div>
<p class="muted">એક message ઘણા customers ને WhatsApp bridge દ્વારા (auto-send ચાલુ હોવું જોઈએ). Messages ધીમે ધીમે, દર 30 સેકન્ડે એક, send window માં મોકલાય છે.</p>

<section class="block">
    <?php if ($campaigns === []): ?>
        <p class="empty">હજુ કોઈ offer મોકલી નથી.</p>
    <?php else: ?>
        <?php foreach ($campaigns as $c): ?>
            <a class="card card-link" href="<?= site_url('app/campaigns/' . (int) $c['id']) ?>">
                <span>
                    <span class="card-title"><?= esc($c['title']) ?></span><br>
                    <span class="muted">
                        <?= (int) $c['recipients'] ?> customers · મોકલાયાં <?= (int) $c['sent'] ?> · નિષ્ફળ <?= (int) $c['failed'] ?> · <?= esc(fmt_date($c['created_at'])) ?>
                    </span>
                </span>
                <span class="badge <?= $c['status'] === 'done' ? 'badge-green' : ($c['status'] === 'cancelled' ? 'badge-grey' : 'badge-amber') ?>"><?= esc(campaign_status_label($c['status'])) ?></span>
            </a>
        <?php endforeach ?>
    <?php endif ?>
</section>

<?= $this->endSection() ?>
