<h1 align="center">Maw3idy — مَوعِدي</h1>

<p align="center">
  Multi-tenant appointment booking for small service businesses — salons, clinics, tutors.<br>
  Each business gets its own subdomain, staff schedules, and a public booking page; the platform
  guarantees isolation, correct availability across timezones, and no double bookings.
</p>

<p align="center">
  <a href="https://github.com/basmakamal/maw3idy/actions/workflows/ci.yml"><img src="https://github.com/basmakamal/maw3idy/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <img src="https://img.shields.io/badge/status-in%20development-orange" alt="Status: in development">
  <img src="https://img.shields.io/badge/PHP-%5E8.2-777BB4?logo=php&logoColor=white" alt="PHP ^8.2">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue" alt="MIT"></a>
</p>

> **Status:** in development. Phases 0–2 are complete: repo and CI, multi-tenancy with
> tenant-scoped auth, and the booking domain (services, staff, schedules, a pure availability
> engine, a race-proof public booking flow). Progress is tracked in [`ROADMAP.md`](ROADMAP.md);
> each phase lands as a pull request.

---

## Running locally

Prerequisites: PHP 8.2+, Composer 2, Node 22, MySQL 8 / MariaDB 10.4+.

```bash
mysql -uroot -e "CREATE DATABASE maw3idy; CREATE DATABASE maw3idy_test;"
composer setup   # install, .env, app key, migrate, build assets
composer dev     # server + queue worker + logs + Vite, in one terminal
```

Then open <http://maw3idy.localhost:8000> and register a business. Tenants live on
subdomains, e.g. <http://demo.maw3idy.localhost:8000>; `*.localhost` resolves to the loopback
address in every modern browser, so no hosts-file entries are needed.

`composer fresh` seeds two demo tenants, each with services, staff, weekly hours and a few
upcoming bookings. Sign in with the password `password`, or book as a customer on `/book`:

| Tenant | Dashboard | Public booking page | Owner |
|--------|-----------|---------------------|-------|
| Demo Salon (English) | `demo.maw3idy.localhost:8000` | `demo.maw3idy.localhost:8000/book` | `owner@demo.test` |
| صالون الجمال (Arabic, RTL) | `jamal.maw3idy.localhost:8000` | `jamal.maw3idy.localhost:8000/book` | `owner@jamal.test` |

The test suite has three parts: `Unit` (the availability engine runs without a database),
`Feature` (HTTP and Livewire, each test in a rolled-back transaction) and `Concurrency`
(separate PHP processes fired at the same slot against committed data). `composer test` runs
all three.

Quality gates — the same ones CI runs:

```bash
composer check          # pint --test, larastan (level 6), pest
composer test           # just the test suite
composer test:coverage  # with coverage report (needs pcov or xdebug)
composer lint           # auto-fix code style
```

## Stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 12, PHP 8.2+ |
| UI | Blade + Livewire, Tailwind CSS 4 (RTL-aware) |
| Data | MySQL 8, single database with `tenant_id` scoping |
| Queue / cache | Database driver locally; Redis-ready via config |
| Testing | Pest 3 (unit, feature, architecture presets), Larastan level 6, Pint |
| CI | GitHub Actions: style → static analysis → dependency audit → tests on PHP 8.2 & 8.3 against MySQL 8 |

## Architecture decisions

Short, dated records of the choices that shape the codebase. Newer decisions are appended
as the project grows; the tenancy and availability-engine decisions arrive with their phases.

**ADR-001 · Laravel 12 on PHP 8.2 (2026-09-10).** Laravel 13 requires PHP 8.3; the
development machine ships PHP 8.2 and the project deliberately avoids Docker. Laravel 12 is
still under security support, the upgrade path to 13 is mechanical, and CI already tests
against PHP 8.3 so the jump costs nothing later.

**ADR-002 · No Docker; CI is the reproducible environment (2026-09-10).** Local runs use
the native PHP/MySQL stack. Reproducibility comes from a pinned `composer.lock` /
`package-lock.json` and a CI pipeline that builds from a clean Ubuntu image with a real
MySQL service on every push. Trade-off: contributors need PHP and MySQL installed; gain: no
virtualisation overhead and a simpler mental model for a solo project.

**ADR-003 · Tests run on MySQL, not SQLite (2026-09-10).** The two riskiest behaviours in
this domain — tenant isolation and the double-booking guard (unique index + row lock in a
transaction) — depend on real database semantics. SQLite would make the suite faster and lie
about correctness. The test database is reset per test with `RefreshDatabase`.

**ADR-004 · Immutable dates everywhere (2026-09-10).** `Date::use(CarbonImmutable::class)`
is set globally. The availability engine does heavy date arithmetic; mutable Carbon lets
`$slot->addMinutes()` silently corrupt the value the caller still holds. Immutability turns
that bug class into a non-issue.

**ADR-005 · Fail loudly outside production (2026-09-10).** `Model::shouldBeStrict()` is on
in local/testing: lazy loading, missing attributes and silently discarded fills throw, so
the test suite catches them. In production the same code degrades gracefully. Destructive
database commands are prohibited in production.

