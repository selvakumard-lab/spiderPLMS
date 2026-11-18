# Spider PLMS: project tracking for delivery teams (SaaS)

A multi-tenant SaaS that tells a delivery team whether each client project is **on time, why it is late, and what to expect next**. Teams log daily activity, meetings, change requests and client dependencies. The system works out the status, separates client-side from internal delay, moves the deadline for approved changes, estimates the next step with AI, and produces PDF and Excel reports.

**What you get:** animated landing page with country-aware pricing · light and dark mode · email one-time-code sign-in for everyone · Razorpay-paid workspace sign-up · Super Admin platform (tenants, countries, currencies, plans, system settings) · the project tracker · client master data · AI-drafted client emails and WhatsApp messages (reviewed before sending) · WhatsApp chat evaluation · a workspace knowledge base for the AI.

**One Laravel application.** React lives inside Laravel (`resources/js`), is bundled by Laravel's Vite plugin and is served through a single Blade shell.

| Layer | Technology |
|---|---|
| Application | Laravel 13 (PHP 8.4) · Sanctum (API tokens) · spatie/laravel-permission |
| UI (inside Laravel) | React 19 · TypeScript · Tailwind CSS 4 · React Router 8 · TanStack Query 5 · Montserrat · Vite 8 + `laravel-vite-plugin` |
| Database | PostgreSQL |
| Integrations | SMTP (login codes, client emails) · Razorpay (payments) · DeepSeek (AI, optional) · WhatsApp via Evolution API (optional) |

```
spiderPLMS/                      ← one Laravel project
├── app/                         Models, controllers (Api/V1), services, tenancy
├── config/plms.php              Platform settings + role/permission matrix
├── database/                    Migrations, factories, seeders
├── resources/
│   ├── css/app.css              Tailwind + design tokens (Montserrat, brand colours)
│   ├── js/                      React app (app.tsx entry, router, pages, components)
│   └── views/app.blade.php      The single HTML shell that boots React
├── routes/
│   ├── api.php                  JSON API  → /api/v1/*
│   └── web.php                  "/" and every other browser URL → React
├── tests/Feature/               PHPUnit feature tests (PostgreSQL)
├── public/build/                Compiled React assets (npm run build)
├── vite.config.ts               Laravel Vite plugin + React + Tailwind
└── docs/                        Product and technical documentation
```

## How a request flows

```
GET /, /login, /app/projects/6 …  → routes/web.php → app.blade.php → React Router renders the page
GET/POST /api/v1/…                 → routes/api.php → controllers → JSON
```

## Quick start (local)

**Prerequisites:** PHP ≥ 8.3 with `pdo_pgsql`, Composer 2, Node ≥ 20, PostgreSQL ≥ 15.

> On this machine use PHP 8.4 at `D:\php84\php.exe` — XAMPP's PHP 8.2 is too old for Laravel 13 (so the site can't run under XAMPP's Apache). Port **8000 is used by another local project**, so Spider PLMS runs on **8010**.

**Shortcuts (Windows):** `php.bat` in the project root makes `php artisan …` run PHP 8.4 when you use cmd.exe from this folder, and `serve.bat` starts the site on port 8010. (In PowerShell, call `D:\php84\php.exe` directly or run `.\serve.bat`.)

```bash
createdb -U postgres spider_plms
createdb -U postgres spider_plms_test        # used by the test suite

cp .env.example .env                          # set DB_*, MAIL_*, RAZORPAY_*, DEEPSEEK_*
composer install
npm install
php artisan key:generate
php artisan migrate --seed                    # plans, countries, roles, the Super Admin (no demo data)
npm run build                                 # compile React into public/build

php artisan serve --port=8010                 # open http://127.0.0.1:8010  → landing page
```

**While developing UI** (hot reload), run both in two terminals:

```bash
php artisan serve --port=8010
npm run dev                                   # Laravel detects public/hot and serves React from Vite with Fast Refresh
```
Still browse to **http://127.0.0.1:8010** — never to the Vite port.

### Accounts (sign-in is always an e-mail one-time code)

| Where | Email | Role |
|---|---|---|
| `/superAdmin` | selvakumar.d@spiderindia.net | Super Admin (platform) |
| `/spiderIndia` | spiderindia6@gmail.com | Organization Admin of the live tenant |

### Useful commands

```bash
php artisan test --compact        # feature tests: auth, tracker, tenant isolation, platform admin, SPA routes
vendor/bin/pint                   # PHP code style
npx tsc -p tsconfig.app.json --noEmit   # TypeScript check
npm run build                     # type-check + compile React
```

PHP needs a CA certificate bundle for HTTPS calls (`curl.cainfo` and `openssl.cafile` in `php.ini`), otherwise Razorpay, DeepSeek and SMTP over TLS fail.

## Documentation

| # | Document | Contents |
|---|---|---|
| 0 | [Overview](docs/00-OVERVIEW.md) | What the product does and what it tracks |
| 1 | [Architecture](docs/01-ARCHITECTURE.md) | Stack, multi-tenancy, data model, integrations |
| 2 | [Sign-in and access](docs/02-SIGN-IN-AND-ACCESS.md) | URLs, email one-time codes, Razorpay registration, roles |
| 3 | [Project tracking](docs/03-PROJECT-TRACKING.md) | How status, delay and deadlines are calculated |
| 4 | [API reference](docs/04-API-REFERENCE.md) | Every endpoint |
| 5 | [Testing and deployment](docs/05-TESTING-AND-DEPLOYMENT.md) | Tests, production setup, backup |
