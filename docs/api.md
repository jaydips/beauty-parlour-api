# beauty-parlour-api - HTTP API reference (Phase 2)

Base URL (production): `https://delicate.jainamagrifood.com/`
All requests and responses are JSON (`Content-Type: application/json`). Times are local
(`Asia/Kolkata`) in the form `YYYY-MM-DD HH:MM:SS`; dates are `YYYY-MM-DD`; amounts are whole rupees
(integers); phone numbers are 10 digits without country code.

## Authentication

| Method | Path | Body | Notes |
|--------|------|------|-------|
| POST | `/auth/login` | `{phone, password, device?}` | Returns `{token, token_type: "Bearer", expires_in_days: 90, owner}`. 10 attempts per minute per IP. |
| POST | `/auth/logout` | | Revokes the current token. |
| GET | `/auth/me` | | `{owner, token: {id, name, expires_at}}` |
| PUT | `/auth/profile` | `{name, phone?, email?}` | |
| PUT | `/auth/password` | `{current_password, new_password}` | Logs out every other device. |
| GET | `/auth/devices` | | Issued tokens (no secrets). |
| DELETE | `/auth/devices/{id}` | | Revoke one token. |

Every other endpoint needs the header `Authorization: Bearer <token>`. Missing or invalid tokens
answer `401 {"status":401,"error":"Unauthorized"}`.

One-time setup of the owner login (deployment only): `POST /install/owner?key=<install.key>` with
`{name, phone, password, email?}`; answers 409 once an owner exists.

## Error format

Validation errors: `400 {"status":400,"error":400,"messages":{field: "message"}}`.
Not found: `404`. Conflicts (double booking, deleting an appointment with payments): `409`.
Unknown routes: `404 {"status":404,"error":"Not Found"}`.

## Dashboard

`GET /dashboard?date=YYYY-MM-DD` (default today)

```json
{
  "date": "2026-10-24", "now": "2026-10-24 09:30:00", "parlour": {"name": "..."},
  "appointments": {"counts": {"scheduled": 3, "confirmed": 1, "completed": 0, "cancelled": 0, "no_show": 0, "total": 4},
                   "list": [ {"id": 1, "starts_at": "...", "ends_at": "...", "status": "scheduled", "total": 1600,
                              "customer": {"id": 5, "name": "Asha", "phone": "9876543211"},
                              "items": [ {"service_name": "Haircut", "price": 500, "quantity": 1, "duration_minutes": 45} ] } ]},
  "upcoming_7_days": 9,
  "reminders_due": {"count": 2, "list": [ {"id": 7, "type": "appointment", "due_at": "...", "message": "...",
                                            "customer": {"id": 5, "name": "Asha", "phone": "9876543211"},
                                            "wa_link": "https://wa.me/919876543211?text=..."} ]},
  "revenue": {"today": {"payments": 1000, "refunds": 0, "net": 1000, "count": 1, "by_method": {"upi": 1000}},
              "month": {"payments": 42000, "refunds": 500, "net": 41500, "count": 31, "by_method": {"cash": 20000, "upi": 22000}}},
  "dues": {"count": 1, "total": 600, "list": [ {"appointment_id": 1, "customer": {...}, "starts_at": "...", "total": 1600, "paid": 1000, "balance": 600} ]},
  "counts": {"customers": 120, "active_services": 14}
}
```

## Settings

- `GET /settings` -> `{settings: {key: value}, editable_keys: [...]}`
- `PUT /settings` with any subset of keys: `parlour_name`, `parlour_phone`, `parlour_address`,
  `country_code`, `currency`, `timezone`, `opening_hours` (JSON), `slot_minutes`,
  `reminder_lead_hours`, `followup_days`, `tpl_whatsapp_reminder`, `tpl_whatsapp_followup`,
  `tpl_whatsapp_birthday`. Templates accept `{customer_name} {parlour_name} {date} {time} {services}`.

## Services (price list)

| Method | Path | Body / query |
|--------|------|--------------|
| GET | `/services?include_inactive=1` | active services by default |
| POST | `/services` | `{name, duration_minutes, price, category?, is_active?, sort_order?, notes?}` |
| GET | `/services/{id}` | |
| PUT | `/services/{id}` | any subset of the fields above |
| DELETE | `/services/{id}` | soft delete; history keeps the snapshot |

