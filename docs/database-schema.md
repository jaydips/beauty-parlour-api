# Database schema - Phase 1 proposal

**Status: DRAFT v2 (owner decisions applied 2026-10-23), awaiting go-ahead for migrations. No migrations exist yet.**
Target: CodeIgniter 4.7.5, SQLite3 (hosting library **3.7.17**), must stay portable to MySQL/MariaDB.

## 1. Design rules

| Rule | Reason |
|------|--------|
| Tables plural `snake_case`; every entity table has `id INT auto-increment` | CI4 Model conventions; SQLite turns it into `INTEGER PRIMARY KEY AUTOINCREMENT` |
| `created_at`, `updated_at` (`DATETIME NULL`) on every entity table, filled by CI4 Model `useTimestamps` | No triggers needed, portable |
| Soft delete (`deleted_at DATETIME NULL`) on `services`; column also present on `customers` (behaviour still to be decided, see section 7) | History in appointments must survive; appointments are cancelled by status, not deleted |
| All amounts stored as **whole rupees, `INT`** (`price`, `amount`, `subtotal`, `discount`, `total`); no paise | Owner decision. Parlour prices are whole rupees; integers are exact and portable |
| All timestamps stored as `DATETIME` text `Y-m-d H:i:s` in **local time, `Asia/Kolkata`** (`appTimezone` will be set accordingly) | Owner decision. One location, India has no DST, "today's appointments" is a plain string range; reminders are due at local wall-clock times |
| Phone numbers stored as the **10-digit mobile number only**, digits, no `+`, no country code; column `VARCHAR(15)` leaves room, app validates exactly 10 digits | Owner decision. Click-to-chat links are built as `https://wa.me/{settings.country_code}{phone}` with `country_code` = `91` in `settings` |
| Status/type columns are `VARCHAR(20)` validated by CI4 Validation rules, never `ENUM` | SQLite has no ENUM; the Forge would silently turn it into TEXT; MySQL ENUM changes need ALTER |
| Booleans declared `BOOLEAN` | Forge maps to `INT` on SQLite and `TINYINT(1)` on MySQL; values 0/1 |
| Foreign keys declared inline at `CREATE TABLE` with explicit `ON DELETE` actions | `PRAGMA foreign_keys` is on in the connection config; portable |
| Anything likely to change (parlour name, hours, templates, lead times, country code) lives in the `settings` key/value table | Hosting SQLite cannot `modifyColumn()`/`dropColumn()`; key/value needs no schema change |
| Only plain and unique indexes; no partial, expression or JSON indexes | Not available in SQLite 3.7.17 |
| No `upsert()`, no raw SQL in migrations or models | Portability and the 3.7.17 limits (see README "Database") |
| Columns needed by later phases are added later with `ADD COLUMN` (works on 3.7.17) | Owner decision for `owner.password_hash`; keeps Phase 1 tables minimal |

## 2. Entity overview

```
owner (exactly 1 row)              settings (key -> value)

customers 1 ──< appointments 1 ──< appointment_services >── 1 services
    │                │
    │                └──< payments      (appointment_id nullable: advances, standalone sales)
    │                └──< reminders     (appointment_id nullable: follow-ups, birthdays)
    └──< payments
    └──< reminders
```

CI4 also creates its own `migrations` bookkeeping table (plain `CREATE TABLE`, fine on 3.7.17).

## 3. Tables

### 3.1 `owner` - the single login identity (profile only in Phase 1)

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | App enforces exactly one row (`id = 1`) |
| `name` | VARCHAR(100) | no | | |
| `phone` | VARCHAR(15) | no | | Owner's own 10-digit number |
| `email` | VARCHAR(190) | yes | NULL | |
| `created_at` / `updated_at` | DATETIME | yes | NULL | |

Phase 2 (authentication) adds `password_hash VARCHAR(255) NULL` and `last_login_at DATETIME NULL`
with `ADD COLUMN` migrations, plus an `auth_tokens` table.

### 3.2 `settings` - parlour configuration as key/value

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `key` | VARCHAR(64), PK | no | | e.g. `parlour_name` |
| `value` | TEXT | yes | NULL | Scalars as text, structured values as JSON text |
| `updated_at` | DATETIME | yes | NULL | |

Seeded keys (defaults, editable from the app): `parlour_name`, `parlour_phone`, `parlour_address`,
`country_code` (`91`), `currency` (`INR`), `timezone` (`Asia/Kolkata`), `opening_hours` (JSON per
weekday), `slot_minutes` (`15`), `reminder_lead_hours` (`24`), `followup_days` (`30`),
`tpl_whatsapp_reminder`, `tpl_whatsapp_followup`, `tpl_whatsapp_birthday` (message templates with
placeholders such as `{customer_name}`, `{date}`, `{time}`, `{services}`, `{parlour_name}`).

