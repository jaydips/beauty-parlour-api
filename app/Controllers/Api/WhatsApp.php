<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\MessageModel;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Automatic WhatsApp (bridge) for the Flutter app.
 *   GET  /whatsapp/status          bridge state (+ pairing code / QR while linking), auto-send switch, outbox counts
 *   POST /whatsapp/pair  {phone}   pairing code for WhatsApp > Linked devices > Link with phone number
 *   POST /whatsapp/logout          unlink
 *   POST /whatsapp/test  {phone}   send one test message now
 * The switch itself is a setting: PUT /settings {"wa_auto_send": "1"}; send window: wa_send_from / wa_send_to.
 */
class WhatsApp extends ApiController
{
    public function status(): ResponseInterface
    {
        $bridge = service('whatsappBridge');
        $outbox = service('outbox');
        $status = $bridge->configured() ? $bridge->status() : ['ok' => false, 'error' => 'bridge_not_configured'];
        [$from, $to] = $outbox->sendWindow();

        return $this->respond([
            'bridge' => [
                'configured'   => $bridge->configured(),
                'ok'           => (bool) ($status['ok'] ?? false),
                'connection'   => $status['connection'] ?? null,
                'registered'   => (bool) ($status['registered'] ?? false),
                'me'           => $status['me'] ?? null,
                'pairing_code' => $status['pairing_code'] ?? null,
                'qr_png'       => $status['qr_png'] ?? null,
                'last_error'   => $status['last_error'] ?? ($status['error'] ?? null),
                'since'        => $status['since'] ?? null,
                'sent_total'   => (int) ($status['sent_total'] ?? 0),
            ],
            'auto_send'   => $outbox->autoSendEnabled(),
            'send_window' => ['from' => $from, 'to' => $to],
            'counts'      => (new MessageModel())->counts(),
        ]);
    }

    public function pair(): ResponseInterface
    {
        $data = $this->input();

        if (! $this->validateData($data, ['phone' => 'required|regex_match[/^[6-9][0-9]{9}$/]'])) {
            return $this->validationFailure();
        }

        $result = service('whatsappBridge')->pair((string) $data['phone']);

        if (empty($result['pairing_code'])) {
            return $this->fail(['error' => $result['error'] ?? 'no_pairing_code', 'me' => $result['me'] ?? null], ($result['http'] ?? 0) === 409 ? 409 : 502);
        }

        return $this->respond(['pairing_code' => $result['pairing_code'], 'instructions' => 'WhatsApp > Settings > Linked devices > Link a device > Link with phone number instead']);
    }

    public function logout(): ResponseInterface
    {
        $result = service('whatsappBridge')->logout();

        return ($result['ok'] ?? false) ? $this->respond(['ok' => true]) : $this->fail(['error' => $result['error'] ?? 'unknown'], 502);
    }

    public function test(): ResponseInterface
    {
        $data = $this->input();

        if (! $this->validateData($data, ['phone' => 'required|regex_match[/^[6-9][0-9]{9}$/]'])) {
            return $this->validationFailure();
        }

        $result = service('whatsappBridge')->send((string) $data['phone'], service('whatsapp')->testMessage());

        return ($result['ok'] ?? false)
            ? $this->respond(['ok' => true, 'id' => $result['id'] ?? null])
            : $this->fail(['error' => $result['error'] ?? 'unknown'], 502);
    }
}
