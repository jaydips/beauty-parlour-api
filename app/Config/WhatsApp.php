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
 *
 * Pacing (keeps the number off WhatsApp's spam radar): at most perRun messages per cron minute, a
 * random pause of gapSeconds..gapSecondsMax between two sends, offer recipients spread by a random
 * campaignGapSeconds..campaignGapSecondsMax each, nothing outside the owner's send window, at most
 * settings.wa_daily_cap sends per day, and no offer broadcasts until the number has been linked for
 * campaignWarmupHours.
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

    /** Shortest pause between two sends inside one run, in seconds. */
    public int $gapSeconds = 4;

    /** Longest pause between two sends inside one run; the actual pause is random in [gapSeconds, gapSecondsMax]. */
    public int $gapSecondsMax = 12;

    /** A message is marked failed after this many delivery attempts (bridge errors, not "offline"). */
    public int $maxAttempts = 3;

    /** Offer campaigns are spread out: one recipient every N seconds at least ... */
    public int $campaignGapSeconds = 30;

    /** ... and at most this many; the gap between two recipients is random in [campaignGapSeconds, campaignGapSecondsMax]. */
    public int $campaignGapSecondsMax = 90;

    /** Offer broadcasts wait until the number has been linked (settings.wa_linked_since) for this many hours. 0 = no warm-up. */
    public int $campaignWarmupHours = 24;

    /** Where campaign images are stored: a directory inside the web document root (null = FCPATH/uploads/campaigns). */
    public ?string $uploadsDir = null;

    /** Public path of that directory under the base URL. */
    public string $uploadsUrlPath = 'uploads/campaigns';

    /** Largest edge of a stored campaign image, in pixels (bigger uploads are resized, all are re-encoded as JPEG). */
    public int $imageMaxEdge = 1600;

    /** Largest accepted upload, in bytes. */
    public int $imageMaxBytes = 2 * 1024 * 1024;
}