### 3.3 `services` - the price list

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | |
| `name` | VARCHAR(100) | no | | App rejects duplicate active names (no partial unique index on 3.7.17) |
| `category` | VARCHAR(50) | yes | NULL | Free text grouping: Hair, Skin, Bridal, ... |
| `duration_minutes` | INT | no | 30 | Used to compute `appointments.ends_at` |
| `price` | INT | no | 0 | Current list price, whole rupees |
| `is_active` | BOOLEAN | no | 1 | Hidden from booking when 0, still usable in history |
| `sort_order` | INT | no | 0 | Display order |
| `notes` | TEXT | yes | NULL | |
| `created_at` / `updated_at` / `deleted_at` | DATETIME | yes | NULL | Soft delete |

Indexes: `(is_active, sort_order)`.

### 3.4 `customers`

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | |
| `name` | VARCHAR(100) | no | | |
| `phone` | VARCHAR(15) | no | | **UNIQUE**, 10 digits |
| `alt_phone` | VARCHAR(15) | yes | NULL | 10 digits |
| `gender` | VARCHAR(10) | yes | NULL | free text / app list |
| `birthday` | DATE | yes | NULL | For birthday reminders; year may be unknown, app may store `1900-MM-DD` |
| `notes` | TEXT | yes | NULL | Allergies, preferences |
| `whatsapp_opt_in` | BOOLEAN | no | 1 | Owner's record of consent; reminders are generated only when 1 |
| `created_at` / `updated_at` | DATETIME | yes | NULL | |
| `deleted_at` | DATETIME | yes | NULL | Present in the schema either way; whether the app soft-deletes or hard-deletes customers is still open (section 7) |

Indexes: `UNIQUE (phone)`, `(name)`.
If soft delete is chosen: the unique index cannot exclude soft-deleted rows on 3.7.17, so re-adding
a deleted customer's phone must **restore** the old row instead of inserting (app logic).
If hard delete is chosen: deletion is blocked by the `RESTRICT` foreign keys whenever the customer
has appointments or payments, which protects history automatically.

### 3.5 `appointments`

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | |
| `customer_id` | INT, FK -> customers.id | **no** | | `ON DELETE RESTRICT ON UPDATE CASCADE`. Owner decision: every appointment needs a customer; walk-ins get a quick customer record |
| `starts_at` | DATETIME | no | | Local time |
| `ends_at` | DATETIME | no | | `starts_at` + sum of item durations; app recomputes |
| `status` | VARCHAR(20) | no | `scheduled` | `scheduled`, `confirmed`, `completed`, `cancelled`, `no_show` |
| `source` | VARCHAR(20) | yes | NULL | `walk_in`, `phone`, `whatsapp`, `app` |
| `notes` | TEXT | yes | NULL | |
| `subtotal` | INT | no | 0 | Sum of `appointment_services.price * quantity`, rupees, stored for fast lists |
| `discount` | INT | no | 0 | Rupees |
| `total` | INT | no | 0 | `subtotal - discount`, rupees, stored |
| `cancelled_at` | DATETIME | yes | NULL | |
| `cancel_reason` | VARCHAR(255) | yes | NULL | |
| `created_at` / `updated_at` | DATETIME | yes | NULL | No soft delete: cancellation is a status |

Indexes: `(starts_at)`, `(customer_id, starts_at)`, `(status, starts_at)`.
Overlap checking (double booking) is app logic: query appointments where
`starts_at < new.ends_at AND ends_at > new.starts_at AND status NOT IN ('cancelled','no_show')`.
Hard delete is allowed only for data-entry mistakes and only when no payments reference the row
(the `payments` FK is RESTRICT). No staff assignment in this release (owner decision).

### 3.6 `appointment_services` - line items with price snapshot

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | |
| `appointment_id` | INT, FK -> appointments.id | no | | `ON DELETE CASCADE` |
| `service_id` | INT, FK -> services.id | yes | NULL | `ON DELETE SET NULL`; history survives even a hard delete of the service |
| `service_name` | VARCHAR(100) | no | | Snapshot at booking time |
| `duration_minutes` | INT | no | | Snapshot |
| `price` | INT | no | | Snapshot, rupees; price changes never rewrite history |
| `quantity` | INT | no | 1 | |
| `created_at` | DATETIME | yes | NULL | |

Indexes: `(appointment_id)`, `(service_id)`.