**ADR-006 · One database, a `tenant_id` on every row (2026-09-16).** Database-per-tenant
buys physical isolation and per-tenant restore at the price of N migrations, N backups, N
connection pools and a tenant-aware migrator on every deploy. For a product whose tenants
are salons with hundreds of rows each, that is the wrong trade: a single schema with
`tenant_id` costs one migration run, one backup, and lets the platform do cross-tenant
reporting with a plain query. Isolation becomes an application invariant instead of a
physical one, so it is enforced in three layers: a global scope on every tenant-owned model
(`BelongsToTenant`), write guards that throw on any cross-tenant create or move
(`TenantMismatchException`), and an architecture test that fails the build if a model in
`App\Models` forgets the trait. Composite unique indexes (`tenant_id, email`) keep
uniqueness per tenant. Choose the opposite when a regulator demands data residency per
customer, when one tenant is large enough to need its own scaling and restore story, or
when tenants need schema customisation.

**ADR-007 · Fail closed when no tenant is bound (2026-09-16).** Querying a tenant-owned
model with no tenant in context throws `TenantNotBoundException`. Returning every tenant's
rows would be a data leak; returning none would hide bugs as empty screens. Cross-tenant
work is therefore explicit and greppable: `TenantContext::runAs($tenant, fn)` for acting on
behalf of one tenant (registration, jobs, the reminder scheduler), `Model::withoutTenancy()`
for a deliberate global query. Console commands and jobs must declare their tenant, which
is a feature.

**ADR-008 · Subdomain per tenant, host-only sessions (2026-09-16).** The tenant is read
from the host (`acme.maw3idy.test`) by a `TenantResolver` strategy, so the API can later
bind a header- or token-based resolver without touching the middleware. Session cookies
stay host-only (no leading-dot `SESSION_DOMAIN`): a session created on one tenant is never
even presented to another. Should a shared cookie ever arrive, the user provider looks the
session's user up inside the tenant scope and finds nothing. Registration happens on the
central domain and hands off to the tenant's own login page rather than auto-signing in
across subdomains, which would need a signed single-use token; that polish is deferred.
Locally `*.localhost` gives zero-config subdomains; production needs a wildcard DNS record
and a wildcard certificate.

**ADR-009 · Tenant identification runs before authentication (2026-09-16).**
`IdentifyTenant` is prepended to the middleware priority list ahead of `Authenticate`,
`ThrottleRequests` and `SubstituteBindings`, so the session user, rate-limit keys and
route-model bindings all resolve inside the tenant scope. Livewire's update endpoint is
re-registered on the tenant domain with the same middleware; otherwise component
re-hydration would run tenantless and fail closed. The tenant is forgotten in the
middleware's `terminate()` so nothing outlives its request.

**ADR-010 · The availability engine is pure (2026-09-16).** `SlotGenerator` takes data and
returns data: a request (day, timezone, duration, buffer, grid interval) and a staff
member's calendar (weekly hours, absences, existing bookings) in, UTC periods out. No
queries, no clock of its own, no tenant lookup. Every hard case (split shifts, closing-time
fit, back-to-back with and without buffers, overnight spill-over, "now", DST days) is a unit
test that runs in milliseconds without a database, and `AvailabilityService` is the single
seam where Eloquent enters. The cost is a handful of small value objects (`Period`,
`WorkingHours`, `SlotRequest`, `StaffCalendar`); the gain is that the centrepiece of the
product is its most tested and least coupled code.

**ADR-011 · Double-booking guard: lock, re-check, insert, with a unique index as backstop
(2026-09-16).** Two customers can pick the same slot at the same second. `CreateBooking`
runs one transaction: `SELECT … FOR UPDATE` on the staff member's row serialises every
attempt for that person; availability is then re-checked reading existing bookings with
`FOR UPDATE` as well, because under MySQL's default REPEATABLE READ a plain `SELECT` may
return the snapshot taken before the lock was granted; only then is the row inserted. A
unique index on `(staff_id, starts_at, slot_lock)` remains as the last line of defence
(`slot_lock` is `NULL` for cancelled bookings, so cancelled slots reopen), and its violation
is translated into the same `SlotUnavailableException`. The index alone would not do: it
cannot see two bookings with different starts that overlap. "Anyone available" resolves the
candidates first and locks them in id order so concurrent requests cannot deadlock. The
`Concurrency` test suite proves it with separate PHP processes released at the same
millisecond: exactly one wins.

**ADR-012 · Time model: UTC instants inside, tenant-local calendar at the edges
(2026-09-16).** Weekly hours are local clock times per weekday (a salon opens at 09:00
whether or not the clocks changed), bookings and time off are UTC instants, and a "day" for
availability is a calendar date in the tenant's timezone. Eloquent's datetime cast formats a
Carbon instance in *its own* timezone, which silently stores local times as UTC; a
`UtcDateTime` cast converts on every write so that mistake cannot be made. Buffer semantics
are deliberate and tested: the service must fit within working hours, its buffer may spill
past closing or into time off, but neither the service nor its buffer may touch another
booking or that booking's buffer.

**ADR-013 · Bookings snapshot the service (2026-09-16).** Duration, buffer and price are
copied onto the booking. Renaming, repricing or shortening a service afterwards changes
future offers but never rewrites what a customer already agreed to, and the availability
engine keeps blocking the time that was actually promised.

## Security baseline

See [`SECURITY.md`](SECURITY.md). In short: global security-headers middleware, strict
Eloquent, a centralised password policy with breach checking in production, dependency
audits on every CI run, and Dependabot. Architecture tests (Pest presets `php`, `security`,
`laravel`) fail the build on `dd()`, `md5()`, `eval()` and friends.

## Roadmap

The full plan, with checkboxes, lives in [`ROADMAP.md`](ROADMAP.md). Explicitly **out of
scope** for v1: payments, subscriptions, SMS provider integration, mobile apps.

## License

[MIT](LICENSE) © 2026 Basma Kamal
