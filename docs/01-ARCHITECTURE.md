# Architecture

## Stack

| Layer | Technology |
|---|---|
| Application | Laravel 13, PHP 8.4, Sanctum (API tokens), spatie/laravel-permission |
| UI | React 19, TypeScript, Tailwind CSS 4, React Router, TanStack Query, Vite |
| Database | PostgreSQL |
| Mail | SMTP (one-time codes, client emails) |
| WhatsApp | Self-hosted Evolution API |
| Payments | Razorpay Orders API and Checkout |
| AI | DeepSeek chat API (optional) |

One Laravel application serves everything. `routes/web.php` returns a single Blade shell for every browser URL and React Router renders the page. JSON lives under `/api/v1`.

```
app/Http/Controllers/Api/V1
├── AuthController, OtpAuthController      sign-in, session
├── RegistrationPaymentController          paid sign-up (Razorpay)
├── PublicController, PlanController       landing page data, pricing
├── ProfileController
├── ClientController, WorkspaceSettingsController   client master data, knowledge base and settings
├── WhatsAppWebhookController              Evolution API webhook: incoming messages
├── Admin/*                                Super Admin: tenants, plans, countries, currencies, settings, stats
└── Tracker/*                              projects, activities, meetings, change requests, dependencies, reports
app/Services
├── ProjectTrackerService                  status, delay and deadline rules
├── ProjectReportService                   PDF and Excel reports
├── DeepSeekService                        AI estimate and general JSON completions, with fallback
├── ClientCommunicationService             drafts and sends client emails and WhatsApp messages
├── ChatEvaluationService                  evaluates a project's WhatsApp chat
├── WhatsAppService                        Evolution API client, webhook token check
├── RazorpayService                        order creation and signature check
└── TenantRegistrationService              workspace + admin + subscription in one transaction
```

## Multi-tenancy

One database, one schema, a `tenant_id` on every tenant-owned row.

* `BelongsToTenant` adds a global scope and stamps `tenant_id` on create, using the request's `TenantContext`.
* `ResolveTenant` middleware binds the context for each authenticated request:
  * a tenant user is **always** bound to their own tenant. The `X-Tenant` header is ignored;
  * a Super Admin may send `X-Tenant: {slug}` to act inside a tenant, and gets `409 tenant_required` on workspace routes without it.
* A suspended or expired workspace returns `402 tenant_inactive` to its members.
* Workspace slugs are matched case-insensitively and are camelCase by convention.

`TenantIsolationTest` proves a user of tenant A cannot read, change or detect tenant B's data. It must stay green.

## Data model

```
plans ── plan_prices (per country)        countries ── currencies
tenants ── subscriptions (gateway, gateway_subscription_id)
tenants ── users ── login_otps
tenants ── clients ── projects (client_id)
client_messages (email and WhatsApp: drafts, sent, received)   chat_evaluations (per project)
tenants ── projects ┬─ project_activities
                    ├─ project_meetings ── meeting_attachments
                    ├─ change_requests
                    └─ client_dependencies
activity_logs (audit trail)     system_settings (platform key/value)
```

Meeting attachments are stored on the private disk under `tenants/{tenantId}/...` and served only through an authenticated download route.

## Integrations

| Service | Used for | Configuration |
|---|---|---|
| SMTP | One-time sign-in codes | `MAIL_*` |
| Razorpay | Workspace sign-up payment | `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` |
| DeepSeek | Next-step estimate, client message drafts, chat evaluation | `DEEPSEEK_API_KEY`, `DEEPSEEK_MODEL`, `DEEPSEEK_BASE_URL` |
| Evolution API (WhatsApp) | Send client messages, receive replies by webhook | `EVOLUTION_API_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE`, `EVOLUTION_WEBHOOK_URL`, `EVOLUTION_WEBHOOK_TOKEN` |
| api.country.is | Landing page country detection (browser side) | none |

PHP needs a CA bundle for outbound HTTPS (`curl.cainfo` and `openssl.cafile` in `php.ini`), otherwise Razorpay, DeepSeek and SMTP over TLS fail with certificate errors.

## Client communication

* **Drafts first.** The AI never sends anything by itself. `ClientCommunicationService::draft()` writes a draft (rule-based text when DeepSeek is unavailable) and a person reviews, edits and sends it.
* **Daily preparation.** `plms:draft-reminders` runs at 08:00 (it needs the scheduler: `php artisan schedule:run` every minute). For each workspace with automation on it drafts an email when a client input is overdue (again after 3 days) or a project is delayed (again after 7 days). It skips projects whose client has no email and never creates a second draft while one is waiting.
* **Knowledge base.** Each workspace keeps one text (up to 8000 characters) in `tenants.settings.workspace.knowledge_base`. It is added to every DeepSeek prompt for that workspace. Only an organization admin (`settings.manage`) can change it.
* **WhatsApp routing.** One platform WhatsApp number (the `EVOLUTION_INSTANCE` on the Evolution server) serves all workspaces. An incoming message is matched to a client by WhatsApp number (stored digits-only with country code) and attached to that client's most recent active project. If several workspaces share a number, the most recently messaged client wins. Webhook deliveries are rejected unless the address carries `EVOLUTION_WEBHOOK_TOKEN`; group chats, our own messages and other instances' events are ignored, and a redelivered message is ignored.
* **No 24-hour rule.** Evolution links a normal WhatsApp account, so free-text messages are not limited to a window. The Meta template restriction does not apply.