### 3.7 `payments` - money received (and refunded)

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | |
| `customer_id` | INT, FK -> customers.id | no | | `ON DELETE RESTRICT` |
| `appointment_id` | INT, FK -> appointments.id | yes | NULL | `ON DELETE RESTRICT`. NULL for advances not yet linked or standalone sales |
| `type` | VARCHAR(10) | no | `payment` | `payment` or `refund`; amounts always positive |
| `method` | VARCHAR(10) | no | | `cash`, `upi`, `card`, `other` |
| `amount` | INT | no | | Whole rupees, > 0 (app validation) |
| `paid_at` | DATETIME | no | | Local time |
| `reference` | VARCHAR(100) | yes | NULL | UPI transaction id, card slip |
| `notes` | TEXT | yes | NULL | |
| `created_at` / `updated_at` | DATETIME | yes | NULL | |

Indexes: `(paid_at)`, `(appointment_id)`, `(customer_id)`.
Balance due per appointment = `appointments.total - SUM(payments) + SUM(refunds)`; computed, not stored.

### 3.8 `reminders` - the owner's manual WhatsApp to-do list

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | INT, PK, auto | no | | |
| `customer_id` | INT, FK -> customers.id | no | | `ON DELETE CASCADE` |
| `appointment_id` | INT, FK -> appointments.id | yes | NULL | `ON DELETE CASCADE` |
| `type` | VARCHAR(20) | no | | `appointment`, `follow_up`, `birthday`, `custom` |
| `due_at` | DATETIME | no | | When the app should surface it, local time |
| `channel` | VARCHAR(10) | no | `whatsapp` | Only `whatsapp` in this release |
| `message` | TEXT | no | | Rendered from the template at creation; snapshot |
| `status` | VARCHAR(10) | no | `pending` | `pending`, `sent`, `skipped`, `cancelled` |
| `sent_at` | DATETIME | yes | NULL | Set when the owner taps "mark as sent" |
| `created_at` / `updated_at` | DATETIME | yes | NULL | |

Indexes: `(status, due_at)`, `(appointment_id)`, `(customer_id)`.

Flow (no automation): the API returns due `pending` reminders together with a click-to-chat URL
`https://wa.me/{settings.country_code}{customers.phone}?text={urlencoded message}`. The owner taps
it, WhatsApp opens on the owner's phone with the text prefilled, the owner presses send, then marks
the reminder as sent in the app. The server never sends anything. Appointment reminders are created
when an appointment is saved (lead time from `settings.reminder_lead_hours`); follow-up and birthday
reminders are created by a daily `spark` command run from a cPanel cron job (later phase).

## 4. Typical reports (all plain `GROUP BY`, portable, no window functions)

- Revenue per day/month: `SUM(amount)` from `payments` grouped by `DATE(paid_at)`, payments minus refunds.
- Revenue by method: group `payments` by `method`.
- Top services: join `appointment_services` with completed appointments, group by `service_name`.
- Appointment counts by status per period.
- Pending dues: completed appointments where `total` exceeds paid total.
- Customer visit frequency and last visit: group `appointments` by `customer_id` with `MAX(starts_at)`.

## 5. Not in Phase 1 (possible later tables; each is a plain `CREATE TABLE` or `ADD COLUMN`)

- Phase 2 authentication: `owner.password_hash`, `owner.last_login_at` (ADD COLUMN) and an `auth_tokens` table for Flutter app sessions.
- `staff` and `appointments.staff_id` if bookings must later be assigned to beauticians (not in this release, owner decision).
- `products` / `expenses` if the owner wants stock or expense reports.
- Multi-location or customer-facing features: out of scope for this release.

## 6. Proposed migration order (after go-ahead)

1. `owner`  2. `settings`  3. `services`  4. `customers`  5. `appointments`
6. `appointment_services`  7. `payments`  8. `reminders`
Then one seeder for `settings` defaults. No data rows for owner/services/customers are seeded.
Alongside the migrations: `app/Config/App.php` `$appTimezone = 'Asia/Kolkata'` (decision 1).

## 7. Owner decisions (2026-10-23)

| # | Topic | Decision |
|---|-------|----------|
| 1 | Time storage | **Local time, `Asia/Kolkata`** |
| 2 | Money | **Whole rupees, `INT`** |
| 3 | Phone format | **10 digits, no `+`, no country code**; `settings.country_code` = `91` for wa.me |
| 4 | Customers: soft delete with restore, or hard delete only | **Still open.** Schema keeps `deleted_at`; either behaviour can be implemented later without a schema change |
| 5 | `appointments.customer_id` | **Mandatory** |
| 6 | `owner.password_hash` | **Phase 2 via `ADD COLUMN`**, not in Phase 1 |
| 7 | Staff assignment | **Not in this release** |
