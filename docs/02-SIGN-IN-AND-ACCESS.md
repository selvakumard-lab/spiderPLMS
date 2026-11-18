# Sign-in and access

## URLs

| Who | URL | Notes |
|---|---|---|
| Visitor | `/` | Landing page with pricing for the visitor's country |
| Super Admin | `/superAdmin` | Platform sign-in |
| Tenant member | `/{workspaceName}` | For example `/acmeDigital` |
| Anyone | `/login` | Asks for the workspace name, then goes to its page |
| New customer | `/register` | Pick a plan, pay, get a workspace |

Workspace names are camelCase and case-insensitive: `/acmeDigital` and `/acmedigital` reach the same page. `superAdmin`, `admin`, `login`, `register`, `api` and other platform words are reserved.

## Email one-time-code sign-in

There are **no passwords** for anyone, including the Super Admin.

1. The user enters their email on the workspace page (or `/superAdmin`).
2. `POST /auth/otp/request` sends a 6-digit code by email. A wrong email format, an unknown workspace, an email with no account, or a disabled account each return a specific validation message, so the person can fix it.
3. `POST /auth/otp/verify` checks the code and returns an API token.

Rules: codes expire after 10 minutes, work once, allow 5 wrong attempts, and a new request invalidates the previous code. Omitting `workspace` selects the Super Admin account. A workspace member cannot sign in to another workspace, even with the same email. The auth endpoints are rate limited to 10 requests a minute per IP.

### Temporary static code

While testing, set `PLMS_STATIC_OTP=123456` in `.env`. Every sign-in (team, client and Super Admin) then accepts that one code and **no email is sent**. Empty the value to go back to real emailed codes. Never leave it set in production.

## Client portal

A client signs in on the same workspace page (`/{workspace}`) with the email on their client record, then lands on `/app/portal`.

- **Give access:** Clients page, **Give portal access**. This creates a login for the client's email (`POST /clients/{id}/portal-access`). **Remove access** deletes it. Deleting the client also removes it.
- **What a client sees:** only projects linked to their client record. Each project shows status and progress, the step-by-step workflow with time spent against estimate, working days left, shared documents, new requirements, meetings, and anything the team is waiting on from them.
- **What a client never sees:** the team workspace, other clients' projects, AI analysis, internal documents. The team APIs return `403` for client accounts and the portal APIs return `403` for team members.
- **Web notifications:** the team's actions notify the client in the portal bell (refreshed every 30 seconds): a step added or changed, project status change, a shared document, a new request for the client, a requirement decision, a meeting recorded.
- **Email, with review:** each portal notification also prepares an email draft for the client (reason *Project change*) under Reminders & Alerts. Changes that arrive while a draft is waiting are added to it as extra lines. A person edits and sends it; nothing is emailed automatically. Turn this off in Settings, *Prepare an email draft when a project changes*.
- **Two-way, near real time:** the client can upload documents, send the team an update, answer a request, and raise a new requirement. The team is notified the same way. Both sides see every change on one **Activity timeline** on the project, and the project's **stage** (Requirements, Design, Development, Testing, Deployment, Handover) is shown on both sides; the team moves it with one click.
- **Notification bell with sound:** every signed-in person has a bell. It checks every 8 seconds, shows a badge, a toast, and plays a short chime. The chime can be muted from the bell (saved per browser). Browsers only allow sound after the person has clicked somewhere on the page once.
- **Documents:** on a project, **Upload document** (20 MB, executables blocked). Tick *Share with the client* to show it in the portal and notify them.

## Registration and payment (Razorpay)

A workspace is created only after a verified payment.

1. The visitor chooses a plan and company details at `/register`.
2. `POST /auth/register/order` validates everything, prices the plan in the visitor's country currency, creates a Razorpay order **on the server**, and holds the sign-up details for 60 minutes. No tenant exists yet.
3. The browser opens Razorpay Checkout with the returned order.
4. On success the browser posts the three Razorpay values to `POST /auth/register/verify`.
5. The server verifies `HMAC-SHA256(order_id|payment_id, key_secret)`. Only then it creates the workspace, the Organization Admin and an **active** subscription (`gateway = razorpay`, payment id stored), and returns a session.
6. Each paid order can create one workspace. A repeated verify call is rejected.

Use Razorpay **test** keys outside production. The key secret is never sent to the browser.

## Roles

Workspace members carry one of five roles: Organization Admin, Project Manager, Team Leader, Developer, Client. The sign-up admin is an Organization Admin. The role and permission matrix lives in `config/plms.php` and is seeded by `RolePermissionSeeder`.

The project tracker does not currently restrict actions by role: every active member of a workspace can use all tracker features for that workspace. Platform routes require the Super Admin (`super-admin` middleware).
