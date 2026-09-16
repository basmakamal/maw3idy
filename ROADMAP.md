# Maw3idy — Project Roadmap

Multi-tenant appointment booking platform (Laravel 12). Target: 4 weeks, part-time.
Check items off as you go — this file lives in the repo root so the roadmap itself is part of the portfolio.

> **Deviations from the original plan** (2026-09-10): Laravel 12 instead of 11 (11 is out of
> support; 13 needs PHP 8.3 which the dev machine lacks). No Docker Compose — the native
> PHP/MySQL stack is used locally and CI is the reproducible environment. Composer scripts
> replace the Makefile (`make` is not available on Windows). See README → Architecture decisions.

---

## Phase 0 — Day 0: Repo & environment (½ day) ✅

- [x] Create GitHub repo `maw3idy` (public), MIT license, `.gitignore` for Laravel
- [x] Laravel 12 scaffold with Pest 3, Larastan, Pint
- [x] ~~Docker Compose~~ → native stack: PHP 8.2 + MariaDB locally, MySQL 8 service in CI
- [x] `.env.example` complete enough that `cp .env.example .env` just works
- [x] ~~Makefile~~ → composer scripts: `composer setup`, `composer dev`, `composer test`, `composer check`, `composer fresh`
- [x] GitHub Actions workflow: Pint + Larastan + dependency audit + Pest (PHP 8.2 & 8.3, MySQL 8) on every push/PR
- [x] Dependabot for composer, npm and GitHub Actions
- [x] Security baseline: security headers middleware, strict Eloquent, immutable dates, password policy, `SECURITY.md`
- [x] Architecture tests (Pest presets: php, security, laravel) so conventions are enforced, not just documented
- [x] README skeleton: one-paragraph pitch + "Status: in development" badge + first ADRs
- [x] First commit pushed, CI green

**Done when:** a stranger can clone the repo and get a running app with 2 commands.

---

## Phase 1 — Week 1: Multi-tenancy foundation ✅

### Tenancy core
- [x] `tenants` table: id, name, slug (subdomain), timezone, locale, settings JSON
- [x] Subdomain routing: `{tenant}.maw3idy.localhost` → `IdentifyTenant` resolves via a `TenantResolver` strategy, binds `TenantContext` (scoped singleton), sets locale and route defaults, forgets it on terminate
- [x] `BelongsToTenant` trait: fail-closed global scope on `tenant_id` + auto-fill on create + write guards (`TenantMismatchException` on cross-tenant create or move)
- [x] Central domain (`maw3idy.localhost`) serves the landing + registration only; nothing is routed without a domain
- [x] Tenant identification ordered before authentication / bindings in the middleware priority list
- [x] Livewire update endpoint re-registered on the tenant domain behind `IdentifyTenant`

