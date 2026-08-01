# UniNText — Multi-client SMS Portal

Laravel + MySQL + Tailwind SMS platform with:

- Platform administration (clients, credits ledger, sender IDs, providers, reports, audit logs)
- Client portal (send SMS, campaigns, contacts/Excel import, templates, billing, API keys)
- Priority queues: `sms-otp` → `sms-transactional` → `sms-alerts` → `sms-bulk`
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

Queue workers (priority order):

```bash
php artisan queue:work --queue=sms-otp,sms-transactional,sms-alerts,sms-bulk,default
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
