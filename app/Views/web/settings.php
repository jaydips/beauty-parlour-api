<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$autoOn   = (string) old('wa_auto_send', $values['wa_auto_send'] ?? '0') === '1';
$linked   = $bridgeState[2] === 'open' || $bridgeState[2] === 'connecting';
$field    = static function (string $key, string $value): string {
    $id = esc($key);
    if (str_starts_with($key, 'tpl_') || $key === 'parlour_address') {
        return '<textarea id="' . $id . '" name="' . $id . '" rows="' . (str_starts_with($key, 'tpl_') ? 5 : 3) . '">' . esc($value) . '</textarea>';
    }
    if (in_array($key, ['slot_minutes', 'reminder_lead_hours', 'followup_days', 'wa_daily_cap', 'wa_campaign_warmup_hours'], true)) {
        return '<input id="' . $id . '" name="' . $id . '" type="number" inputmode="numeric" min="0" step="1" value="' . esc($value) . '">';
    }
    if ($key === 'wa_send_from' || $key === 'wa_send_to') {
        return '<input id="' . $id . '" name="' . $id . '" type="time" value="' . esc($value) . '">';
    }
    if ($key === 'parlour_phone' || $key === 'country_code') {
        return '<input id="' . $id . '" name="' . $id . '" type="tel" inputmode="numeric" value="' . esc($value) . '">';
    }
    if (in_array($key, ['google_review_url', 'instagram_url', 'whatsapp_group_url'], true)) {
        return '<input id="' . $id . '" name="' . $id . '" type="url" inputmode="url" placeholder="https://" value="' . esc($value) . '">';
    }

    return '<input id="' . $id . '" name="' . $id . '" type="text" value="' . esc($value) . '">';
};
$general   = ['parlour_name', 'tagline', 'parlour_phone', 'parlour_address', 'country_code', 'slot_minutes', 'reminder_lead_hours', 'followup_days'];
$links     = ['google_review_url', 'instagram_url', 'whatsapp_group_url'];
$templates = ['tpl_whatsapp_booking', 'tpl_whatsapp_reminder', 'tpl_whatsapp_followup', 'tpl_whatsapp_birthday', 'tpl_whatsapp_review', 'tpl_whatsapp_offer'];
?>

<h1><?= esc(lang('App.title_settings')) ?></h1>

<form method="post" action="<?= site_url('app/settings') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-top">
            <span class="card-title"><?= esc(lang('App.auto_wa_title')) ?></span>
            <span class="badge <?= $autoOn ? 'badge-green' : 'badge-grey' ?>"><?= esc($autoOn ? lang('App.on') : lang('App.off')) ?></span>
        </div>
        <p class="muted"><?= esc(lang('App.auto_wa_help')) ?></p>
        <input type="hidden" name="wa_auto_send" value="0">
        <label class="check"><input type="checkbox" name="wa_auto_send" value="1" <?= $autoOn ? 'checked' : '' ?>> <?= esc(lang('App.auto_wa_switch_label')) ?></label>
        <div class="row"><span><?= esc(lang('App.bridge_status')) ?></span><span class="badge <?= esc($bridgeState[1]) ?>"><?= esc($bridgeState[0]) ?></span></div>
        <?php if ($autoOn && ! $linked): ?>
            <p class="muted"><?= esc(lang('App.auto_wa_not_linked_warning')) ?></p>
        <?php endif ?>
        <div class="grid2">
            <div>
                <label for="wa_send_from"><?= esc(lang('App.setting_wa_send_from')) ?></label>
                <?= $field('wa_send_from', (string) old('wa_send_from', $values['wa_send_from'] ?? '')) ?>
            </div>
            <div>
                <label for="wa_send_to"><?= esc(lang('App.setting_wa_send_to')) ?></label>
                <?= $field('wa_send_to', (string) old('wa_send_to', $values['wa_send_to'] ?? '')) ?>
            </div>
        </div>
        <div class="grid2">
            <div>
                <label for="wa_daily_cap"><?= esc(lang('App.setting_wa_daily_cap')) ?></label>
                <?= $field('wa_daily_cap', (string) old('wa_daily_cap', $values['wa_daily_cap'] ?? '150')) ?>
            </div>
            <div>
                <label for="wa_campaign_warmup_hours"><?= esc(lang('App.setting_wa_campaign_warmup_hours')) ?></label>
                <?= $field('wa_campaign_warmup_hours', (string) old('wa_campaign_warmup_hours', $values['wa_campaign_warmup_hours'] ?? '24')) ?>
            </div>
        </div>
        <p class="muted"><?= esc(lang('App.daily_cap_help')) ?></p>
        <div class="actions">
            <a class="btn btn-ghost btn-sm" href="<?= site_url('app/whatsapp') ?>"><?= esc(lang('App.open_whatsapp_page')) ?></a>
        </div>
    </div>

    <h2><?= esc(lang('App.general_heading')) ?></h2>
    <?php foreach ($general as $key): ?>
        <label for="<?= esc($key) ?>"><?= esc($fields[$key]) ?></label>
        <?= $field($key, (string) old($key, $values[$key] ?? '')) ?>
    <?php endforeach ?>

    <h2><?= esc(lang('App.links_heading')) ?></h2>
    <p class="muted"><?= esc(lang('App.links_help')) ?></p>
    <?php foreach ($links as $key): ?>
        <label for="<?= esc($key) ?>"><?= esc($fields[$key]) ?></label>
        <?= $field($key, (string) old($key, $values[$key] ?? '')) ?>
    <?php endforeach ?>

    <h2><?= esc(lang('App.templates_heading')) ?></h2>
    <p class="muted"><?= esc(lang('App.template_placeholders')) ?></p>
    <?php foreach ($templates as $key): ?>
        <label for="<?= esc($key) ?>"><?= esc($fields[$key]) ?></label>
        <?= $field($key, (string) old($key, $values[$key] ?? '')) ?>
    <?php endforeach ?>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= site_url('app/more') ?>"><?= esc(lang('App.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= esc(lang('App.save')) ?></button>
    </div>
</form>

<?= $this->endSection() ?>
