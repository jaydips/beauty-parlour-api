/**
 * beauty-parlour-api - WhatsApp bridge (Baileys = WhatsApp Web protocol).
 *
 * A small HTTP service that keeps ONE linked WhatsApp account connected and sends
 * text messages for the PHP backend (booking confirmations, reminders, offers).
 * It listens on loopback only; the PHP app on the same server is its only client.
 * Started and kept alive by start_bridge.sh from a cPanel cron job (no SSH on the host).
 *
 *   GET  /health                 no key - liveness for the watchdog
 *   GET  /status                 {connection, registered, me, qr, qr_png, pairing_code, last_error, since, sent_total}
 *   POST /pair    {phone}        link by phone number -> 8-character pairing code (WhatsApp > Linked devices)
 *   POST /send    {to, text}     send one text message (to = 10-digit Indian mobile or 91XXXXXXXXXX)
 *   POST /logout                 unlink the WhatsApp account (forgets the session)
 *   POST /restart                exit cleanly; the watchdog starts a fresh process (session is kept)
 *
 * Every endpoint except /health needs the header `X-Bridge-Key: <BRIDGE_KEY>`.
 *
 * Environment (.env.bridge, loaded by start_bridge.sh): BRIDGE_KEY (required, long random string,
 * same as whatsapp.bridgeKey in the PHP .env), PORT (default 3310), HOST (default 127.0.0.1),
 * WA_AUTH_DIR (default ./auth_state - the WhatsApp session, keep private), LOG_LEVEL (default warn),
 * WA_MAX_PER_MINUTE (default 10).
 *
 * Baileys is unofficial. Use a WhatsApp number dedicated to the parlour, keep volumes modest
 * (the PHP side paces sends and respects a daily send window) and expect WhatsApp to ask for a
 * re-link now and then. Incoming messages are ignored on purpose.
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import makeWASocket, { Browsers, DisconnectReason, fetchLatestBaileysVersion, useMultiFileAuthState } from 'baileys';
import pino from 'pino';
import QRCode from 'qrcode';

import { normalizeIndianMobile, keyMatches, RateLimiter } from './lib.js';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const AUTH_DIR = process.env.WA_AUTH_DIR ? path.resolve(DIR, process.env.WA_AUTH_DIR) : path.join(DIR, 'auth_state');
const KEY = process.env.BRIDGE_KEY || '';
const PORT = Number(process.env.PORT || 3310);
const HOST = process.env.HOST || '127.0.0.1';
const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });
const limiter = new RateLimiter(Number(process.env.WA_MAX_PER_MINUTE || 10), 60_000);

const status = {
  connection: 'close',
  registered: false,
  me: null,
  qr: null,
  qr_png: null,
  pairing_code: null,
  last_error: null,
  since: null,
  sent_total: 0,
  started_at: new Date().toISOString(),
};
let sock = null;
let starting = null;
let pairPhone = null;
let retries = 0;

async function start() {
  if (starting) return starting;
  starting = (async () => {
    fs.mkdirSync(AUTH_DIR, { recursive: true, mode: 0o700 });
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    let version;
    try {
      ({ version } = await fetchLatestBaileysVersion());
    } catch {
      // fall back to the version bundled with Baileys
    }

    const s = makeWASocket({
      auth: state,
      logger,
      browser: Browsers.ubuntu('Chrome'),
      markOnlineOnConnect: false,
      syncFullHistory: false,
      // This number only sends: no history sync, no message handlers.
      shouldSyncHistoryMessage: () => false,
      ...(version ? { version } : {}),
    });
    sock = s;
    status.registered = Boolean(state.creds.registered);
    status.connection = 'connecting';

    s.ev.on('creds.update', saveCreds);
    s.ev.on('connection.update', async (u) => {
      if (u.qr) {
        status.qr = u.qr;
        status.connection = 'waiting_for_link';
        try {
          status.qr_png = await QRCode.toDataURL(u.qr, { margin: 1, width: 280 });
        } catch {
          status.qr_png = null;
        }
        if (pairPhone && !s.authState.creds.registered) {
          const phone = pairPhone;
          pairPhone = null;
          try {
            status.pairing_code = await s.requestPairingCode(phone);
          } catch (e) {
            status.last_error = `pairing failed: ${e?.message ?? e}`;
          }
        }
      }
      if (u.connection === 'open') {
        retries = 0;
        status.connection = 'open';
        status.registered = true;
        status.me = s.user?.id?.split(':')[0]?.split('@')[0] ?? null;
        status.qr = null;
        status.qr_png = null;
        status.pairing_code = null;
        status.last_error = null;
        status.since = new Date().toISOString();
      }
      if (u.connection === 'close') {
        const code = u.lastDisconnect?.error?.output?.statusCode;
        status.connection = 'close';
        status.last_error = u.lastDisconnect?.error?.message ?? null;
        sock = null;
        starting = null;
        if (code === DisconnectReason.loggedOut) {
          // Unlinked from the phone: forget the session, show a fresh QR / pairing code next time.
          fs.rmSync(AUTH_DIR, { recursive: true, force: true });
          status.registered = false;
          status.me = null;
        }
        retries += 1;
        setTimeout(() => start().catch((e) => logger.error(e)), Math.min(30_000, 1000 * 2 ** Math.min(retries, 5)));
      }
    });
  })();
  try {
    await starting;
  } catch (e) {
    starting = null;
    status.last_error = String(e?.message ?? e);
    throw e;
  }
  return starting;
}

/** Waits until the socket is open (or gives up). */
async function waitOpen(ms) {
  const until = Date.now() + ms;
  while (Date.now() < until) {
    if (status.connection === 'open' && sock) return true;
    if (!sock && !starting) start().catch(() => {});
    await new Promise((r) => setTimeout(r, 250));
  }
  return status.connection === 'open' && Boolean(sock);
}

