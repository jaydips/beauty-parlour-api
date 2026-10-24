<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\WhatsApp as WhatsAppConfig;
use Throwable;

/**
 * HTTP client for the Node.js WhatsApp bridge (whatsapp-bridge/server.js).
 * Every method returns the bridge's JSON as an array plus `http` (status code, 0 when unreachable);
 * it never throws, so the cron job and the UI can show the error instead of dying.
 */
class WhatsAppBridge
{
    private readonly WhatsAppConfig $config;

    public function __construct(?WhatsAppConfig $config = null)
    {
        $this->config = $config ?? config('WhatsApp');
    }

    /** True when a bridge URL and key are configured (the key lives in the server .env). */
    public function configured(): bool
    {
        return $this->config->bridgeKey !== '' && $this->config->bridgeUrl !== '';
    }

    public function status(): array
    {
        return $this->call('GET', '/status');
    }

    /**
     * Requests an 8-character pairing code for the given number (WhatsApp > Linked devices > Link with phone number).
     */
    public function pair(string $phone): array
    {
        return $this->call('POST', '/pair', ['phone' => $phone]);
    }

    public function send(string $phone, string $text): array
    {
        return $this->call('POST', '/send', ['to' => $phone, 'text' => $text]);
    }

    public function logout(): array
    {
        return $this->call('POST', '/logout');
    }

    public function restart(): array
    {
        return $this->call('POST', '/restart');
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function call(string $method, string $path, array $body = []): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'error' => 'bridge_not_configured', 'http' => 0];
        }

        try {
            $client  = service('curlrequest', ['baseURI' => rtrim($this->config->bridgeUrl, '/') . '/', 'timeout' => $this->config->timeout, 'http_errors' => false], null, null, false);
            $options = ['headers' => ['X-Bridge-Key' => $this->config->bridgeKey, 'Accept' => 'application/json']];

            if ($method === 'POST') {
                $options['json'] = $body;
            }

            $response = $client->request($method, ltrim($path, '/'), $options);
            $data     = json_decode((string) $response->getBody(), true);

            if (! is_array($data)) {
                $data = ['ok' => false, 'error' => 'bad_response'];
            }

            $data['http'] = $response->getStatusCode();

            return $data;
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'bridge_unreachable', 'detail' => $e->getMessage(), 'http' => 0];
        }
    }
}
