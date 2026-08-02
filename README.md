# Touring REST API

Touring is a Laravel 13 REST API for a fictional guided-tour marketplace. It supports a public tour catalog, guide teams, scheduled departures, image galleries, authenticated bookings and cancellations through Stripe, purchase-qualified reviews, user administration, and tour analytics.

This repository is the API backend. A separate client is expected to handle customer-facing pages and the email verification, password reset, and Stripe checkout return flows.

## Features

- Versioned JSON API at `/api/v1` with generated OpenAPI documentation.
- Sanctum Bearer-token authentication, email verification, and password recovery.
- Permission-based administration with one primary role per user.
- Tour catalog filtering, sorting, pagination, sparse fieldsets, and relationship inclusion.
- Lead and supporting guide assignments, departure capacity, and ordered R2 image galleries.
- Verified-user bookings with idempotent seat holds and Stripe Checkout fulfillment.
- Cancellation and full-refund reconciliation with exactly-once seat restoration.
- Reviews restricted to customers with a completed, confirmed tour purchase.
- Rating aggregates, tour statistics, top-tour rankings, and monthly planning reports.
- Auditing, rate limiting, readiness checks, queues, and scheduled maintenance.

## Technology

- PHP 8.3 or newer and Laravel 13
- PostgreSQL for development and production; in-memory SQLite for tests
- Laravel Sanctum, Spatie Permission, Query Builder, Media Library, Health, and Sluggable
- Stripe Checkout and refunds
- Cloudflare R2 through its S3-compatible API
- Resend for production transactional email
- Pest 5 and PHPUnit
- Scramble-generated OpenAPI documentation

The image pipeline requires the PHP Imagick extension. Node.js and npm are used to build the small Vite-managed frontend asset bundle.

## Local setup

1. Install PHP, Composer, PostgreSQL, Node.js, npm, Imagick, and the PHP extensions required by Laravel and PostgreSQL.
2. Create the PostgreSQL database named in `.env.example`, or change the database variables for your environment.
3. Run the setup workflow:

   ```shell
   composer run setup
   ```

The workflow installs PHP and JavaScript dependencies, creates `.env` when absent, generates the application key, migrates the database, seeds canonical permissions and roles, and builds assets.

Start the application services with:

```shell
composer run dev
```

This starts the Laravel development server, queue listener, Pail logs, Vite, and scheduler. Laravel's development server normally listens at `http://127.0.0.1:8000`; set `APP_URL=http://127.0.0.1:8000` before using it so host validation and signed email-verification links use the same origin. The API is available below `${APP_URL}/api/v1`.

To load local demonstration tours, guides, users, images, and reviews, run:

```shell
php artisan db:seed
```

Local fixtures may write tour images to the configured `MEDIA_DISK`. Configure R2 first or use an appropriate local testing configuration.

## Configuration

Copy `.env.example` to `.env` when configuring manually. The main integration settings are:

| Area | Variables |
| --- | --- |
| Application | `APP_URL`, `FRONTEND_URL`, `APP_ENV`, `APP_DEBUG` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Queues and cache | `QUEUE_CONNECTION`, `CACHE_STORE`, `RATE_LIMITER_STORE` |
| Email | `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| Stripe | `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY` |
| Media | `IMAGE_DRIVER`, `MEDIA_DISK`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_URL` |

Local email defaults to the log transport. Production uses Resend and must use a verified sender. Paid bookings require valid Stripe credentials and a webhook forwarding events to `POST /api/v1/stripe/webhook`.

Never commit credentials or environment-specific configuration. See [Operations](docs/06_OPERATIONS.md) for the complete production checklist.

## API quick start

Interactive API documentation is available while the app is running:

- UI: `/docs/v1`
- OpenAPI JSON: `/docs/v1.json`

Register and capture the one-time plaintext token:

```shell
curl -X POST "http://127.0.0.1:8000/api/v1/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Alex Traveler","email":"alex@example.com","password":"password","password_confirmation":"password"}'
```

Use that token on protected operations:

```shell
curl "http://127.0.0.1:8000/api/v1/auth/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Public catalog example:

```shell
curl "http://127.0.0.1:8000/api/v1/tours?sort=price&filter[difficulty]=easy&per_page=10" \
  -H "Accept: application/json"
```

The generated OpenAPI document is authoritative for request fields and response schemas. [API behavior](docs/03_API.md) explains authentication, permissions, query conventions, errors, and stateful workflows.

## Development commands

```shell
# Run all tests
composer test

# Run a focused Pest test
php artisan test --compact --filter=testName

# Format changed PHP files
vendor/bin/pint --dirty --format agent

# Analyze and export the v1 OpenAPI document
php artisan scramble:analyze --api=v1
php artisan scramble:export --api=v1

# Inspect scheduled tasks and registered API routes
php artisan schedule:list
php artisan route:list --path=api/v1
```

Maintenance commands include `bookings:expire-holds`, `bookings:reconcile-refunds`, `users:promote-admin`, and the local/testing-only `r2:purge-media`. See [Development](docs/05_DEVELOPMENT.md) and [Operations](docs/06_OPERATIONS.md) before running commands that affect external services.

## Architecture

HTTP controllers validate and serialize requests but delegate each use case to a dedicated action. Shared domain or integration capabilities live in focused services, and readonly DTOs form stable input/output boundaries. Required business steps are explicitly orchestrated; transactions protect cross-record invariants, and asynchronous notifications are dispatched only after commit.

Start with these documents:

| Document | Purpose |
| --- | --- |
| [Business](docs/00_BUSINESS.md) | Fictional company context, customers, and product rationale |
| [Specification](docs/01_SPEC.md) | Contract index and system-wide requirements |
| [Domain](docs/02_DOMAIN.md) | Entities, lifecycle rules, and business invariants |
| [API](docs/03_API.md) | HTTP conventions, endpoint groups, and workflows |
| [Architecture](docs/04_ARCHITECTURE.md) | Code organization, integrations, and data flows |
| [Development](docs/05_DEVELOPMENT.md) | Setup, fixtures, testing, and contributor workflow |
| [Operations](docs/06_OPERATIONS.md) | Production configuration, health, scheduling, and maintenance |

## Production status

The application contains production-oriented controls, but deployment still requires deliberate infrastructure configuration: PostgreSQL, persistent queues, a continuously running worker, a minutely scheduler, shared rate-limit storage, Resend, Stripe webhooks, R2, HTTPS, and monitoring of `/health`.

Do not infer production readiness from local defaults. Review migrations before applying them to a shared database, set `APP_ENV=production` and `APP_DEBUG=false`, and verify the exact public `APP_URL`. Tour write routes are currently permission-protected; any future public-write exception must be treated as an explicit temporary state and removed before release.
