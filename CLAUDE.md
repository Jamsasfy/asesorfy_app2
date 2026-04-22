# CLAUDE.md

This file provides guidance to Claude Code when working with code in this repository.

---

## Project Identity

**AsesorFy** is a CRM and client portal for an advisory firm (asesoría), built with:

- Laravel 12+
- PHP 8.3
- Filament v4
- Livewire / Alpine
- Vite
- MySQL 8

The application is entirely in **Spanish**, including:

- business terminology
- model names
- database columns
- enum values
- UI labels
- admin actions and workflows

Do not translate domain concepts to English in code, labels, comments, migrations, or documentation unless explicitly requested.

---

## ⚠️ CURRENT PROJECT STATUS — READ FIRST

**AsesorFy is in pre-production.** No real clients yet. A complete security audit (April 2026) found **316 documented issues**, including **~28 critical (🔴🔴)** bugs that must be fixed before any real client uses the system.

**Before doing ANY work on this codebase, read:**
1. The section **"Security Audit Status"** below — understand what's already known broken
2. **"Sprint 0 — Mandatory Pre-Production Fixes"** — the blocking issues
3. **"Architectural Patterns to Avoid"** — the 4 transversal patterns + 7 demons identified
4. **"Lessons Learned"** — guidance for future modules (WordPress + Qdrant/AI still pending)

Do not propose patches that conflict with the Sprint 0 plan without discussing it first.

---

## Security Audit Status (as of 2026-04-21)

A complete security and architectural audit was performed in two phases:

### Phase 1 — Global Flows (F1-F5) — 133 findings

| Phase | Area | Key findings |
|---|---|---|
| **F1** | Leads lifecycle | Cross-reference with Cliente email, helpers, lead history |
| **F2** | Lead conversion | Silent failures in ClienteActivacionService, email sync gaps |
| **F3** | Stripe one-time payments | Webhook without signature, missing checkout.session.completed |
| **F4** | Stripe recurring + SEPA | Multiple activation paths, SEPA days 16-31 missing invoice |
| **F5** | Invoicing | Numbering races, PDF ownership, B2B/B2C fiscal missing, rectificativas dead code |

### Phase 2 — Focused Audits (B1-B11) — 183 findings

| Audit | Area | Findings | 🔴🔴 | 🔴 |
|---|---|---|---|---|
| **B1** | Telegram system | 16 | 1 | 0 |
| **B2** | Mis Clientes Asignados | 5 | 1 | — |
| **B3** | ClienteResource (admin) | 28 | 4 | 9 |
| **B4** | ClienteSuscripciones + StripeSyncRuns | 9 | 2 | 3 |
| **B5** | LeadResource + Conversión | 16 | 4 | 4 |
| **B6** | ProyectoResource | 17 | 3 | 8 |
| **B7** | VentaResource | 12 | 1 | 4 |
| **B8** | Documentos (portal) | 28 | 5 | 5 |
| **B9** | Portal + Tenancy + Notifications | 22 | 2 | 3 |
| **B10** | Comisiones (sistema completo) | 20 | 3 | 5 |
| **B11** | UserResource + TrabajadorResource | 10 | 2 | 3 |
| **TOTAL** | — | **183** | **28** | **44** |

**Global total (F1-F5 + B1-B11): 316 findings**

For complete details, see `auditoria-recursos-menores.md` and `plan-arreglo-v2.md`.

---

## The 7 Architectural Demons

These are the recurring patterns that generate most bugs in AsesorFy. When modifying or adding code, actively check that your changes don't reinforce any of them.

### 1. Inconsistent idempotency
Multiple entry points to the same flow handle duplicates differently. Some paths create records, others check-then-create, others upsert. Result: duplicate leads from same email, duplicate invoices on retry, duplicate commissions on manual recalc.

### 2. Multiple uncoordinated activation paths
There are 5+ ways a client can be "activated" (A auto, B Checkout, C setup, D transfer, E customer.updated webhook). Each path does different things. Some create User portal, some don't. Some send email, some don't. Some create Stripe subscription, some don't.

### 3. Mixed reversible/irreversible operations in transactions
Code creates a Stripe subscription AND a local record in the same flow. If the local save fails, Stripe has a subscription that local doesn't know about. No rollback logic.

### 4. Incomplete Stripe → AsesorFy synchronization
Stripe webhooks don't cover all cases. SEPA invoices days 16-31 never generate a real invoice in AsesorFy. Manual status changes in Stripe don't propagate back. Client marked as BAJA locally doesn't cancel Stripe subscription.

### 5. Zero client-side observability
Clients don't know when something failed. A card payment that fails silently, a subscription that should have been created but wasn't, an invoice that should have been sent but wasn't — the client sees nothing. No notifications, no visible state changes.

### 6. Admin can break anything freely
Filament admin has no guards for destructive actions. Admin can modify an already-issued invoice. Admin can change fecha_venta after commission was paid. Admin can delete a client with active Stripe subscription (Stripe zombie).

### 7. Features that promise what they don't deliver
A growing pattern of "flags and features that lie":
- `bloquea_portal` — UI-only, all URLs still accessible
- `acceso_app` — doesn't gate panel access (B11-R2)
- `HasShieldPermissions` commented in ClienteResource — custom perms are BD-only, not regenerable
- `shield:generate --all` won't recreate custom permissions
- `informe_hash` SHA-256 in ComercialHistorialObjetivo — always invalid (B10-R3)
- `getPdfUrl()` in ComercialContratoIncentivo — always returns 404 (B10-R10)
- `verificar_documento` policy exists but is never invoked (B8-C2)
- `DocumentoPolicy` exists but `$shouldSkipAuthorization = true` bypasses it

---

## The 5 Transversal Patterns Confirmed

These are the bug patterns that appear in MULTIPLE areas of the codebase. When you see code resembling these, suspect a bug.

### Pattern 1 — Decorative Policy not invoked (10 occurrences)

**Symptom:** A `XxxPolicy.php` exists with correct methods (view, update, delete). It is registered. But the actions in Resources, Pages, and Relation Managers don't call `->authorize('update', $record)`. They rely only on `can('Update:Xxx')` Shield checks or on `getEloquentQuery()` scoping.

**Effect:** If scoping fails (bug, refactor, middleware change), there's no second line of defense.

