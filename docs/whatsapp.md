# Automatic WhatsApp sending (Baileys bridge)

Phase 4 (2026-10-24). On top of the manual click-to-chat reminders, the backend can send WhatsApp
messages by itself: booking confirmations, appointment reminders, follow-ups, birthday wishes and
offer broadcasts. Sending goes through a small Node.js service, the **bridge**
(`whatsapp-bridge/`), which keeps one WhatsApp account linked with
[Baileys](https://github.com/WhiskeySockets/Baileys) (the WhatsApp Web protocol).

**Read this first.** Baileys is unofficial. WhatsApp may ask for a re-link at any time and can
block numbers that send bulk or unwanted messages. Therefore:

- use a number dedicated to the parlour, not the owner's personal number;
- send only to customers who agreed (`customers.whatsapp_opt_in`), which the outbox enforces;
- keep volumes modest: the outbox sends at most 8 messages per minute with 4-second gaps, offer
  broadcasts go out one recipient every 30 seconds, everything only inside the owner's send window
  (default 09:00–21:00);
- the whole feature stays **off** until the owner switches `wa_auto_send` on in the app.

**Owner's guide (Gujarati):** [whatsapp-owner-guide.gu.md](whatsapp-owner-guide.gu.md): pairing the
number, send window, switching on, reading the outbox. The same steps are shown on the app's
*ઑટો WhatsApp* page under "ઑટો WhatsApp ચાલુ કરવાનાં પગલાં".

**Language (2026-10-25).** Every message template defaults to Gujarati (`SettingsSeeder`), with
`{date}` rendered as "શનિવાર, 25 ઑક્ટોબર 2026" and `{time}` as "સવારે 10:30" (`format_helper.php`:
`gu_date()`, `gu_time()`). Migration `2026-10-25-100001` converts an existing installation: it
replaces a template only while its value is still the old English default, so edited templates
survive. Migration `2026-10-25-120001` then upgrades the plain Gujarati defaults to the current
texts with WhatsApp `*bold*` markup and emoji (same rule: untouched defaults only). WhatsApp renders
`*bold*`, `_italic_` and `~strikethrough~` in sent text and in `wa.me` prefilled messages alike. Text is UTF-8 end to end: SQLite stores it as is, the MySQL config uses `utf8mb4`
(`app/Config/Database.php`; an emoji in a template needs that too), `wa.me` links percent-encode
the UTF-8 bytes (`rawurlencode`), and the bridge receives JSON (`\uXXXX` escapes) and reads the
request body as one UTF-8 stream.

## 1. How it fits together

```
owner's app / web UI ──► beauty-parlour-api (PHP)  ──HTTP 127.0.0.1:3310──►  whatsapp-bridge (Node, Baileys)  ──►  WhatsApp
                                   │  messages table (outbox)                 │ auth_state/ = linked session
                                   └─ spark whatsapp:send (cron, every min) ─┘
```

| Piece | Where | Role |
|-------|-------|------|
| `messages` table | migration `2026-10-24-120002` | outbox: one row per message with status queued / sent / failed / cancelled, attempts, last_error, scheduled_at |
| `campaigns` table | migration `2026-10-24-120001` | one row per offer broadcast; its recipients are `messages` rows with `campaign_id` |
| `App\Libraries\Outbox` | `app/Libraries/Outbox.php` | queueing (booking confirmation, due reminders, campaigns) and delivery with pacing, retries, send window |
| `App\Libraries\WhatsAppBridge` | `app/Libraries/WhatsAppBridge.php` | HTTP client for the bridge (`status`, `pair`, `send`, `logout`, `restart`); never throws |
| `spark whatsapp:send` | `app/Commands/WhatsappSend.php` | the cron job: queue due reminders, send what is due |
| `Config\WhatsApp` | `app/Config/WhatsApp.php` | bridge URL/key (from `.env`), pacing limits |
| Web UI | `/app/whatsapp`, `/app/whatsapp/messages`, `/app/campaigns` | link the number, switch auto-send, test, outbox, offers |
| JSON API | `GET /whatsapp/status`, `POST /whatsapp/pair`, `/whatsapp/logout`, `/whatsapp/test`, `/messages…`, `/campaigns…` | same for the Flutter app (see `docs/api.md`) |
| Settings | `wa_auto_send` (0/1), `wa_send_from`, `wa_send_to`, `tpl_whatsapp_booking`, `tpl_whatsapp_offer` | owner-editable; the switch, the window and the bridge link state are also on `/app/settings` ("નવી બુકિંગ પર ઑટો WhatsApp") |

What gets queued, and when:

- **Booking confirmation**: when an appointment is created (web, API) and the customer opted in,
  one `booking` message (template `tpl_whatsapp_booking`), scheduled "now" (so it leaves with the
  next cron run inside the send window). Idempotent per appointment. The web UI confirms it in the
  flash message ("WhatsApp કન્ફર્મેશન કતારમાં મૂક્યું"). Follow-ups on the appointment
  (`AppointmentService` → `Outbox::cancelForAppointment()` / `refreshBookingConfirmation()`):
  changing the time or the services rewrites a confirmation that is still queued and drops a
  queued reminder for the old slot; cancelling or marking no-show cancels every queued message of
  the appointment; completing it cancels a queued confirmation/reminder. Sent messages are never
  touched, and nothing is queued retroactively when the switch is turned on later.
- **Reminders**: every pending reminder whose `due_at` has passed (appointment reminders created at
  booking time, follow-ups and birthdays created by `spark reminders:generate`) becomes one message
  the first time `whatsapp:send` sees it. When the bridge confirms delivery the reminder is marked
  `sent`; the owner's manual wa.me button stays available meanwhile.
- **Offers**: a campaign queues one personalised message per recipient (`{customer_name}`,
  `{parlour_name}`), spread 30 s apart.
- **Custom**: `POST /messages {customer_id, body}` or the web outbox.

Delivery rules (`Outbox::process`): nothing happens while `wa_auto_send` is `0` or outside the send
window; the bridge must report `registered` (linked). Bridge-side problems (`not_linked`,
`not_connected`, unreachable, rate limited) leave the batch queued for the next minute. A
`not_on_whatsapp` answer fails the message at once; other errors retry after 10 minutes, three times.

## 2. Server setup (cPanel, no SSH)

The host already runs Node 20 at `/opt/alt/alt-nodejs20/root/usr/bin/node` (used by the owner's
other apps); the bridge is started by a cron watchdog, not by Passenger.

1. **Deploy the code** as usual (`deploy/build-release.ps1` → `deploy/deploy-cpanel.sh`, or the
   GitHub Action). The release carries `whatsapp-bridge/` without `node_modules`, `auth_state`,
   `.env.bridge` and logs.
2. **Install the Node dependencies once** with a one-off cron job (cPanel » Cron Jobs; run it, check
   the log, delete the job):

   ```
   cd /home/<user>/beauty-parlour-api/whatsapp-bridge && /opt/alt/alt-nodejs20/root/usr/bin/npm install --omit=dev --no-audit --no-fund >> /home/<user>/tmp/npm-bridge.log 2>&1
   ```

   Repeat only when `package.json` changes. (`node_modules` survives deploys because the zip is
   extracted over the folder.)
3. **Create `whatsapp-bridge/.env.bridge`** (File Manager, permissions 0600) from
   `.env.bridge.example`: `BRIDGE_KEY` = 48+ random characters, `PORT=3310`.
4. **Add to the PHP `.env`**:

   ```
   whatsapp.bridgeUrl = http://127.0.0.1:3310
   whatsapp.bridgeKey = <the same BRIDGE_KEY>
   ```
5. **Cron jobs** (cPanel » Cron Jobs, both every minute):

   ```
   * * * * *  /bin/bash /home/<user>/beauty-parlour-api/whatsapp-bridge/start_bridge.sh
   * * * * *  /opt/cpanel/ea-php83/root/usr/bin/php /home/<user>/beauty-parlour-api/spark whatsapp:send >> /home/<user>/tmp/cron-whatsapp.log 2>&1
   ```

   The watchdog starts `server.js` when it is not running and writes `whatsapp-bridge/watchdog.log`
   and `bridge.log`. After a deploy that changes the bridge code, create an empty file
   `whatsapp-bridge/.restart` (File Manager); the next watchdog run restarts the process and keeps
   the WhatsApp session.
6. **Link the number** in the app: *વધુ » ઑટો WhatsApp* (`/app/whatsapp`). Enter the
   parlour's WhatsApp mobile, tap *Pairing code*, then on that phone: WhatsApp » Settings » Linked
   devices » Link a device » *Link with phone number instead* » type the 8-character code. A QR code
   is shown as well for linking from a second device. When the page shows *જોડાયેલ* (connected),
   send a test message to the owner's number, then switch **ઑટો WhatsApp** on (same page, or the
   checkbox at the top of *સેટિંગ્સ*).

