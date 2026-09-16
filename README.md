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

> **Status:** in development. Phase 0 (repo, CI, hardening baseline) and Phase 1
> (multi-tenancy, registration, tenant-scoped auth, dashboard shell) are complete.
> Progress is tracked in [`ROADMAP.md`](ROADMAP.md); each phase lands as a pull request.

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

`composer fresh` seeds two demo tenants you can sign in to with the password `password`:

| Tenant | URL | Owner |
|--------|-----|-------|
| Demo Salon (English) | `demo.maw3idy.localhost:8000` | `owner@demo.test` |
| صالون الجمال (Arabic, RTL) | `jamal.maw3idy.localhost:8000` | `owner@jamal.test` |

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