**Confirmed in:** LeadPolicy, VentaPolicy, ClientePolicy, ClienteSuscripcionPolicy, ProyectoPolicy, DocumentoPolicy, ChatPolicy (doesn't exist), UserPolicy, TrabajadorPolicy, ComercialHistorialObjetivoPolicy.

**Rule when writing new code:** Every Resource/Page/Action/RelationManager MUST invoke its Policy explicitly. Don't trust Shield alone. Don't trust scoping alone.

### Pattern 2 — Scaffold not cleaned in production (3 occurrences)

**Symptom:** A Filament form file (`XxxForm.php`) was autogenerated by `filament:make-resource` and never depured. It exposes internal-control columns like `cliente_id`, `user_id`, `verificado`, `stripe_invoice_id`, `observaciones_privadas` as editable fields. When combined with `$shouldSkipAuthorization = true` and/or `$guarded = []`, this allows client-side mass assignment manipulation.

**Confirmed in:** `Portal/Resources/Documentos/Schemas/DocumentoForm.php` (active — B8-B1), `Portal/Resources/Facturas/Schemas/FacturaInfolist.php` (active — B9-R1), `Portal/Resources/Facturas/Schemas/FacturaForm.php` (latent — B9-R2).

**Rule when writing new code:** After `filament:make-resource`, ALWAYS review the generated form and remove any field the user shouldn't edit. Never leave raw scaffolds in portal resources.

### Pattern 3 — Fail-open guard (2 occurrences)

**Symptom:** A security check has the form `if ($secret !== '' && ! hash_equals(...))` or `if ($blockingFlag) { showModal(); }`. If `$secret` is empty or `$blockingFlag` is on a different URL, the check doesn't run. The guard defaults to "allow" instead of "deny".

**Confirmed in:** TelegramWebhookController (B1-K1 — if `TELEGRAM_WEBHOOK_SECRET` empty, webhook accepts anything), `bloquea_portal` blade modal (B9-K1 — only active on /portal/dashboard, not on /portal/documentos etc.).

**Rule when writing new code:** Guards must be fail-closed. The default behavior when configuration is missing or check can't be performed must be DENY, not ALLOW.

### Pattern 4 — Inconsistency `$user->clientes()` vs `Cliente::find()`

**Symptom:** Some code uses `$user->clientes()->where('clientes.id', $id)->first()` (ownership validated via the relation). Other code uses `Cliente::find($id)` relying on middleware having already validated. Pages use Pattern A; Widgets use Pattern B. If middleware fails or gets bypassed, only Pattern A fails closed.

**Confirmed in:** Portal Dashboard uses A (correct), NotificacionesWidget uses B (B9-D2).

**Rule when writing new code:** In multi-tenant context, ALWAYS validate the entity belongs to the authenticated user at every access point, not just in middleware. Defense in depth.

### Pattern 5 — Multiple paths do the same thing with different effects

**Symptom:** Two Filament Resources, two pages, or two actions that appear to do "the same business operation" but have divergent logic. Silent bugs ensue: super_admin confuses which path to use, one works, the other doesn't, data ends up inconsistent.

**Confirmed in:**
- B5/B7: `GestionarConversion::enviar_contrato` dedupes LeadConversionLink; `VentaResource::enviar_contrato` does NOT dedupe
- B6-J1: Project cancellation has 2 mechanisms with different Stripe effects
- B10-R12: 2 commission approval flows, only one generates PDF
- B11: `UserResource` role change SILENTLY discarded (no `->relationship()`); `TrabajadorResource` role change works (R3). TrabajadorResource has privilege escalation (R1), UserResource doesn't.

**Rule when writing new code:** Before building a second way to do something, check if the first way exists. If yes, either replace it or document clearly in code comments why both exist and when each is used.

---

## Sprint 0 — Mandatory Pre-Production Fixes

These are the **🔴🔴 critical** issues that MUST be resolved before any real client uses AsesorFy. Cross-reference with `plan-arreglo-v2.md` for exact order and estimates.

### Blocking Security Issues

| ID | Description | File | Est. |
|---|---|---|---|
| **B11-R1** | **Privilege escalation: any user with `Update:Trabajador` can assign `super_admin` role** | `TrabajadorResource.php:128-138` | 15min |
| **B11-R2** | `acceso_app` doesn't gate panel access — `canAccessPanel` doesn't check it | `User.php:65-79` | 30min |
| **B11-R5** | No guard against deleting the last super_admin | `UserResource.php:356-374` | 30min |
| **B8-B1** | `DocumentoForm.php` portal exposes `cliente_id`, `verificado`, `ruta` editable | `Portal/Resources/Documentos/Schemas/DocumentoForm.php` | 20min |
| **B8-B2** | `$shouldSkipAuthorization = true` combined with B8-B1 = full client-side manipulation | `Portal/Resources/Documentos/DocumentoResource.php:69` | 15min |
| **B8-A1** | `$guarded = []` in Documento model — mass assignment of all 25+ fields | `Documento.php:16` | 10min |
| **B8-A2** | Public disk for fiscal documents — URL-direct access without auth | `config/filesystems.php:33-40` + `DocumentoResource.php` | 4-6h |
| **B9-R1** | `FacturaInfolist` exposes `observaciones_privadas`, `stripe_invoice_id` to client | `Portal/Resources/Facturas/Schemas/FacturaInfolist.php:30-44` | 5min |
| **B9-R2** | `FacturaForm` scaffold latent (pages exist but not registered) | `Portal/Resources/Facturas/Schemas/FacturaForm.php` | 10min |
| **B9-L2** | `EnviarNotificacionPortalJob` uses `Http::withoutVerifying()` hardcoded | `EnviarNotificacionPortalJob.php:235` | 10min |
| **B1-K1** | Telegram webhook fail-open if `TELEGRAM_WEBHOOK_SECRET` empty | `TelegramWebhookController.php:18-23` | 15min |

### Blocking Financial Issues

| ID | Description | File | Est. |
|---|---|---|---|
| **B10-R1** | Recalculating commissions resets `estado` to `'borrador'` even for paid commissions | `CalcularComisionesMes.php:88-109` | 30min |
| **B10-R2** | `guardarDetalles()` deletes historical detail without state check | `CalcularComisionesMes.php:230` | 20min |
| **B10-R3** | SHA-256 hash never matches stored PDF (calc on first render, save second render) | `InformeComisionService.php:38-58` | 2-4h |
| **B10-R6** | `calcularBajas()` filters by `'inactiva'` state that doesn't exist in Enum | `CalcularComisionesMes.php:209` | 10min |
| **B10-R8** | CASCADE DELETE removes paid commission history when user/rule is deleted | Migrations | 1-2h |

### Blocking Operational Issues

| ID | Description | File | Est. |
|---|---|---|---|
| **B3-K5** | `->actions()` API v3 in v4 Resource — crashes or silences actions | `ContratosResponsabilidadRelationManager.php:47` | 3min |
| **B3-K1** | UsuariosRM DeleteAction hard-deletes global User (affects multi-company) | `UsuariosRelationManager.php:271` | 30min |
| **B3-L1** | CASCADE DELETE on client destroys subscriptions, Stripe continues charging | Cliente migrations | 2-4h |
| **B3-G1** | DeleteBulkAction on Cliente without active-subscription guard | `ClienteResource.php:1172` | 30min |
| **B5-L3/L4** | DeleteBulkAction on Lead destroys LeadConversionLinks + PDFs + email logs | `LeadResource.php` | 1h |
| **B5-L8** | ComentariosRelationManager hard-deletes comments without guards | `ComentariosRelationManager.php:70-78` | 20min |
| **B5-L9** | `GestionarConversion` has zero authorization (uses `findOrFail`) | `GestionarConversion.php` | 30min |
| **P6-K2** | `cambiar_estado_proyecto` triggers Stripe activation without role check | `ProyectoResource.php:781` | 15min |
| **P6-K3** | `cancelar_suscripcion` action without authorization check | `ViewProyecto.php:132-200` | 15min |
| **P6-A1** | ProyectoPolicy Shield-only (5th occurrence of the pattern) | `ProyectoPolicy.php` | — |
| **B7-R1** | `EditVenta::afterSave()` regenerates invoice on every save | `EditVenta.php:72-82` | 30min |
| **B4-R1** | CreateAction bypasses `activarSuscripcion` | `ClienteSuscripcionResource.php` | 30min |
| **B4-R4** | Scheduler daily sync doesn't create StripeSyncRun — invisible | `stripe:sync` command | 30min |
| **B2-R1** | ClientePolicy without ownership (confirmed asesor has View + Update) | `ClientePolicy.php` | — |

**Estimated total Sprint 0:** ~3-4 days of focused work.

### Sprint 1+ — Important but not immediately blocking

See `plan-arreglo-v2.md` for the full roadmap of remaining 🔴 and 🟡 findings.

---

## Non-Negotiable Working Rules

### 1. Always inspect the real code first

Before proposing or applying any change, inspect the actual files involved.

Do not invent generic implementations if the repository already contains the relevant:

- model
- observer
- service
- controller
- resource
- page
- widget
- enum
- migration
- policy
- command
- job
- relation manager
- Livewire component

If the real implementation is not visible yet, ask first.

### 2. If there is any doubt, ask before changing

This project contains business logic that may not be fully documented in this file.

There may be:

- additional services
- hidden side effects
- custom flows
- business rules not visible at first glance
- legacy decisions that must be preserved
- integrations not obvious from file names alone

**Default rule: if something important is unclear, ask the user before implementing.**

Do not guess.

Ask before changing anything related to:

- billing
- subscriptions
- invoices
- Stripe
- Telegram
- notifications
- lead conversion
- contracts
- document lifecycle
- portal access
- multi-company logic
- observers
- scheduled commands
- admin permissions
- destructive actions
- status transitions
- external integrations
- commissions
- user roles and shield permissions

### 3. Prefer minimal and localized changes

Make the smallest safe change that solves the problem.

Do not:

- refactor unrelated files
- rename classes or methods without being asked
- reorganize folders unnecessarily
- replace working patterns with "cleaner" abstractions just because they look better
- introduce architectural rewrites unless explicitly requested

This repository is an active production-oriented business application. Stability is more important than elegance.

### 4. Do not change design or UX unless explicitly requested

If the task is about logic, validation, persistence, performance, observers, services, or integrations:

- do not modify layout
- do not alter visual structure
- do not rewrite labels
- do not redesign widgets
- do not move actions around
- do not change tables/forms/infolists unless required for the requested feature

Preserve the current UI unless the user explicitly asks for visual changes.

### 5. Respect the current business naming

Preserve existing domain names and business terminology such as:

- Lead
- Venta
- Cliente
- ClienteSuscripcion
- Factura
- FacturaItem
- Proyecto
- Documento
- Servicio
- Notificación
- Comentario
- Comercial (es un User con rol shield "comercial", no un modelo propio)

Do not rename classes, methods, relationships, columns, enums, labels, routes, or actions unless explicitly requested.

### 6. Be extremely careful with side effects

This project uses model observers, services, jobs, scheduled commands, notifications, and external integrations.

A small change in one file may affect:

- Stripe sync
- invoice generation
- recurring billing
- Telegram messages
- portal notifications
- lead automation
- document flows
- derived record creation
- dashboards / KPIs
- permissions
- commissions

Before modifying create/update/delete flows, inspect all related observers and service calls.

If unsure, ask first.

### 7. Never weaken security to "make it work"

Never:

- hardcode secrets
- expose API keys
- expose webhook secrets
- commit `.env` values
- bypass validation
- disable middleware without approval
- remove authorization checks without approval
- simplify security-sensitive flows in production code
- use `$shouldSkipAuthorization = true` unless it's a deliberate, documented architectural decision
- leave Filament resource scaffold without depuring form fields
- use public disk for sensitive business documents
- accept `$guarded = []` in any model

If something seems blocked by security, explain it and propose a safe fix.

### 8. Treat billing and payroll flows as critical

Anything involving the following is business-critical:

- Stripe
- subscriptions
- invoices
- payment methods
- payment links
- recurring billing
- proration
- numbering
- webhook processing
- reconciliations
- **commission calculation** (business payout — affects real commercials' pay)
- **commission state transitions** (borrador → aprobada → pagada — once 'pagada', do not alter)

Prefer:

- idempotency
- explicit validation
- transactions where needed
- traceability
- safe retries
- defensive checks against duplicates
- state guards (if `estado === 'pagada'`, don't overwrite)

If the existing billing/commission flow is not fully clear, ask before changing it.

### 9. Respect existing Filament patterns

Internal CRUDs are implemented through **Filament Resources**.

When extending functionality, prefer the existing project patterns using:

- Resources
- Resource Pages
- RelationManagers
- Actions
- Forms
- Tables
- Infolists
- Widgets

Do not introduce a different admin pattern if the feature clearly belongs inside the current Filament structure.

### 10. Use the repository's real implementation, not generic examples

When working on a task, ground every proposal in the actual repository.

Do not answer with abstract boilerplate if the concrete file already exists.

Prefer:

- "Edit this class"
- "Change this observer"
- "Add this method here"
- "Patch this Filament Action"
- "Update this query"

instead of inventing a parallel implementation.

### 11. Explain impact clearly

When proposing a code change, be explicit about:

- what file(s) must change
- why the change is needed
- what side effects may occur
- what must be tested manually
- whether any observer/service/job/webhook could be affected
- whether the change touches any Sprint 0 item or Demon

Do not hide impact.

### 12. If the business rule is unclear, stop and ask

This is especially important in AsesorFy.

If a rule is not explicitly visible in code and not explicitly stated by the user, do not assume.

Ask first.

This rule has priority over speed.

---

## Expected Collaboration Style

When helping in this repository, follow this order:

1. Identify the exact files involved
2. Read the current implementation
3. Detect observers, services, jobs, commands, policies, and integrations involved
4. **Check if the change touches any Sprint 0 item or any of the 7 Demons or 5 Patterns**
5. If anything important is unclear, ask the user
6. Only then propose the smallest safe change
7. Explain how to verify it

If a task touches sensitive business logic, do not skip step 5.

---

## Commands

```bash
# Start development (server + queue + vite concurrently)
composer dev

# Individual dev processes
php artisan serve
php artisan queue:listen --tries=1
npm run dev

# Build frontend assets
npm run build

# Run all tests
php artisan test
./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Run tests matching a description
./vendor/bin/pest --filter "test name"

# Migrations
php artisan migrate
php artisan migrate:fresh --seed

# Code style
./vendor/bin/pint

# Filament Shield permissions
php artisan shield:generate --all

# View scheduler status
php artisan schedule:list

# Commission calculation (manual)
php artisan comisiones:calcular-mes --mes=YYYY-MM
```

⚠️ **Warning on `php artisan shield:generate --all`:** ClienteResource has `HasShieldPermissions` commented out (B3-A1). Custom permissions like `CambiarAsesor:Cliente`, `AsignarAsesor:Cliente`, `QuitarAsesor:Cliente`, `Chats:UnlinkTelegram` exist in BD but are NOT regenerable. If `migrate:fresh` + `shield:generate` is run, these permissions will disappear and related actions will silently fail.

⚠️ **Warning on `comisiones:calcular-mes`:** Until Sprint 0 is completed, re-running this command for an already-processed month will RESET the state to 'borrador' and RE-CALCULATE, even for commissions already marked as 'pagada' (B10-R1, B10-R2). This can silently alter paid amounts. Until fixed, only re-run this command when you understand all its side effects.

---

## Architecture Overview

AsesorFy is a CRM + advisory operations platform + client portal.

It is used to manage:

- leads
- conversions
- sales
- clients
- subscriptions
- invoices
- projects
- documents
- notifications
- client access
- internal workflows
- advisor/client communication
- commercial agent performance and commissions

This file is only a partial guide. The repository may contain additional services and flows not listed here. If something important is missing from this document, inspect the code and ask the user before assuming.

---

## Filament Panels

### Admin panel

- URL: `/admin`
- Provider: `AdminPanelProvider`
- Used by internal team members AND by commercial agents (rol comercial)
- ~~Access controlled by `users.acceso_app = true`~~ **WRONG** — access is controlled by `hasRole('super_admin') || (bool) $this->trabajador` (see B11-R2). The `acceso_app` toggle does NOT gate panel access; it only affects landing-page redirect. Until B11-R2 is fixed, do not rely on `acceso_app` to block user access.
- Uses filament-shield for roles and permissions
- Commercial agents see their own reduced view via the "Mi espacio de trabajo" navigation group

### Portal panel

- URL: `/portal`
- Provider: `PortalPanelProvider`
- Used by clients
- Access controlled by portal-specific middleware (`VerificarAccesoPortal`) + `portal_activo` flag on User
- Supports multi-company access for a single user via `session('cliente_activo_id')`
- Uses `$shouldSkipAuthorization = true` in DocumentoResource and FacturaResource by deliberate architectural decision (portal clients don't have Shield roles). Isolation is enforced via `getEloquentQuery()` scope. This is correct for Factura (read-only) but dangerous for Documento due to B8-B1 scaffold form.

Panel providers are typically located in:

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/PortalPanelProvider.php`

---

## Core Business Flow

```
Lead → (conversion link + contract signing) → Venta → Cliente → ClienteSuscripcion
                                                              ↓
                                                         Factura / Proyecto
```

This is a general high-level flow only. **There are 5+ alternative paths** for client activation (Demon 2). Do not assume every conversion path or billing path behaves exactly like this without reading the real code.

Known activation paths:
- **Path A:** automatic activation via ClienteActivacionService (happy path)
- **Path B:** Stripe Checkout success webhook
- **Path C:** SEPA setup complete
- **Path D:** manual advisor transfer (⚠️ does NOT create User portal — F2-N9)
- **Path E:** `customer.updated` webhook (phantom behavior — F2)

---

## Commercial Agent Commissions System

AsesorFy has a full commission system for commercial agents (users with Shield role `comercial`).

**Key business rule: Commissions are paid ONCE when a client is acquired**, not monthly. Even for clients with recurring subscriptions, the commercial agent earns the commission only on the month of acquisition.

### ⚠️ Commission System — Current State

The commission system has critical bugs (B10, 20 findings, 3 🔴🔴). Until Sprint 0 is completed:

1. **Do not re-run `comisiones:calcular-mes` for closed months** without full understanding of B10-R1 and B10-R2 side effects
2. **Do not trust `informe_hash`** in `ComercialHistorialObjetivo` — it never matches the stored PDF (B10-R3)
3. **Do not use `getPdfUrl()`** on `ComercialContratoIncentivo` — always returns a broken URL (B10-R10)
4. **Do not change `fecha_venta` of past ventas** — silently affects past commission calculations on next recalc (amplified by B7-R2)
5. **`calcularBajas()` is partially blind** — only `'cancelada'` state counts as penalty, `'inactiva'` string doesn't exist in Enum (B10-R6)
6. **Do not delete commercial users or commission rules** — CASCADE DELETE removes paid commission history (B10-R8)

### Data model

- `comision_reglas` — rules defining commission % per service type, monthly minimum, early-churn penalty
- `comercial_reglas` — pivot table linking commercials to rules (`es_obligatoria`, `activa`)
- `comisiones_mensuales` — per-commercial-per-rule calculation by month
- `comision_detalles` — line-by-line detail per invoice / subscription
- `comercial_historial_objetivos` — aggregated monthly record with totals, status, PDF path and SHA-256 hash
- `comercial_alertas` — automatic alerts for dismissal conditions
- `configuracion_comisiones` — global config (dismissal thresholds, HR emails)
- `comercial_contratos_incentivos` — signable incentive contracts with token + PDF + hash
- `plantillas_email_comisiones` — editable email templates for monthly reports (the one rendered in `/admin/plantilla-email-comisions`)
- `email_plantillas_comercial` — different table for management emails (dismissal warnings etc.)

### Commercial field in all commission tables

In every commissions-related table the commercial's FK is **`comercial_id`** (not `user_id`), referencing `users.id`. Always filter by `comercial_id = Auth::id()` when building per-commercial queries.

### Rule type discrimination

`ComisionRegla.tipo_servicio` is a plain string (`'unico'` or `'recurrente'`), not an Enum. The value comes from `ServicioTipoEnum` semantically but is stored as string. Use `$regla->tipo_servicio === 'recurrente'` for comparisons.

### Commercial-facing pages under /admin

Pages visible to commercial agents (under navigation group "Mi espacio de trabajo"):

- `app/Filament/Pages/MisComisiones.php` — monthly history with detail drill-down (✅ ownership via query scope, B10-J)
- `app/Filament/Pages/VerDetalleComision.php` — monthly detail (✅ abort_if ownership check in mount)
- `app/Filament/Pages/MisReglas.php` — read-only view of assigned rules (✅ ownership via relation)
- `app/Filament/Pages/MiContrato.php` — incentive contract with sign/download flow (✅ ownership via relation)

All these pages use `HasPageShield` trait and are protected by permissions:

- `View:MisComisiones`
- `View:VerDetalleComision`
- `View:MisReglas`
- `View:MiContrato`

These permissions must be assigned to the `comercial` role via `$rol->givePermissionTo([...])`.

### Query pattern for commercial pages

Always filter by authenticated commercial:

```php
ComercialHistorialObjetivo::where('comercial_id', Auth::id())->...
```

In pages with route parameters (like `VerDetalleComision`), validate ownership in `mount()` and `abort(403)` if the record belongs to a different commercial. A superadmin exception is acceptable:

```php
if (! $user->hasRole(['super_admin', 'coordinador'])
    && $this->historial->comercial_id !== $user->id) {
    abort(403);
}
```

### Scheduled commission calculation

The command `comisiones:calcular-mes` is scheduled in `routes/console.php` to run automatically on day 1 of each month at 03:00 Europe/Madrid. Without a `--mes` parameter, it calculates the previous month. This scheduler requires the system cron (`* * * * * schedule:run`) to be configured on the production server.

⚠️ **Reminder:** until B10-R1/R2 are fixed, a manual re-run of this command can silently alter approved or paid commissions.

---

## Main Domain Entities

### Lead

Prospective customer.

Typical responsibilities:

- lead capture
- lead state changes
- follow-up flows
- automatic reminders
- conversion links
- conversion to venta/cliente

Usually related to `LeadEstadoEnum`.

### Venta

Represents a sale or contracted operation.

Typical responsibilities:

- created during conversion or direct commercial process
- contains items via `VentaItem`
- may trigger invoice/subscription/project logic
- may interact with Stripe/payment flows

⚠️ **fecha_venta is critical** — it drives commission calculation period. Editing it after the commission has been calculated affects past commissions silently (B7-R2 + B10).

### Cliente

Represents an active customer.

Typical responsibilities:

- linked to one or more portal users
- business/customer data
- advisory lifecycle
- Stripe customer linkage
- relation to subscriptions, invoices, documents, projects

A single user may belong to multiple `Cliente` records (multi-company tenancy).

⚠️ **No B2B/B2C fiscal distinction exists** (B3-O1 + F5-L1). `TipoCliente` only has B2B types (Autónomo, SL, Empleados del Hogar, Comunidad de Bienes). `getPorcentajeImpuesto()` only considers geography. Any future B2C client cannot be correctly handled fiscally.

⚠️ **Cascade delete on Cliente is devastating** (B3-L1) — destroys subscriptions, documents, projects, telegram links while Stripe keeps charging (zombie).

### ClienteSuscripcion

Represents a recurring contracted service for a client.

Typical responsibilities:

- recurring billing
- lifecycle state handling
- main tariff flag (`es_tarifa_principal`)
- relation with Stripe subscriptions
- invoice generation
- renewals, pauses, cancellations, unpaid states

This area is highly sensitive.

Dates of interest: `fecha_inicio`, `fecha_fin` (null if still active), `proxima_fecha_facturacion`. State in `estado` (cast to `ClienteSuscripcionEstadoEnum`).

⚠️ **Enum values:** `PENDIENTE_ACTIVACION`, `ACTIVA`, `CANCELADA`, `PAUSADA`. The string `'inactiva'` is used in some places (B10-R6) but does NOT exist in the Enum — any WhereIn filter using `'inactiva'` is dead code.

### Factura

Represents an invoice.

Typical responsibilities:

- generated from ventas or subscriptions
- sequential numbering
- totals, taxes, states, payment tracking
- PDF generation
- relation to Stripe or payment status depending on flow

Be careful not to break numbering or duplication safeguards.

Key fields: `serie` + `numero_factura` (combined for human-readable number), `fecha_emision`, `cliente_id` (BelongsTo Cliente).

⚠️ **Factura has internal fields that must NEVER be exposed to clients:**
- `observaciones_privadas` — internal notes for advisors only (currently exposed in portal B9-R1 — Sprint 0)
- `stripe_invoice_id`, `stripe_payment_intent_id` — internal identifiers (currently exposed B9-R1)

### Proyecto

Represents operational work linked to a service/sale/client flow.

Typical responsibilities:

- task/project lifecycle
- service execution state
- advisor visibility
- client-related operational status

### Documento

Represents uploaded or generated documents.

Typical responsibilities:

- polymorphic attachment
- client/admin visibility
- status workflow
- verification / rejection / clarification flows
- portal interaction
- storage lifecycle

Do not assume storage paths, purge rules, or visibility rules without checking the code.

⚠️ **Critical issues pending Sprint 0:**
- `$guarded = []` in Documento — full mass assignment (B8-A1)
- Public disk storage for fiscal documents (B8-A2)
- Portal DocumentoForm is unedited scaffold (B8-B1)
- Client can delete documents (including advisor's) via portal (B8-B3)

---

## Filament Structure

Common locations:

- `app/Filament/Resources/` — Admin resources
- `app/Filament/Pages/` — Custom admin pages (dashboards, commercial pages, etc.)
- `app/Filament/Portal/Resources/` — Portal resources
- `app/Filament/Portal/Pages/` — Portal pages
- `app/Filament/Widgets/` — Admin widgets
- `app/Filament/Portal/Widgets/` — Portal widgets

When adding or fixing admin CRUD behavior, start here first.

---

## Filament v4 — Critical Namespace and API Notes

This project uses **Filament v4**, not v3. Multiple namespaces and APIs changed between versions, and v3 examples are still widely found online. Do not rely on memory — v3 syntax will often compile but fail at runtime with "Class not found" errors.

### Actions moved out of Tables

Actions are no longer under `Filament\Tables\Actions\`. They live in `Filament\Actions\`.

Wrong (v3):

```php
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\Action;
```

Correct (v4):

```php
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
```

### Table methods renamed

Inside `public static function table(Table $table): Table`:

- `->actions([...])` → `->recordActions([...])`
- `->bulkActions([...])` → `->toolbarActions([...])`

⚠️ **Confirmed bug in production:** `ContratosResponsabilidadRelationManager.php:47` uses `->actions()` — this is silently broken in v4 (B3-K5). Sprint 0 fix.

### Schemas replace the old form() / infolist() signatures

Resources use `Filament\Schemas\Schema` and typically expose:

```php
public static function schema(Schema $schema): Schema
```

The `Section` component lives in `Filament\Schemas\Components\Section`, not `Filament\Forms\Components\Section`.

### Root schema uses an implicit 2-column grid

The root schema of a Filament v4 Resource applies an implicit 2-column grid. This has two consequences that cause layout bugs if ignored:

1. A top-level `Section` that must span the full page width requires `->columnSpanFull()` explicitly. Without it, the Section only occupies half the page and leaves an empty space on the right.

2. A `Grid::make(2)` placed at the top level does not automatically fill the whole row. It is placed inside the 2-column root grid, so it only takes half the page and its two children appear squeezed to the left (roughly 25% + 25% of the page). To force the inner Grid to span the full width so its children really split 50/50, also chain `->columnSpanFull()` on it.

Correct pattern for a layout with two equal sections on top (50/50) and one full-width section below:

```php
->components([
    Grid::make(2)
        ->schema([
            Section::make('Izquierda')->schema([...]),
            Section::make('Derecha')->schema([...]),
        ])
        ->columnSpanFull(),

    Section::make('Abajo ancho completo')
        ->schema([...])
        ->columnSpanFull(),
])
```

### Pages with route parameters — protect presentation methods

Methods like `getTitle()`, `getHeading()`, `getSubheading()`, `getBreadcrumb()` can be called by Filament in global contexts (building the sidebar, checking permissions) before `mount()` assigns values to typed properties. If the property has no default value, the page throws a fatal error in unrelated URLs.

Always guard access with `isset()` when reading properties assigned in `mount()`:

```php
public function getTitle(): string
{
    if (! isset($this->historial)) {
        return 'Detalle de comisión';
    }

    return 'Comisiones de ' . $this->historial->año . '-' . $this->historial->mes;
}
```

### Filament plugins with frontend assets

When installing a Filament plugin that ships its own JS/CSS (rich editors, WYSIWYG fields, custom components, map pickers, etc.), publishing only the config is not enough. The public assets must also be published, otherwise the field renders with an empty label and no visible editor/component, without producing any error in the Laravel log.

Standard checklist when installing a plugin with frontend assets:

```bash
composer require vendor/plugin-name
php artisan vendor:publish --tag="plugin-config"
php artisan vendor:publish --provider="Vendor\Plugin\ServiceProvider" --tag="public"
php artisan filament:assets
php artisan filament:optimize-clear
npm run build
```

### Download buttons — never use `<x-filament::button>` for file downloads

Filament button components can be intercepted by Livewire and turn a direct download into an AJAX `fetch()`, which shows the binary content as text in the browser instead of downloading the file.

For any file download, use a plain HTML `<a>` with the `download` attribute and styled with Tailwind classes.

### Download controllers must send proper headers

Closures or controllers that serve PDFs (or any binary file) must send `Content-Type` and `Content-Disposition`.

Correct pattern:

```php
return Storage::disk('local')->download(
    $contrato->pdf_path,
    'nombre-archivo-legible.pdf',
    ['Content-Type' => 'application/pdf']
);
```

---

## Known Recurring Errors to Avoid

These errors have already occurred in this project. If any of them appears, inspect the namespace or API first — do not start generic debugging.

- `Class "Filament\Tables\Actions\EditAction" not found` → wrong namespace, use `Filament\Actions\EditAction`.
- Any `Filament\Tables\Actions\*` import in v4 code → wrong namespace, move to `Filament\Actions\*`.
- Calling `->actions()` on a Table in v4 → method does not exist, use `->recordActions()`.
- Calling `->bulkActions()` on a Table in v4 → use `->toolbarActions()`.
- Importing `Filament\Forms\Components\Section` for a Resource schema → use `Filament\Schemas\Components\Section` instead.
- A top-level Section appearing narrow / squeezed to the left of the page → missing `->columnSpanFull()` to override the implicit root 2-column grid.
- A Filament plugin field (rich editor, custom component) rendering as an empty line with no errors in the log → public assets of the plugin were not published.
- `Typed property X must not be accessed before initialization` on unrelated URLs → some Page's `getTitle()` / `getHeading()` / `getBreadcrumb()` reads a property assigned only in `mount()`. Guard with `isset()`.
- A download link opens the PDF as garbled text in the browser → missing `Content-Type: application/pdf` and/or `Content-Disposition` headers.
- A CTA button becomes invisible when toggling light/dark mode → using `dark:` modifiers on the background color; remove them.
- A newly styled element appears completely unstyled after a Blade edit → Tailwind JIT has not seen the new classes yet. Run `npm run build`.
- `Class "Filament\Resources\Components\Tab" not found` → use `Filament\Schemas\Components\Tabs\Tab`.
- `php artisan shield:generate --all` doesn't regenerate some custom permissions → `HasShieldPermissions` is commented out in ClienteResource (B3-A1).

---

## Observers

Key models may have observers registered, typically from `AppServiceProvider`.

Observers are critical in this project because they may trigger side effects such as:

- Stripe synchronization
- notifications
- Telegram messages
- status changes
- invoice generation
- follow-up actions
- linked record creation
- portal updates

Confirmed observers from audit:

- `ClienteObserver` — saving() + updated() for asesor assignment (B3-L2)
- `ClienteSuscripcionObserver` — updates cliente.estado to PENDIENTE_ASIGNACION
- `ProyectoObserver` — creates/updates state transitions, triggers Stripe activation
- `VentaObserver` — invoice generation (B7-R1 issue: regenerates on every edit save)
- `DocumentoObserver` — creates notifications to client when advisor uploads
- `ChatMensajeObserver` — tracks pendiente_respuesta
- `UserObserver` — kills sessions when portal_activo set to 0 (NOT when acceso_app is changed — B11-R2)

Before changing model save/update/delete flows, inspect all related observers.

---

## Services

Known services:

- `FacturacionService` — invoice generation, numbering
- `FacturacionRecurrenteService` — recurring invoice flows
- `StripeSubscriptionService` — creation, cancellation of Stripe subs
- `StripeSuscripcionSyncService` — daily sync Stripe ↔ local
- `TelegramService` — send messages, download files
- `ConfiguracionService` — global config helpers
- `InformeComisionService` — monthly commission PDF reports (⚠️ SHA-256 hash is invalid by design — B10-R3)
- `ClienteActivacionService` — activates cliente + creates portal user (⚠️ silent failure on duplicate email — F2-N1)
- `ContratoIncentivosService` — commercial incentive contract signing

The repository may contain many more services and helper classes. Inspect the actual codebase and ask the user if the relevant service flow is unclear.

---

## Integrations

### Stripe

Used for:

- customer creation
- payment methods
- card setup
- SEPA setup
- subscriptions
- invoices
- payment intents
- payment links
- webhooks
- reconciliation logic

Treat all Stripe-related changes as sensitive.

⚠️ **Known issues (see F3, F4):**
- Webhook without Stripe signature verification (bloqueante)
- SEPA days 16-31 never generate local invoice
- `customer.updated` handler phantom (referenced but not implemented)
- Multiple uncoordinated activation paths (Demon 2)

### Telegram

Used for communication flows between clients and advisors.

This may involve:

- conversations
- messages
- bot interactions
- company/client context
- advisor routing
- thread/topic logic depending on implementation

Do not assume chat routing logic without checking the actual classes.

**Audit verdict (B1):** Telegram module is the best-built in the codebase. Has scope `queryChats()` with 3 ownership levels, Job retries with exponential backoff, allowlist MIME + 25MB limit, escudo de identidad cruzada multi-empresa. **Only 🔴🔴 is webhook fail-open (B1-K1) — 1-line fix.**

### PDF / Contracts

The application may generate PDFs for:

- invoices
- contracts
- responsibility documents
- commercial monthly commission reports (⚠️ with SHA-256 integrity hash that never matches — B10-R3)
- commercial incentive contracts (base + anexos, with SHA-256 hash)
- other client/admin flows

These flows often depend on signed URLs, tokens, state transitions, and business-specific validations.

### TinyMCE (email template editor)

Installed via `amidesfahani/filament-tinyeditor` for visual HTML editing of commission email templates. Configured with `provider: vendor` in `config/filament-tinyeditor.php`, requires `.env` key `TINY_LICENSE_KEY` and the public assets published.

---

## Enums

Business states are typically implemented with PHP enums under `app/Enums/`.

Confirmed enums from audit:

- `LeadEstadoEnum`
- `VentaEstadoEnum` (includes CANCELADA — phantom state, never assigned; RECURRENTE_CANCELADO only in ViewProyecto.php:173)
- `ClienteEstadoEnum` — 10 values: PENDIENTE, PENDIENTE_ASIGNACION, EN_PROYECTO, ACTIVO, IMPAGADO, BLOQUEADO, RESCINDIDO, BAJA, REQUIERE_ATENCION, PROYECTO_FINALIZADO
- `ClienteSuscripcionEstadoEnum` — PENDIENTE_ACTIVACION, ACTIVA, CANCELADA, PAUSADA
- `FacturaEstadoEnum`
- `ProyectoEstadoEnum`
- `DocumentoEstadoEnum` — 5 values: PENDIENTE, VERIFICADO, RECHAZADO, NECESITA_ACLARACION, ARCHIVADO (form admin only shows 3 — B8-B5)
- `FormaDePago`
- `EstadoPago`
- `CicloFacturacionEnum`
- `ServicioTipoEnum` (conceptual — `tipo_servicio` is stored as plain string `'unico'` / `'recurrente'`, not cast to Enum)

Do not change enum backed values lightly. Enum changes may affect:

- stored data
- filters
- policies
- dashboards
- automations
- conditional UI
- jobs
- reports
- integrations

⚠️ **Anti-pattern detected:** Several filters use hardcoded strings that don't exist in their Enum (e.g., `'inactiva'` for ClienteSuscripcionEstadoEnum — B10-R6). Always use Enum values directly, never bare strings.

If unsure, ask first.

---

## Scheduled Commands

This project uses scheduled commands in `routes/console.php` for recurring business operations.

Currently registered schedules:

- `leads:enviar-recordatorios` — daily at 08:00
- `leads:enviar-informe-emails-diario` — daily at 09:00
- `documentos:purge-rechazados --days=30` — daily at 03:10 (⚠️ no legal-hold for fiscal docs — B8-D1)
- `asesorfy:recordatorio-pendientes-respuesta` — daily at 10:00 Madrid
- `notificaciones:enviar-programadas` — every minute
- `stripe:sync` — daily at 02:00 Madrid, no overlapping, one server, background (⚠️ doesn't create StripeSyncRun record — B4-R4)
- `comisiones:calcular-mes` — monthly on day 1 at 03:00 Madrid, no overlapping, one server (⚠️ has critical bugs, see Sprint 0 B10)

Always inspect the scheduler configuration before modifying background behavior. The list above may become stale — `php artisan schedule:list` is the source of truth.

**Production note:** the scheduler requires the OS cron `* * * * * cd /path/to/project && php artisan schedule:run` to be active. Without it, none of the scheduled commands will run. `onOneServer()` depends on shared cache (Redis) — if file-based cache is used, multiple workers may run the same command in parallel with no protection.

---

## Frontend Notes

The project may use:

- Tailwind CSS
- Livewire
- Alpine
- Volt
- Flux components
- Vite

Filament themes may be compiled through Vite.

Do not replace the frontend stack or styling approach unless explicitly requested.

When `npm run build` fails with "Could not resolve entry module" for a CSS file inside `resources/css/filament/...`, check whether that file is still referenced in `vite.config.js` after a plugin was uninstalled.

---

## Public / Tokenized Routes

The application may expose unauthenticated routes such as:

- lead conversion routes
- responsibility signing routes
- payment routes
- Stripe setup routes
- portal activation routes
- commercial incentive contract signing (`/contrato-incentivos/{token}`)
- commercial incentive contract PDF preview (`/contrato-incentivos-pdf/{token}` and `/contrato-incentivos-pdf-firmado/{token}`)

These flows are sensitive because they may depend on:

- tokens
- signatures
- expiration
- state transitions
- security validation
- contract acceptance
- payment setup completion

Do not change these routes casually.

Authenticated download routes (for example `/descargar-contrato-firmado/{id}`) must still enforce ownership — verify `Auth::id() === $record->comercial_id` (or similar) before serving the file, and grant access to privileged roles like `super_admin` or `coordinador` if applicable.

---

## Multi-Company Portal Context

The portal supports users linked to multiple `Cliente` records.

An active client context is stored in:

- `session('cliente_activo_id')`

Do not assume all portal logic is single-company. Before modifying portal queries, visibility rules, chat logic, documents, invoices, or dashboard behavior, verify whether they depend on the active client context.

**Confirmed ownership patterns (B9):**

- **Correct:** `$user?->clientes()->where('clientes.id', $clienteActivoId)->first()` (Dashboard, Suscripcion, Chats)
- **Weak:** `Cliente::find($clienteActivoId)` (NotificacionesWidget — B9-D2). Relies on middleware validation only.

If middleware ever fails or gets bypassed, only the first pattern fails closed. Always use the first pattern in new code.

⚠️ **Clients in BAJA/RESCINDIDO still appear in empresa selector and have full portal access** (B9-E1). Document this business decision or filter the selector.

⚠️ **Covert context change:** `/portal/metodo-de-pago?cliente_id=X` silently sets `cliente_activo_id` without passing SeleccionarEmpresa UI (B9-D1).

If unclear, ask first.

---

## Storage and Documents

Document handling is important in this project.

Before modifying document upload, access, display, purge, or linking behavior, verify:

- storage path
- visibility rules
- polymorphic relation target
- portal/admin access rules
- validation rules
- duplicate handling (SHA-256 per-cliente, not global)
- purge/rejection flows

Do not assume current storage conventions without checking the real code.

**Current state (pending Sprint 0 fixes):**

- **Client documents:** public disk ⚠️ — B8-A2 fix required (move to `local` + authenticated controller)
- **Commission reports and signed contracts:** `local` disk ✅ (correct)
- **Telegram chat attachments:** `local` disk ✅ (correct)

Always use `Storage::disk('local')` for sensitive content. `Storage::disk('public')` is ONLY for static assets (logos, public images), NEVER for business documents.

---

## Testing and Verification

When changing logic:

- prefer adding or updating tests when appropriate
- run relevant tests if possible
- run code style checks with Pint
- explain manual verification steps clearly

At minimum, always provide:

- files changed
- expected behavior after change
- manual test steps
- possible regressions or side effects

---

## Lessons Learned — For Future Development

This project has just completed a 316-finding audit. Apply these rules to all new modules (WordPress integration, Qdrant/AI, new features, etc.).

### Before starting any new module

1. **Design the Policy FIRST.** Before writing the first Resource, write the Policy with real ownership checks. Register it. Invoke `->authorize()` in every action.
2. **Never commit `filament:make-resource` scaffold without depuring.** Remove every field the user shouldn't edit. Replace the default form with a deliberate one.
3. **Define `$fillable` explicitly.** Never use `$guarded = []` in any model. It's a footgun.
4. **Default to `local` disk for business content.** Public disk is ONLY for static assets (logos, public images).
5. **Guards must be fail-closed.** If config is missing, DENY. Never `if ($secret && check()) deny;` — always `if (!$secret || !check()) deny;`.
6. **Validate ownership at the action level**, not just via query scope. Defense in depth.
7. **Never `$shouldSkipAuthorization = true`** unless you've written a Policy alternative (portal middleware, scope) AND documented why in code comments.
8. **One canonical way to do each business action.** If you need a second way, remove the first or explicitly document the difference.
9. **Observers are your friend for sync**, but always wrap notification sending in try/catch — don't let observer failure roll back the main save.
10. **State machines for financial data:** 'pagada' → 'aprobada' → 'borrador' transitions must be explicit. Never let a recalculation overwrite a 'pagada' record.

### When adding AI/Qdrant (future)

- Never send fiscal client data to external APIs without explicit consent and anonymization
- Log all AI interactions for audit trail
- Rate limit per user and per request type
- Validate AI responses before showing to client (don't let hallucinations become fiscal advice)
- `llms.txt` and AI metadata files must be access-controlled appropriately

### When adding WordPress integration (future)

- Any WordPress → AsesorFy webhook must validate signature + whitelist IPs
- Never expose Asesorfy API keys in WordPress config (use read-only tokens)
- Rate limit inbound webhooks
- Audit log all inbound data
- Consider webhook replay attacks

---

## What Claude Code Should Do By Default

By default, Claude Code should behave conservatively in this repository:

- inspect first
- check Sprint 0 list before touching any code in affected files
- ask if unclear
- change little
- preserve naming
- preserve UX
- respect observers
- respect business rules
- avoid assumptions
- explain impact
- flag if the change touches any of the 7 Demons or 5 Patterns
- verify safely

If there is uncertainty, asking the user is the correct behavior.

---

## Final Rule

**When in doubt, ask Juan Antonio before changing the code.**

This repository contains real business logic, real billing flows, real commission calculations (affecting real employee pay), and production-sensitive behavior.

Do not guess.
