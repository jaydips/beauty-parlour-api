<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $audience = (string) old('audience', 'all'); ?>
<h1><?= esc($title) ?></h1>

<?php if (! $autoSend): ?>
    <div class="flash flash-error">Auto-send બંધ છે. Messages કતારમાં રહેશે અને <a href="<?= site_url('app/whatsapp') ?>">WhatsApp page</a> પરથી ચાલુ કરશો ત્યારે મોકલાશે.</div>
<?php endif ?>

<form method="post" action="<?= site_url('app/campaigns') ?>" data-confirm="બધા પસંદ કરેલા customers ને message કતારમાં મૂકવો?">
    <?= csrf_field() ?>

    <label for="title">Offer નું નામ (ફક્ત તમારા માટે)</label>
    <input id="title" name="title" type="text" maxlength="120" value="<?= esc(old('title', '')) ?>" required>

    <label for="body">Message (WhatsApp માં જશે)</label>
    <textarea id="body" name="body" rows="5" maxlength="2000" required><?= esc(old('body', '')) ?></textarea>
    <p class="muted">વાપરી શકાય: {customer_name} {parlour_name}</p>

    <label>કોને મોકલવું</label>
    <label class="check"><input type="radio" name="audience" value="all" <?= $audience === 'all' ? 'checked' : '' ?>> બધા WhatsApp opted-in customers (<?= (int) $counts['all'] ?>)</label>
    <label class="check"><input type="radio" name="audience" value="recent" <?= $audience === 'recent' ? 'checked' : '' ?>> છેલ્લા <?= (int) $days ?> દિવસમાં આવેલા (<?= (int) $counts['recent'] ?>)</label>
    <label class="check"><input type="radio" name="audience" value="inactive" <?= $audience === 'inactive' ? 'checked' : '' ?>> <?= (int) $days ?>+ દિવસથી ન આવેલા (<?= (int) $counts['inactive'] ?>)</label>

    <div class="grid2">
        <div>
            <label for="days">દિવસ</label>
            <input id="days" name="days" type="number" inputmode="numeric" min="1" max="3650" value="<?= (int) $days ?>">
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-ghost btn-block" formmethod="get" formaction="<?= site_url('app/campaigns/new') ?>" formnovalidate>ગણતરી જુઓ</button>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/campaigns') ?>">રદ</a>
        <button type="submit" class="btn btn-primary">કતારમાં મૂકો</button>
    </div>
</form>

<?= $this->endSection() ?>
