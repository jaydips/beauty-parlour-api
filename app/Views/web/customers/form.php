<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $customer !== null;
$action = $isEdit ? site_url('app/customers/' . (int) $customer['id']) : site_url('app/customers');
$v      = static fn (string $f, string $d = ''): string => (string) old($f, $customer[$f] ?? $d);
?>
<h1><?= esc($title) ?></h1>

<form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <label for="name">નામ</label>
    <input id="name" name="name" type="text" maxlength="100" value="<?= esc($v('name')) ?>" required>

    <label for="phone">Mobile (10 અંક)</label>
    <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" value="<?= esc($v('phone')) ?>" required>

    <label for="alt_phone">બીજો નંબર (વૈકલ્પિક)</label>
    <input id="alt_phone" name="alt_phone" type="tel" inputmode="numeric" maxlength="10" value="<?= esc($v('alt_phone')) ?>">

    <div class="grid2">
        <div>
            <label for="gender">Gender</label>
            <select id="gender" name="gender">
                <?php foreach (['' => '-', 'female' => 'સ્ત્રી', 'male' => 'પુરુષ', 'other' => 'અન્ય'] as $val => $lbl): ?>
                    <option value="<?= esc($val) ?>" <?= $v('gender') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="birthday">જન્મદિવસ</label>
            <input id="birthday" name="birthday" type="date" value="<?= esc($v('birthday')) ?>">
        </div>
    </div>

    <label class="check"><input type="checkbox" name="whatsapp_opt_in" value="1" <?= old('whatsapp_opt_in', $customer['whatsapp_opt_in'] ?? 1) ? 'checked' : '' ?>> WhatsApp reminders મોકલવા</label>

    <label for="notes">નોંધ (allergy, પસંદગી વગેરે)</label>
    <textarea id="notes" name="notes" rows="3"><?= esc($v('notes')) ?></textarea>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $isEdit ? site_url('app/customers/' . (int) $customer['id']) : site_url('app/customers') ?>">રદ</a>
        <button type="submit" class="btn btn-primary">સાચવો</button>
    </div>
</form>

<?= $this->endSection() ?>
