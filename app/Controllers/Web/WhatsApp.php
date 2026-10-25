<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\MessageModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Automatic WhatsApp (bridge) screens.
 *   GET  /app/whatsapp                   bridge state, linking (pairing code / QR), auto-send switch, outbox counts, owner guide
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
            'title'        => lang('App.title_whatsapp'),
            'configured'   => $configured,
            'status'       => $status,
            'state'        => self::state($configured, $status),
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
            return $this->home()->with('error', lang('App.enter_whatsapp_mobile'));
        }

        $result = service('whatsappBridge')->pair($phone);

        if (! empty($result['pairing_code'])) {
            return $this->home()->with('pairing_code', (string) $result['pairing_code'])
                ->with('success', lang('App.pairing_code_received'));
        }

        if (($result['error'] ?? '') === 'already_linked') {
            return $this->home()->with('error', lang('App.already_linked', [(string) ($result['me'] ?? '')]));
        }

        return $this->home()->with('error', lang('App.pairing_failed', [(string) ($result['error'] ?? lang('App.unknown'))]));
    }

    public function logout(): RedirectResponse
    {
        $result = service('whatsappBridge')->logout();
        $ok     = (bool) ($result['ok'] ?? false);

        return $this->home()->with($ok ? 'success' : 'error', $ok ? lang('App.unlinked') : lang('App.unlink_failed', [(string) ($result['error'] ?? lang('App.unknown'))]));
    }

    public function restart(): RedirectResponse
    {
        $result = service('whatsappBridge')->restart();
        $ok     = (bool) ($result['ok'] ?? false);

        return $this->home()->with($ok ? 'success' : 'error', $ok ? lang('App.restarting') : lang('App.bridge_unreachable', [(string) ($result['error'] ?? lang('App.unknown'))]));
    }

    public function toggle(): RedirectResponse
    {
        $on = (string) ($this->input()['enabled'] ?? '0') === '1';
        (new SettingModel())->setValue('wa_auto_send', $on ? '1' : '0');

        return $this->home()->with('success', $on ? lang('App.auto_send_on') : lang('App.auto_send_off'));
    }

    public function test(): RedirectResponse
    {
        $phone = preg_replace('/\D+/', '', (string) ($this->input()['phone'] ?? '')) ?? '';

        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return $this->home()->with('error', lang('App.enter_mobile_10'));
        }

        $result = service('whatsappBridge')->send($phone, service('whatsapp')->testMessage());
        $ok     = (bool) ($result['ok'] ?? false);

        return $this->home()->with($ok ? 'success' : 'error', $ok ? lang('App.test_sent', [$phone]) : lang('App.not_sent', [(string) ($result['error'] ?? lang('App.unknown'))]));
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
            'title'    => lang('App.title_outbox'),
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
            return $this->back('app/whatsapp/messages')->with('error', lang('App.message_not_found'));
        }

        if (! in_array($row['status'], ['failed', 'cancelled'], true)) {
            return $this->back('app/whatsapp/messages')->with('error', lang('App.retry_only_failed'));
        }

        $model->requeue($id);

        return $this->back('app/whatsapp/messages')->with('success', lang('App.message_requeued'));
    }

    public function cancel(int $id): RedirectResponse
    {
        $model = new MessageModel();
        $row   = $model->find($id);

        if ($row === null || $row['status'] !== 'queued') {
            return $this->back('app/whatsapp/messages')->with('error', lang('App.cancel_only_queued'));
        }

        $model->cancel($id);

        if (! empty($row['campaign_id'])) {
            service('outbox')->refreshCampaign((int) $row['campaign_id']);
        }

        return $this->back('app/whatsapp/messages')->with('success', lang('App.message_cancelled'));
    }

    /**
     * Link state of the bridge for other pages (the settings card): asks the bridge once.
     *
     * @return array{0: string, 1: string, 2: string} [label, badge class, key]
     */
    public static function bridgeState(): array
    {
        $bridge     = service('whatsappBridge');
        $configured = $bridge->configured();

        return self::state($configured, $configured ? $bridge->status() : ['ok' => false, 'error' => 'bridge_not_configured']);
    }

    /**
     * @return array{0: string, 1: string, 2: string} [label, badge class, key]
     */
    private static function state(bool $configured, array $status): array
    {
        if (! $configured) {
            return [lang('App.state_unconfigured'), 'badge-grey', 'unconfigured'];
        }
        if (! ($status['ok'] ?? false)) {
            return [lang('App.state_down'), 'badge-grey', 'down'];
        }
        if (! ($status['registered'] ?? false)) {
            return [lang('App.state_unlinked'), 'badge-amber', 'unlinked'];
        }
        if (($status['connection'] ?? '') === 'open') {
            return [lang('App.state_open'), 'badge-green', 'open'];
        }

        return [lang('App.state_connecting'), 'badge-amber', 'connecting'];
    }

    private function home(): RedirectResponse
    {
        return redirect()->to(site_url('app/whatsapp'));
    }
}
