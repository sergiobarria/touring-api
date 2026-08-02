# Development Guide

## Prerequisites

- PHP 8.3 or newer with Laravel's required extensions, PostgreSQL support, and Imagick
- Composer
- PostgreSQL
- Node.js and npm
- Credentials for Stripe, Cloudflare R2, and Resend when exercising those integrations

Automated tests use in-memory SQLite and isolate most external integrations with fakes or mocks.

## Initial setup

Create the PostgreSQL database described by `.env.example`, then run:

```shell
composer run setup
```

This installs Composer dependencies, creates `.env` if absent, generates `APP_KEY`, migrates, seeds canonical permissions and roles, installs JavaScript dependencies, and builds assets. It does not load the local tour fixture set.

Review `.env` before starting the application. At minimum, ensure the database is reachable and `APP_URL` exactly matches the host you will use.

## Running locally

```shell
composer run dev
```

The development workflow runs five named processes together:

- Laravel's HTTP server
- Queue listener
- Pail log viewer
- Vite development server
- Laravel scheduler worker

Running only `php artisan serve` is insufficient for queued email, hold expiration, refund reconciliation, and scheduled health results.

## Development data

```shell
php artisan db:seed
```

`DatabaseSeeder` always synchronizes permissions and roles. In `local`, it also creates lead guides, supporting guides, regular users, tours, guide assignments, departures, image fixtures, and reviews.

The fixture set creates 20 tours. Tour properties use realistic ranges, each tour has one lead guide and up to four unique supporting guides, and departures occur in the next 180 days. Reviews come from distinct regular users and rating aggregates are derived from the persisted rows.

The tour factory's opt-in `withImages(minimum: 1, maximum: 2)` state copies JPEG, PNG, or WebP fixtures from `data/assets` to the configured media disk. It does not move or mutate source fixtures. Configure storage before seeding images; invalid ranges or missing assets fail explicitly.

The seed data is fictional and must not be treated as production content or credentials.

## Common commands

```shell
# Full test suite
composer test

# Focused test
php artisan test --compact --filter=testName

# Format changed PHP files
vendor/bin/pint --dirty --format agent

# Build or serve assets
npm run build
npm run dev

# Inspect application surfaces
php artisan route:list --path=api/v1
php artisan schedule:list
php artisan about
```

Promote an existing account to the first administrator by ULID or email:

```shell
php artisan users:promote-admin user@example.com
```

Promotion replaces the user's current role; it does not create an account.

## Testing strategy

Tests use Pest 5 with PHPUnit. Put HTTP and persistence behavior in `tests/Feature` and isolated logic in `tests/Unit`. Prefer factories for setup and `RefreshDatabase` for persistence isolation.

Feature coverage should remain aligned with the contract documents:

- Authentication, email verification, password management, token lifecycle, and response secrecy.
- Sole-role enforcement, permissions, ownership, administrative account restrictions, and auditing.
- Public tour visibility, sorting, filtering, pagination, sparse fieldsets, includes, and computed values.
- Tour, guide-team, image, and departure writes, including soft deletion and storage failures.
- UTC uniqueness, historical visibility, and capacity invariants.
- Booking verification, snapshots, price rounding, idempotency, concurrency, webhooks, expiration, cancellation, refunds, and seat restoration.
- Review purchase qualification, ownership, uniqueness, validation, and rating recomputation.
- Analytics ordering, boundaries, numeric normalization, and empty results.
- Health behavior, rate limiting, trusted hosts, and sensitive-field redaction.

Tests that exercise protected behavior seed the canonical authorization data. Never depend on local development seed data in automated tests.

## OpenAPI workflow

Scramble generates the field-level HTTP reference from the registered v1 API:

```shell
php artisan scramble:analyze --api=v1
php artisan scramble:export --api=v1
```

The running application serves the UI at `/docs/v1` and JSON at `/docs/v1.json`. The unversioned default Scramble routes are disabled so contracts cannot mix versions.

When changing HTTP behavior:

1. Update validation, controller/resource documentation, and tests.
2. Update [API behavior](03_API.md) when the behavior or workflow changes.
3. Run Scramble analysis and inspect the exported operations and schemas.
4. Confirm public operations have no Bearer requirement and protected operations do.

## Contributor workflow

Follow the repository's controller/action/service boundaries described in [Architecture](04_ARCHITECTURE.md). Use typed parameters, returns, properties, and constants; declare immutable classes readonly; and use explicit exception documentation when failures intentionally propagate.

Before handing off a change:

```shell
vendor/bin/pint --dirty --format agent
composer test
php artisan scramble:analyze --api=v1
```

Update the relevant contract document in the same change. Do not add endpoint schemas to Markdown when OpenAPI already expresses them; document intent and non-obvious behavior instead.

## Media cleanup in development

Inspect before deleting anything:

```shell
php artisan r2:purge-media
```

Execution targets the entire configured R2 bucket and matching media rows, and is restricted to `local` and `testing`. Read [Operations](06_OPERATIONS.md#r2-media) before using its execution flags.
