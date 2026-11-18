# API reference

Base path `/api/v1`. JSON in, JSON out. Authenticated calls send `Authorization: Bearer {token}`.

| Group | Guard |
|---|---|
| Public | none |
| Auth | none, rate limited to 10 per minute per IP |
| Signed in | Sanctum token, tenant bound by `ResolveTenant`, 120 requests per minute |
| Platform | signed in and Super Admin |
| Workspace | signed in and bound to a tenant (Super Admin must send `X-Tenant: {slug}`) |

Common errors: `401` unauthenticated, `402` workspace inactive (`code: tenant_inactive`), `403` forbidden, `404` not found (also for another tenant's records), `409` tenant required (`code: tenant_required`), `422` validation.

## Public

| Method | Path | Purpose |
|---|---|---|
| GET | `/plans` | Active plans |
| GET | `/public/config` | Branding, registration flag, countries, currencies |
| GET | `/public/pricing?country=IN` | Plans priced for one country (falls back to the default country) |
| GET | `/public/workspaces/{slug}` | Whether a workspace exists (case-insensitive) and its display name |

## Auth

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/otp/request` | `{ workspace?, email }` sends a code. Omit `workspace` for the Super Admin. |
| POST | `/auth/otp/verify` | `{ workspace?, email, code }` returns `{ token, user, tenant, permissions }` |
| POST | `/auth/register/order` | Sign-up details plus `plan`. Returns the Razorpay order (`key`, `order_id`, `amount`, `currency`). |
| POST | `/auth/register/verify` | `{ razorpay_order_id, razorpay_payment_id, razorpay_signature }` creates the workspace and returns a session |
| GET | `/auth/me` | Current user, tenant, permissions |
| POST | `/auth/logout` | Revoke the current token |
| PATCH | `/auth/profile` | Name, job title, phone |

`/auth/register/order` body: `organization`, `plan` (code), `slug` (optional, derived from the name as camelCase), `country_code`, `name`, `email`, `phone`.

## Workspace: project tracker

| Method | Path | Purpose |
|---|---|---|
| GET | `/tracker/dashboard` | Counts per status, average progress, all projects |
| GET | `/tracker/projects` | List. Filters: `search`, `status` (tracking status) |
| POST | `/tracker/projects` | Create. Needs `name`, `start_date`, `due_date`. Honours the plan's project limit (`402`). |
| GET | `/tracker/projects/{id}` | Project, summary, activities, meetings, change requests, client dependencies |
| PATCH | `/tracker/projects/{id}` | Update. `status` is one of `active`, `on_hold`, `completed`, `cancelled`; `stage` is one of `requirements`, `design`, `development`, `testing`, `deployment`, `handover`. Changing either notifies the client. |
| DELETE | `/tracker/projects/{id}` | Soft delete |
| POST | `/tracker/projects/{id}/analyse` | Re-run the AI analysis |
| GET | `/tracker/projects/{id}/reports/{type}?format=pdf\|xlsx` | `type` is `progress` or `performance` |
| POST | `/tracker/projects/{id}/activities` | Log an activity. Response includes `ai_source`. |
| PATCH, DELETE | `/tracker/activities/{id}` | Edit or remove an activity |
| POST | `/tracker/projects/{id}/meetings` | Multipart: details, `links[]`, `files[]` (10 files, 20 MB each) |
| POST | `/tracker/meetings/{id}` | Update a meeting (POST because PHP does not parse multipart PATCH) |
| DELETE | `/tracker/meetings/{id}` | Remove a meeting and its files |
| GET | `/tracker/attachments/{id}/download` | Download a file |
| DELETE | `/tracker/attachments/{id}` | Remove a file |
| GET | `/tracker/messages` | Client messages. Filters: `status` (draft, sent, failed, received), `channel`, `project_id` |
| POST | `/tracker/projects/{id}/messages/draft` | `{ channel: email or whatsapp, reason: dependency_overdue, status_alert, progress_update or custom, instruction?, body? }` writes a draft. `body` skips the AI. |
| PATCH | `/tracker/messages/{id}` | Edit a draft or failed message |
| POST | `/tracker/messages/{id}/send` | Send it. `422` with the stored error if delivery fails. |
| DELETE | `/tracker/messages/{id}` | Delete a draft (sent messages are kept) |
| GET | `/tracker/projects/{id}/chat` | WhatsApp conversation and the latest AI evaluation |
| POST | `/tracker/projects/{id}/chat/evaluate` | Evaluate the chat now |
| POST | `/tracker/projects/{id}/change-requests` | Add a change request |
| PATCH, DELETE | `/tracker/change-requests/{id}` | Decide, edit or remove |
| POST | `/tracker/projects/{id}/documents` | Multipart: `title`, `file` (20 MB), `note`, `visible_to_client`. Shared documents notify the client. |
| GET, DELETE | `/tracker/documents/{id}(/download)` | Download or remove a document |
| POST | `/tracker/projects/{id}/client-dependencies` | Add a client dependency |
| PATCH, DELETE | `/tracker/client-dependencies/{id}` | Mark received, edit or remove |

Mutating tracker calls return the changed record and the refreshed `summary`.

## Workspace: clients and settings

| Method | Path | Purpose |
|---|---|---|
| GET, POST | `/clients` | List (`search`) or add a client |
| PATCH, DELETE | `/clients/{id}` | Edit or delete. A project may use `client_id` from this list. |
| GET | `/workspace/settings` | Knowledge base, tone, sender name, signature, automation, connection status |
| PUT | `/workspace/settings` | Change them. Organization admin only. |

## WhatsApp webhook (Evolution API)

| Method | Path | Purpose |
|---|---|---|
| POST | `/webhooks/whatsapp?token=...` | Evolution `MESSAGES_UPSERT` events. Rejected (`403`) unless `token` equals `EVOLUTION_WEBHOOK_TOKEN`. |

## Platform (Super Admin)

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/stats` | Tenant totals, MRR, by country, by plan, new tenants per month |
| GET, POST | `/admin/tenants` | List (search, status) or add a tenant with its admin |
| GET, PATCH, DELETE | `/admin/tenants/{id}` | Detail with usage, edit (status, currency, trial extension, notes), archive |
| GET, POST, PATCH, DELETE | `/admin/countries`, `/admin/countries/{id}` | Countries |
| GET, POST, PATCH, DELETE | `/admin/currencies`, `/admin/currencies/{id}` | Currencies and rates |
| GET, POST, PATCH, DELETE | `/admin/plans`, `/admin/plans/{id}` | Plans and per-country prices |
| GET, PUT | `/admin/settings` | Branding, theme, registration open, default country and currency |

## Client portal

Client accounts only (team members get `403`). Clients get `403` on every `tracker/*`, `clients` and `workspace/*` route.

| Method | Path | Purpose |
|---|---|---|
| GET | `/notifications` | Everyone: `{ unread, data[] }`, latest 50 |
| POST | `/notifications/read`, `/notifications/{id}/read` | Everyone: mark all, or one, as read |
| POST | `/portal/projects/{id}/documents` | Client: upload a document (`title`, `file`, `note`); the team is notified |
| POST | `/portal/projects/{id}/updates` | Client: `{ message, request_id? }` sends the team an update |
| POST | `/portal/projects/{id}/requirements` | Client: `{ title, description? }` raises a requirement (pending) |
| POST, DELETE | `/clients/{id}/portal-access` | Team: give or remove a client's portal login (needs the client's email) |
| GET | `/portal/projects` | The client's own projects with status and progress |
| GET | `/portal/projects/{id}` | Summary and time totals, workflow `steps`, `client_requests`, `requirements`, `meetings`, shared `documents` |
| GET | `/portal/documents/{id}/download` | Download a shared document |
