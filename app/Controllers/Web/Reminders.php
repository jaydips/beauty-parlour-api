<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\ReminderModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Reminder actions from the web UI (the list page arrives with the reminders screen).
 *   POST /app/reminders/{id}/sent   the owner sent the WhatsApp message themselves
 *   POST /app/reminders/{id}/skip   do not send this one
 */
class Reminders extends WebController
{
    public function sent(int $id): RedirectResponse
    {
        $model = new ReminderModel();

        if ($model->find($id) === null) {
            return $this->back()->with('error', 'Reminder મળ્યું નહીં.');
        }

        $model->markSent($id);

        return $this->back()->with('success', 'Reminder "મોકલ્યું" તરીકે નોંધાયું.');
    }

    public function skip(int $id): RedirectResponse
    {
        $model = new ReminderModel();

        if ($model->find($id) === null) {
            return $this->back()->with('error', 'Reminder મળ્યું નહીં.');
        }

        $model->update($id, ['status' => 'skipped', 'sent_at' => null]);

        return $this->back()->with('success', 'Reminder છોડી દીધું.');
    }
}
