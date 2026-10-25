<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $audience = (string) old('audience', 'all'); ?>
<h1><?= esc($title) ?></h1>

<?php if (! $autoSend): ?>
    <div class="flash flash-error"><?= lang('App.auto_send_off_warning', [site_url('app/whatsapp')]) ?></div>
<?php endif ?>

<form method="post" action="<?= site_url('app/campaigns') ?>" data-confirm="<?= esc(lang('App.confirm_queue_campaign'), 'attr') ?>">
    <?= csrf_field() ?>

    <label for="title"><?= esc(lang('App.offer_name')) ?></label>
    <input id="title" name="title" type="text" maxlength="120" value="<?= esc(old('title', '')) ?>" required>

    <label for="body"><?= esc(lang('App.message_whatsapp')) ?></label>
    <textarea id="body" name="body" rows="5" maxlength="2000" required><?= esc(old('body', $defaultBody)) ?></textarea>
    <p class="muted"><?= esc(lang('App.offer_placeholders')) ?></p>

    <label><?= esc(lang('App.send_to_whom')) ?></label>
    <label class="check"><input type="radio" name="audience" value="all" <?= $audience === 'all' ? 'checked' : '' ?>> <?= esc(lang('App.audience_all', [(int) $counts['all']])) ?></label>
    <label class="check"><input type="radio" name="audience" value="recent" <?= $audience === 'recent' ? 'checked' : '' ?>> <?= esc(lang('App.audience_recent', [(int) $days, (int) $counts['recent']])) ?></label>
    <label class="check"><input type="radio" name="audience" value="inactive" <?= $audience === 'inactive' ? 'checked' : '' ?>> <?= esc(lang('App.audience_inactive', [(int) $days, (int) $counts['inactive']])) ?></label>

    <div class="grid2">
        <div>
            <label for="days"><?= esc(lang('App.days')) ?></label>
            <input id="days" name="days" type="number" inputmode="numeric" min="1" max="3650" value="<?= (int) $days ?>">
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-ghost btn-block" formmethod="get" formaction="<?= site_url('app/campaigns/new') ?>" formnovalidate><?= esc(lang('App.recount')) ?></button>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/campaigns') ?>"><?= esc(lang('App.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= esc(lang('App.queue_messages')) ?></button>
    </div>
</form>

<?= $this->endSection() ?>