// ---- HTTP -------------------------------------------------------------------------

function reply(res, code, body) {
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(body));
}

function readJson(req) {
  return new Promise((resolve, reject) => {
    let data = '';
    req.on('data', (chunk) => {
      data += chunk;
      if (data.length > 16_384) {
        reject(new Error('body too large'));
        req.destroy();
      }
    });
    req.on('end', () => {
      try {
        resolve(data === '' ? {} : JSON.parse(data));
      } catch {
        reject(new Error('invalid JSON'));
      }
    });
    req.on('error', reject);
  });
}

async function handle(req, res) {
  const url = new URL(req.url, 'http://localhost');
  const route = '/' + (url.pathname.replace(/\/+$/, '').split('/').pop() || '');

  if (req.method === 'GET' && route === '/health') {
    return reply(res, 200, { ok: true, configured: KEY !== '', connection: status.connection, registered: status.registered });
  }
  if (KEY === '') return reply(res, 500, { ok: false, error: 'BRIDGE_KEY is not set' });
  if (!keyMatches(req.headers['x-bridge-key'], KEY)) return reply(res, 401, { ok: false, error: 'unauthorized' });

  if (req.method === 'GET' && route === '/status') {
    if (!sock && !starting) start().catch(() => {});
    return reply(res, 200, { ok: true, ...status });
  }

  if (req.method === 'POST' && route === '/pair') {
    const body = await readJson(req);
    const phone = normalizeIndianMobile(body.phone);
    if (!phone) return reply(res, 422, { ok: false, error: 'invalid_phone' });
    if (status.registered && status.connection === 'open') return reply(res, 409, { ok: false, error: 'already_linked', me: status.me });
    status.pairing_code = null;
    pairPhone = phone;
    if (!sock && !starting) start().catch(() => {});
    // The code is requested on the next "qr" event; wait for it.
    const until = Date.now() + 20_000;
    while (Date.now() < until && !status.pairing_code) {
      if (sock && status.qr && pairPhone === phone && !sock.authState.creds.registered) {
        pairPhone = null;
        try {
          status.pairing_code = await sock.requestPairingCode(phone);
        } catch (e) {
          status.last_error = `pairing failed: ${e?.message ?? e}`;
          break;
        }
      }
      await new Promise((r) => setTimeout(r, 250));
    }
    return status.pairing_code
      ? reply(res, 200, { ok: true, pairing_code: status.pairing_code })
      : reply(res, 504, { ok: false, error: status.last_error || 'no_pairing_code' });
  }

  if (req.method === 'POST' && route === '/send') {
    const body = await readJson(req);
    const to = normalizeIndianMobile(body.to);
    const text = typeof body.text === 'string' ? body.text.slice(0, 2000) : '';
    if (!to || text === '') return reply(res, 422, { ok: false, error: 'invalid_request' });
    if (!limiter.take()) return reply(res, 429, { ok: false, error: 'rate_limited' });
    if (!status.registered) return reply(res, 503, { ok: false, error: 'not_linked' });
    if (!(await waitOpen(25_000))) return reply(res, 503, { ok: false, error: 'not_connected' });

    const jid = `${to}@s.whatsapp.net`;
    try {
      const [check] = await sock.onWhatsApp(jid);
      if (!check?.exists) return reply(res, 404, { ok: false, error: 'not_on_whatsapp' });
      const sent = await sock.sendMessage(check.jid || jid, { text });
      status.sent_total += 1;
      return reply(res, 200, { ok: true, id: sent?.key?.id ?? null });
    } catch (e) {
      logger.error({ err: e }, 'send failed');
      return reply(res, 502, { ok: false, error: String(e?.message ?? e) });
    }
  }

  if (req.method === 'POST' && route === '/logout') {
    try {
      if (sock) await sock.logout();
    } catch {
      // already gone
    }
    fs.rmSync(AUTH_DIR, { recursive: true, force: true });
    Object.assign(status, { connection: 'close', registered: false, me: null, qr: null, qr_png: null, pairing_code: null });
    return reply(res, 200, { ok: true });
  }

  if (req.method === 'POST' && route === '/restart') {
    reply(res, 200, { ok: true, restarting: true });
    setTimeout(() => process.exit(0), 300);
    return undefined;
  }

  return reply(res, 404, { ok: false, error: 'not_found' });
}

http
  .createServer((req, res) => {
    handle(req, res).catch((e) => reply(res, 400, { ok: false, error: String(e?.message ?? e) }));
  })
  .listen(PORT, HOST, () => {
    logger.warn(`WhatsApp bridge listening on ${HOST}:${PORT}`);
    // Only reconnect automatically when a session already exists; linking starts from /status or /pair.
    if (fs.existsSync(path.join(AUTH_DIR, 'creds.json'))) start().catch((e) => logger.error(e));
  });

// A stray promise rejection inside Baileys must not kill the process.
process.on('unhandledRejection', (e) => logger.error({ err: e }, 'unhandled rejection'));
