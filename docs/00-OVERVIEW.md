# Overview

Spider PLMS is a multi-tenant SaaS that tells a delivery team, at any moment, **whether each client project is on time, why it is late, and what to expect next.**

Teams record the facts as they happen. The system derives status, delay attribution and a revised deadline from them.

## What is tracked per project

| Record | Purpose |
|---|---|
| **Project** | Name, client, start date, due date, working days, status (active, on hold, completed, cancelled). |
| **Activity** | A dated piece of work: title, hours and days spent, status, and the overall progress after it. Drives progress and the AI estimate. |
| **Meeting** | Date, details, discussion points, links and up to 10 file attachments (20 MB each). |
| **Change request** | Extra client requirement with a decision (pending, approved, rejected) and the additional days and hours it adds. Approved days move the deadline. |
| **Client dependency** | Something the team is waiting on from the client: requested, expected and received dates. Overdue items count as client-side delay. |

## Workspace screens

| Screen | Purpose |
|---|---|
| Dashboard | Status counts, progress of every project, report downloads |
| Projects | Search and filter all projects, onboard a new one |
| Clients | Client master data: contact person, email, WhatsApp number. A project picks its client from this list |
| Reminders & Alerts | AI-drafted client emails and WhatsApp messages. Drafts wait here until a person reviews and sends them |
| Settings | Knowledge base text for the AI, message tone, sender name and signature, daily draft automation, connection status |

## What the system produces

* **Tracking status**: On Time, Slightly Delayed, Delayed, Significantly Delayed or Completed. See [03 Project tracking](03-PROJECT-TRACKING.md).
* **Delay attribution**: days lost to the client versus days lost internally.
* **Revised deadline**: original due date plus the working days of approved change requests.
* **AI estimate**: hours for the next step and a plain-language summary (DeepSeek, with a rule-based fallback when no key is configured or the call fails).
* **Reports**: Progress and Performance reports as PDF or Excel.
* **Client messages**: DeepSeek drafts reminders and alerts (overdue client inputs, delays, progress updates) from the project data and the workspace knowledge base. A person reviews each draft before it is sent by email or WhatsApp.
* **WhatsApp chat evaluation**: replies from clients arrive by webhook from the Evolution API server; DeepSeek summarises the chat, rates the sentiment, lists open items and suggests a reply.

## Who uses it

| Person | Where | Does |
|---|---|---|
| Super Admin | `/superAdmin` | Manages tenants, plans, countries, currencies and system settings. |
| Tenant member | `/{workspaceName}`, for example `/acmeDigital` | Works in the project tracker of their own workspace. |

## Commercial model

A workspace is created through self-service sign-up and **paid with Razorpay** (see [02 Sign-in and access](02-SIGN-IN-AND-ACCESS.md)). Price depends on the plan and the buyer's country and currency, configured by the Super Admin.

## Document map

| # | Document | Contents |
|---|---|---|
| 0 | Overview (this file) | What the product does |
| 1 | [Architecture](01-ARCHITECTURE.md) | Stack, tenancy, data model, integrations |
| 2 | [Sign-in and access](02-SIGN-IN-AND-ACCESS.md) | URLs, email one-time codes, registration and Razorpay payment, roles |
| 3 | [Project tracking](03-PROJECT-TRACKING.md) | How status, delay and deadlines are calculated |
| 4 | [API reference](04-API-REFERENCE.md) | Every endpoint |
| 5 | [Testing and deployment](05-TESTING-AND-DEPLOYMENT.md) | Tests, production setup, backup |
