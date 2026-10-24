<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\MessageModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Automatic WhatsApp (bridge) screens.
 *   GET  /app/whatsapp                   bridge state, linking (pairing code / QR), auto-send switch, outbox counts
 *   POST /app/whatsapp/pair {phone}      ask the bridge for a pairing code
 *   POST /app/whatsapp/logout            unlink the WhatsApp account
 *   POST /app/whatsapp/restart           restart the bridge process (watchdog starts it again)
 *   POST /app/whatsapp/auto-send {enabled}
 *   POST /app/whatsapp/test {phone}      send one test message right now
 *   GET  /app/whatsapp/messages?status=  outbox list
 *   POST /app/whatsapp/messages/{id}/retry | /cancel
 */
class WhatsApp extends WebController
{
    public function index(): string
    {
        $bridge     = service('whatsappBridge');
        $outbox     = service('outbox');
        $configured = $bridge->configured();
        $status     = $configured ? $bridge->status() : ['ok' => false, 'error' => 'bridge_not_configured'];
        $messages   = new MessageModel();
        $settings   = new SettingModel();

        return $this->render('web/whatsapp/index', [
            'title'        => 'WhatsApp auto-send',
            'configured'   => $configured,
            'status'       => $status,
            'state'        => $this->state($configured, $status),
            'autoSend'     => $outbox->autoSendEnabled(),
            'window'       => $outbox->sendWindow(),
            'counts'       => $messages->counts(),
            'recent'       => $outbox->decorate($messages->orderBy('id', 'DESC')->findAll(8)),
            'pairingCode'  => session()->getFlashdata('pairing_code') ?? ($status['pairing_code'] ?? null),
            'defaultPhone' => (string) ($settings->value('parlour_phone') ?: ($this->owner()['phone'] ?? '')),
        ], 'more');
    }

    public function pair(): RedirectResponse
    {
        $phone = preg_replace('/\D+/', '', (string) ($this->input()['phone'] ?? '')) ?? '';

        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return $this->home()->with('error', 'WhatsApp વાળો 10 અંકનો mobile નંબર લખો.');
        }

        $result = service('whatsappBridge')->pair($phone);

        if (! empty($result['pairing_code'])) {
            return $this->home()->with('pairing_code', (string) $result['pairing_code'])
                ->with('success', 'Code મળ્યો. WhatsApp → Linked devices → Link with phone number માં આ code નાખો.');
        }

        if (($result['error'] ?? '') === 'already_linked') {
            return $this->home()->with('error', 'Bridge પહેલેથી linked છે (+' . ($result['me'] ?? '') . '). પહેલાં unlink કરો.');
        }

        return $this->home()->with('error', 'Pairing code મળ્યો નહીં: ' . ($result['error'] ?? 'unknown'));
    }

    public function logout(): RedirectResponse
    {
        $result = service('whatsappBridge')->logout();

        return $this->home()->with($result['ok'] ?? false ? 'success' : 'error', $result['ok'] ?? false ? 'WhatsApp નંબર unlink થયો.' : 'Unlink થયું નહીં: ' . ($result['error'] ?? 'unknown'));
    }

    public function restart(): RedirectResponse
    {
        $result = service('whatsappBridge')->restart();

        return $this->home()->with($result['ok'] ?? false ? 'success' : 'error', $result['ok'] ?? false ? 'Bridge restart થાય છે; 1 મિનિટમાં પાછું ચાલુ થશે.' : 'Bridge સુધી પહોંચાયું નહીં: ' . ($result['error'] ?? 'unknown'));
    }

    public function toggle(): RedirectResponse
    {
        $on = (string) ($this->input()['enabled'] ?? '0') === '1';
        (new SettingModel())->setValue('wa_auto_send', $on ? '1' : '0');

        return $this->home()->with('success', $on ? 'Auto-send ચાલુ થયું.' : 'Auto-send બંધ થયું. Reminders હવે જાતે WhatsApp button થી મોકલો.');
    }

    public function test(): RedirectResponse
    {
        $phone = preg_replace('/\D+/', '', (string) ($this->input()['phone'] ?? '')) ?? '';

        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return $this->home()->with('error', '10 અંકનો mobile નંબર લખો.');
        }

        $parlour = (string) (new SettingModel())->value('parlour_name', 'Beauty Parlour');
        $result  = service('whatsappBridge')->send($phone, 'Test message from ' . $parlour . ' - WhatsApp auto-send is working.');

        return $this->home()->with($result['ok'] ?? false ? 'success' : 'error', $result['ok'] ?? false ? "Test message {$phone} પર મોકલાયો." : 'મોકલાયું નહીં: ' . ($result['error'] ?? 'unknown'));
    }

    public function messages(): string
    {
        $status = (string) ($this->request->getGet('status') ?? 'all');
        $model  = new MessageModel();

        if (in_array($status, MessageModel::STATUSES, true)) {
            $model->where('status', $status);
        } else {
            $status = 'all';
        }

        return $this->render('web/whatsapp/messages', [
            'title'    => 'WhatsApp outbox',
            'status'   => $status,
            'counts'   => (new MessageModel())->counts(),
            'messages' => service('outbox')->decorate($model->orderBy('id', 'DESC')->findAll(150)),
        ], 'more');
    }

    public function retry(int $id): RedirectResponse
    {
        $model = new MessageModel();
        $row   = $model->find($id);

        if ($row === null) {
            return $this->back('app/whatsapp/messages')->with('error', 'Message મળ્યો નહીં.');
        }

        if (! in_array($row['status'], ['failed', 'cancelled'], true)) {
            return $this->back('app/whatsapp/messages')->with('error', 'ફક્ત નિષ્ફળ/રદ થયેલા messages ફરી મોકલી શકાય.');
        }

        $model->requeue($id);

        return $this->back('app/whatsapp/messages')->with('success', 'Message ફરી કતારમાં મૂક્યો.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $model = new MessageModel();
        $row   = $model->find($id);

        if ($row === null || $row['status'] !== 'queued') {
            return $this->back('app/whatsapp/messages')->with('error', 'ફક્ત કતારમાંના messages રદ થઈ શકે.');
        }

        $model->cancel($id);

        if (! empty($row['campaign_id'])) {
            service('outbox')->refreshCampaign((int) $row['campaign_id']);
        }

        return $this->back('app/whatsapp/messages')->with('success', 'Message રદ થયો.');
    }

    /**
     * @return array{0: string, 1: string, 2: string} [label, badge class, key]
     */
    private function state(bool $configured, array $status): array
    {
        if (! $configured) {
            return ['Configure બાકી', 'badge-grey', 'unconfigured'];
        }
        if (! ($status['ok'] ?? false)) {
            return ['Bridge બંધ છે', 'badge-grey', 'down'];
        }
        if (! ($status['registered'] ?? false)) {
            return ['Link બાકી', 'badge-amber', 'unlinked'];
        }
        if (($status['connection'] ?? '') === 'open') {
            return ['જોડાયેલ', 'badge-green', 'open'];
        }

        return ['જોડાઈ રહ્યું છે…', 'badge-amber', 'connecting'];
    }

    private function home(): RedirectResponse
    {
        return redirect()->to(site_url('app/whatsapp'));
    }
}