### Auth & onboarding
- [x] Owner registration on central domain → `RegisterTenant` action (transaction, DTO, `TenantRegistered` event) → redirects to the tenant login page; reserved + malformed subdomains rejected, per-IP rate limit
- [x] Login scoped per tenant (user of tenant A cannot log in on tenant B's subdomain); per-account + per-IP throttling, session regeneration, host-only cookies
- [x] Dashboard shell (Livewire + Tailwind, RTL-aware): sidebar, empty states for Services / Staff / Calendar, working owner-only Settings page (name, timezone, language) behind `TenantPolicy`

### Tests (the ones that matter most)
- [x] `tenant A cannot see tenant B data` — query isolation
- [x] `tenant A user cannot authenticate on tenant B subdomain`
- [x] `scopes new records to the current tenant automatically`
- [x] Plus: fail-closed without a tenant, `withoutTenancy()` escape hatch, `runAs()` restore semantics, session replay across tenants, subdomain parsing edge cases, architecture rule "every model is tenant scoped unless explicitly central"

### README
- [x] ADR-006 single-DB + `tenant_id` vs DB-per-tenant (and when to choose the opposite), ADR-007 fail-closed scope, ADR-008 subdomains + host-only sessions, ADR-009 middleware ordering

**Done when:** two tenants registered, fully isolated, CI proves it.

Deferred from this phase: cross-subdomain auto-login after registration (signed single-use handoff token), password reset (the `password_reset_tokens` table is keyed by email and must become tenant-aware first), email verification.

---

## Phase 2 — Week 2: Booking domain ✅

### Data model
- [x] `services`: name, description, duration_minutes, price, buffer_after_minutes, active
- [x] `staff`: name, email, active, optional user link; pivot `service_staff`
- [x] `schedules`: staff working hours per ISO weekday (start, end as local times)
- [x] `time_off`: staff UTC ranges (vacations, breaks) with a reason
- [x] `bookings`: service, staff, customer_name/phone/email, starts_at/ends_at (UTC via `UtcDateTime` cast), snapshot of duration/buffer/price, status (confirmed/cancelled), reference, cancel_token, `slot_lock` for the unique index
- [x] Every model tenant-scoped (architecture test), MariaDB-safe `DATETIME` columns

### Availability engine — the centerpiece
- [x] Pure `SlotGenerator::generate(SlotRequest, StaffCalendar): Collection<Period>` — no DB, no clock, no tenant lookup; `AvailabilityService` is the single Eloquent seam
- [x] Handles: working hours (incl. split shifts), service duration + buffer, overlaps, time off, "no slots in the past", tenant timezone, configurable grid interval
- [x] Unit-tested exhaustively: closing-time fit, buffer spilling past closing, back-to-back with and without buffer, existing booking's buffer, overnight spill-over, whole-day absence, "now" boundary, DST spring-forward/fall-back days, swallowed DST block, invalid inputs
- [x] Target ~100% coverage on the engine (26 unit cases; measured in Phase 4's coverage gate)

### Public booking flow (no auth)
- [x] `{tenant}.maw3idy.localhost/book`: service → staff (or "anyone available") → date → slot grid → name + phone (+ optional email) → confirm; every id and time re-validated server-side; phone normalisation incl. Arabic-Indic digits; per-IP rate limit
- [x] Double-booking guard: staff row lock + locking re-check + insert, unique index as backstop (ADR-011); tested with a unique-index race simulation **and** a `Concurrency` suite that fires 3–4 real PHP processes at the same slot
- [x] Confirmation page with booking reference, read from the session so no URL exposes customer data

### Dashboard (added to make the flow usable end to end)
- [x] Services: list for everyone, owner adds/edits/hides, assigns staff (foreign ids rejected)
- [x] Staff: list with links to each member's hours, owner adds/edits/deactivates, assigns services
- [x] Hours & time off per staff member: one block per weekday in the UI (engine supports more), time off entered in tenant time and stored as UTC; route model binding is tenant-scoped (foreign staff id → 404)

**Done when:** end-to-end booking works and `SlotGenerator` tests read like documentation.

Deferred from this phase: minimum notice / lead time before a slot, multiple blocks per weekday in the UI, gap-filling slots that start at the end of a blocker instead of on the grid, customer-facing cancel/reschedule (Phase 3).

---

## Phase 3 — Week 3: Product polish

### Notifications
- [ ] `BookingConfirmed` + `BookingReminder` notifications, queued (database driver locally, Redis in production via config only)
- [ ] Reminder scheduled 24h before via `schedule` + query, not delayed jobs (survives redeploys — note this in README)
- [ ] Channel abstraction: `NotificationChannel` interface with `MailChannel` implemented, `WhatsAppChannel` stubbed — shows the pattern without the API cost

### Manage bookings
- [ ] Signed cancellation/reschedule URL in the confirmation email
- [ ] Dashboard calendar (day/week view) for the business, filter by staff
- [ ] Booking statuses + cancellation reason

### Timezones & localization
- [ ] Store UTC, display in tenant timezone; customer-facing pages state the timezone explicitly
- [ ] `ar` + `en` locales, RTL layout for Arabic (logical Tailwind properties: `ms-`/`me-`), language switcher per tenant
- [ ] Seeded demo tenant in Arabic to screenshot for the README

**Done when:** a real salon could use it for a week without hitting a wall.

---

## Phase 4 — Week 4: Professional wrapper & launch

### API
- [ ] `/api/v1`: auth via Sanctum tokens, endpoints for services, availability, bookings (create/cancel)
- [ ] Rate limiting on the public availability endpoint
- [ ] OpenAPI docs generated with Scribe, published via GitHub Pages or `/docs`

### Quality gate
- [ ] Coverage: availability engine ~100%, booking flow feature tests, tenancy isolation tests — overall ~80%
- [x] Pint + Larastan (level 6) in CI — from Phase 0; raise the level as the codebase grows
- [ ] `composer demo`: seeds 2 tenants, 3 staff, services, a week of bookings
- [ ] Content-Security-Policy with per-request nonces once the Vite/Livewire asset story is settled

### Ship it
- [ ] Deploy demo (small VPS / Railway / Fly.io) with a real subdomain wildcard + Let's Encrypt wildcard cert
- [ ] README final: GIF of booking flow, architecture diagram (Mermaid), "Design decisions", "Running locally", "Roadmap / out of scope"
- [ ] Roadmap section lists v2 ideas explicitly NOT built: payments, subscriptions, SMS provider, mobile app
- [ ] Pin the repo on your GitHub profile; add 3-line description + topics (laravel, multi-tenancy, saas, booking)

**Done when:** the repo answers every "can she build production systems?" question before the interview starts.

---

## Working rules

1. **Commit small and often** — the history is part of the portfolio. Conventional commits (`feat:`, `fix:`, `test:`).
2. **Branch per phase**, PR to main with a self-review — even solo, it shows process.
3. **When behind schedule, cut features, never tests or the README.**
4. **Every architectural choice gets 2–3 lines in the README** — decisions are what interviewers ask about.

## Suggested weekly rhythm (part-time, ~10h/week)

| Day | Focus |
|-----|-------|
| Sat | Big block: main feature of the phase (4h) |
| Mon | Tests for what Saturday built (2h) |
| Wed | Small feature / refactor (2h) |
| Thu | Polish, README notes, commit cleanup (2h) |
