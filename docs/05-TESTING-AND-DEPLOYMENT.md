# Testing and deployment

## Tests

PHPUnit feature tests run against a real PostgreSQL database (`spider_plms_test`). Run them with:

```bash
php artisan test --compact
```

| Suite | Covers |
|---|---|
| `AuthTest` | Paid registration with a faked Razorpay (forged signature rejected, one workspace per order), reserved and duplicate names, email one-time codes for members and the Super Admin, cross-workspace refusal, suspended workspaces, case-insensitive workspace names |
| `ProjectTrackerTest` | Project onboarding, status calculation, change requests and revised deadlines, client dependencies, AI estimate with fallback, meetings and attachments, reports |
| `TenantIsolationTest` | A user of one tenant cannot read, change or write into another tenant's projects. **Must stay green.** |
| `PlatformAdminTest` | Countries, currencies, per-country pricing, adding tenants, system settings, platform stats, tenant detail, closed registration |
| `ClientCommunicationTest` | Clients and their isolation, AI and fallback drafts with the knowledge base, review before sending, failed delivery reasons, WhatsApp sending through Evolution, the token-protected webhook (no or wrong token rejected, redelivery, groups and own messages ignored), chat evaluation, settings permissions, the daily draft command |
| `SpaRoutesTest` | The React shell is served on browser URLs while `/api/*` stays JSON |

Rules for new tests: use factories, fake outbound HTTP (Razorpay, DeepSeek) and mail, and never call real services.

Frontend checks:

```bash
npx tsc -p tsconfig.app.json --noEmit    # type check
npm run build                            # type check and bundle
```

## Local development

```bash
cp .env.example .env            # set DB_*, MAIL_*, RAZORPAY_*, DEEPSEEK_*
composer install && npm install
php artisan key:generate
php artisan migrate --seed      # plans, countries, roles, the Super Admin
npm run build
php artisan serve --port=8010
```

`php artisan db:seed` creates the Super Admin and reference data only. It creates **no demo tenants**. Create a workspace through `/register` (Razorpay test mode) or from the Super Admin screen.

## Production

**Topology:** one PHP-FPM or Octane application behind a TLS-terminating web server, PostgreSQL, a queue worker, and an SMTP provider.

**`.env` essentials**

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
DB_CONNECTION=pgsql          # plus host, database, user, password
MAIL_MAILER=smtp             # plus host, port, username, password, from address
RAZORPAY_KEY_ID=rzp_live_...
RAZORPAY_KEY_SECRET=...
DEEPSEEK_API_KEY=...         # optional
EVOLUTION_API_URL=https://your-evolution-server
EVOLUTION_API_KEY=...         # the Evolution global API key
EVOLUTION_INSTANCE=spiderplms # this app's own instance; other instances are never touched
EVOLUTION_WEBHOOK_URL=https://your-domain/api/v1/webhooks/whatsapp
EVOLUTION_WEBHOOK_TOKEN=...  # any long random string; sent to Evolution inside the webhook address
QUEUE_CONNECTION=database
```

**Release steps**

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

**Scheduler.** Add one cron entry so the daily reminder drafts are prepared:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

**WhatsApp setup.** Fill the `EVOLUTION_*` values, then run `php artisan plms:whatsapp-setup --qr`. It creates the `EVOLUTION_INSTANCE` if it is missing, registers the webhook (the public address must reach `/api/v1/webhooks/whatsapp`; use a tunnel such as ngrok while testing), and saves a QR code to `storage/app/whatsapp-qr.html`. Scan it in WhatsApp, Linked devices. Settings shows *Connected* once the phone is linked. Without it the app still works: email drafts are unaffected.

**Checklist**

* Live Razorpay keys, not test keys.
* A valid CA bundle configured for PHP (`curl.cainfo`, `openssl.cafile`).
* SMTP sender verified, so one-time codes are delivered.
* HTTPS enforced, `APP_DEBUG=false`.
* The Super Admin email is correct. Sign-in is by emailed code only.

## Backup and recovery

* Back up PostgreSQL daily with `pg_dump` and keep copies off the server.
* Back up `storage/app/private` (meeting attachments).
* Test a restore regularly: restore into an empty database, run `php artisan migrate --force`, then sign in.

## Maintenance

* Update dependencies with `composer update` and `npm update` in a branch, then run the full test suite.
* The legacy 8-stage lifecycle tables were removed by migration `2026_11_16_000001_drop_legacy_lifecycle_tables`. That migration cannot be reversed. Restore from a backup taken before it if the old data is ever needed.