## Customers

| Method | Path | Body / query |
|--------|------|--------------|
| GET | `/customers?q=&page=&per_page=` | `q` matches name or phone; `{customers, pager: {page, per_page, total, pages}}` |
| POST | `/customers` | `{name, phone, alt_phone?, gender?, birthday?, notes?, whatsapp_opt_in?}`; a previously deleted phone restores the old record (200 + `restored: true`) |
| GET | `/customers/{id}` | with `stats: {visits, last_visit_at, total_spent, upcoming[]}` |
| PUT | `/customers/{id}` | |
| DELETE | `/customers/{id}` | soft delete |

## Appointments

| Method | Path | Body / query |
|--------|------|--------------|
| GET | `/appointments?date=YYYY-MM-DD` or `?from=&to=` plus `status=a,b`, `customer_id=`, `page=` | each row has `customer` and `items` |
| POST | `/appointments` | `{customer_id, starts_at, items: [...], discount?, notes?, source?, status?, force?}` |
| GET | `/appointments/{id}` | full detail: `customer, items, payments, paid, balance, reminders` |
| PUT | `/appointments/{id}` | same fields, all optional; `items` replaces the list |
| PATCH | `/appointments/{id}/status` | `{status: scheduled|confirmed|completed|cancelled|no_show, reason?}` |
| DELETE | `/appointments/{id}` | only without payments (409 otherwise) |

Items: `{service_id, quantity?, price?}` (price snapshot from the price list unless overridden) or a
custom line `{service_name, price, duration_minutes?, quantity?}`. `ends_at`, `subtotal` and
`total = subtotal - discount` are computed by the server. A slot that overlaps another
scheduled/confirmed/completed appointment answers `409 {"messages": {"message": "...", "conflicts": [...]}}`
unless `force: true`.

Side effects: creating or rescheduling an appointment for a customer with `whatsapp_opt_in = 1`
creates a pending `appointment` reminder `reminder_lead_hours` before the slot (or immediately if the
slot is closer than that). `completed` creates a `follow_up` reminder `followup_days` later and skips
the appointment reminder; `cancelled`/`no_show` cancel pending reminders.

## Payments

| Method | Path | Body / query |
|--------|------|--------------|
| GET | `/payments?from=&to=&customer_id=&appointment_id=&page=` | |
| POST | `/payments` | `{appointment_id? or customer_id, amount, method: cash|upi|card|other, type?: payment|refund, paid_at?, reference?, notes?}`; with `appointment_id` the response adds `appointment: {total, paid, balance}` |
| GET | `/payments/{id}` | |
| DELETE | `/payments/{id}` | data-entry corrections |

## Reminders (manual WhatsApp)

| Method | Path | Body / query |
|--------|------|--------------|
| GET | `/reminders?status=pending&due=today|overdue|all|YYYY-MM-DD&type=&page=` | each row has `customer`, `appointment` and `wa_link` |
| POST | `/reminders` | `{customer_id, message, due_at, appointment_id?}` (type `custom`) |
| GET | `/reminders/{id}` | |
| PATCH | `/reminders/{id}` | `{status: sent|skipped|cancelled|pending}` |
| POST | `/reminders/{id}/sent` | shortcut: mark as sent |

The app opens `wa_link` (`https://wa.me/<country_code><phone>?text=<message>`); WhatsApp opens on the
owner's phone with the text prefilled, the owner taps send, then the app calls `/reminders/{id}/sent`.
The server never sends messages.

## Reports

| Method | Path | Result |
|--------|------|--------|
| GET | `/reports/revenue?from=&to=&group=day|month` | `{series: [{period, payments, refunds, net, count}], totals: {..., by_method}}`; default range: current month to date |
| GET | `/reports/services?from=&to=` | top services from completed appointments: `{service_name, quantity, amount, appointments}` |
| GET | `/reports/dues` | every completed appointment with an outstanding balance |

## Operations

- `GET /health` (no auth): framework, PHP, environment, timezone, database driver and version.
- `GET /install/status?key=` and `GET /install/migrate?key=`: migration runner used by deployments.