Checks: `GET https://<host>/whatsapp/status` (bearer token) or the web page shows the bridge state;
`tmp/cron-whatsapp.log` shows one line per minute like
`[2026-10-24 10:31:00] reminders queued 2, sent 2, failed 0, deferred 0`.

Two more spark commands exist for checks from a one-off cron job (or locally):

```
php spark whatsapp:status                         # bridge link state, auto-send switch, outbox counts, what is due now
php spark whatsapp:test --to 98XXXXXXXX           # sends one real message immediately through the bridge
```

## 3. Operations

- **Re-link**: if the phone unlinks the device (or WhatsApp logs it out) the page shows *નંબર જોડવો બાકી*
  again; queued messages wait (`deferred`), nothing is lost.
- **Stop sending**: switch ઑટો WhatsApp off (messages stay queued) or cancel individual queued messages
  / a whole campaign from the outbox.
- **Failed messages** carry `last_error`; *ફરી મોકલો* (retry) puts them back in the queue.
- **Logs**: `whatsapp-bridge/bridge.log` (pino, level `warn`), `whatsapp-bridge/watchdog.log`,
  `~/tmp/cron-whatsapp.log`. Rotate or truncate them from File Manager now and then.
- **Session files** `whatsapp-bridge/auth_state/` (0700) are the WhatsApp login. Never download or
  commit them; `db:backup` does not include them. Unlinking deletes them.
- **Local development**: `cd whatsapp-bridge && npm install && BRIDGE_KEY=dev PORT=3310 node server.js`,
  then `whatsapp.bridgeKey = dev` in the local `.env`. The PHPUnit suite never talks to a real bridge
  (`tests/feature/WhatsAppOutboxTest.php` injects a fake through `Services::injectMock`).
