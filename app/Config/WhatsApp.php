<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Connection to the Node.js WhatsApp bridge (whatsapp-bridge/, Baileys) and pacing of the outbox.
 *
 * Override on the server through .env:
 *   whatsapp.bridgeUrl = http://127.0.0.1:3310
 *   whatsapp.bridgeKey = <the BRIDGE_KEY from whatsapp-bridge/.env.bridge>
 *
 * With an empty bridgeKey nothing is ever sent automatically; the manual wa.me links keep working.
 */
class WhatsApp extends BaseConfig
{
    /** Base URL of the bridge (loopback on the same server). */
    public string $bridgeUrl = 'http://127.0.0.1:3310';

    /** Shared secret sent as X-Bridge-Key. Empty = bridge not configured. */
    public string $bridgeKey = '';

    /** HTTP timeout per bridge call in seconds (a send can wait for the socket to reconnect). */
    public int $timeout = 40;

    /** Maximum messages sent per `spark whatsapp:send` run (the cron runs every minute). */
    public int $perRun = 8;

    /** Pause between two sends inside one run, in seconds (keeps the number off WhatsApp's radar). */
    public int $gapSeconds = 4;

    /** A message is marked failed after this many delivery attempts (bridge errors, not "offline"). */
    public int $maxAttempts = 3;

    /** Offer campaigns are spread out: one recipient every N seconds. */
    public int $campaignGapSeconds = 30;
}
