# UniNText — Multi-client SMS Portal

Laravel + MySQL + Tailwind SMS platform with:

- Platform administration (clients, credits ledger, sender IDs, providers, reports, audit logs)
- Client portal (send SMS, campaigns, contacts/Excel import, templates, billing, API keys)
- Priority queues + TPS budgets: `sms-otp` (10/s) → `sms-transactional` (6/s) → `sms-bulk` (4/s)
- Immutable credit ledger (never mutate balance without a ledger row)

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+
- Node.js 20+

## Setup

```bash
cp .env.example .env
php artisan key:generate

# Configure MySQL in .env, then:
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

### Client HTTP API

Base: `/api/v1` — auth with `X-Api-Key` + `X-Api-Secret` (create keys in **API & Webhooks**).

- `GET /api/v1/me` — balance + sender IDs  
- `POST /api/v1/sms` — queue SMS  
- `GET /api/v1/sms/{uuid}` — status  
- DLR webhooks: set webhook URL on the key; we POST signed `sms.dlr` events  

Docs are also shown in the client portal under **API & Webhooks**.

### Migrate clients from old Unitext

```bash
# 1) On a host that can reach the old Unitext MySQL (or via SSH tunnel):
php artisan unitxt:export-clients \
  --email=client@example.com \
  --with-contacts --contact-limit=500 \
  --file=storage/app/unitxt-pilot.json

# 2) Copy JSON to the new app server, then preview:
php artisan unitxt:import-clients --file=storage/app/unitxt-pilot.json --with-contacts --dry-run

# 3) Persist:
php artisan unitxt:import-clients --file=storage/app/unitxt-pilot.json --with-contacts --execute
```

Imports company profile, login (password hash), SMS balance (ledger credit), sender IDs, and optional contacts. API tokens are not migrated.

Queue workers (Redis, priority order or dedicated):

```bash
# Single worker (OTP checked first)
php artisan queue:work redis --queue=sms-otp,sms-transactional,sms-bulk

# Dedicated workers (recommended)
php artisan queue:work redis --queue=sms-otp
php artisan queue:work redis --queue=sms-transactional
php artisan queue:work redis --queue=sms-bulk
```

Scheduler (scheduled campaigns):

```bash
php artisan schedule:work
```

## Demo logins

| Portal | Email | Password |
|--------|-------|----------|
| Admin | `admin@unintext.test` | `password` |
| Client | `client@unintext.test` | `password` |

## Tests

```bash
php artisan test
```

## Release notes

**v1 (this release):** client management, credit ledger, sender IDs, single/bulk send, Excel import, queue priorities, delivery reports, templates, scheduling, audit logs.

**v2 (planned):** advanced invoicing, automatic provider failover, deep campaign analytics, Excel/PDF report packs.
