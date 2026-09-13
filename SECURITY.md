# Security Policy

Maw3idy is a portfolio project, but it is built as if it were going to production.

## Reporting a vulnerability

Please **do not** open a public issue. Email the maintainer directly (address on the
GitHub profile) with:

- a description of the issue and its impact,
- steps to reproduce or a proof of concept,
- the commit hash you tested against.

You will get an acknowledgement within 72 hours.

## Baseline controls

- Dependencies are audited on every CI run (`composer audit`, `npm audit`) and kept
  current by Dependabot.
- Security response headers are applied globally by `App\Http\Middleware\SecurityHeaders`.
- Eloquent runs in strict mode outside production (no lazy loading, no silently
  discarded attributes), and destructive `artisan db:*`/`migrate:fresh` commands are
  prohibited in production.
- Password rules are enforced centrally via `Password::defaults()`; production requires
  12+ chars, mixed case, numbers, symbols and a breach check (Have I Been Pwned).
- Tenant isolation is enforced by a global query scope and covered by dedicated tests
  (see `ROADMAP.md`, Phase 1).
