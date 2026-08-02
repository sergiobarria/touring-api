# Operations Guide

This guide describes the infrastructure required to operate Touring. Environment-specific credentials must remain outside version control.

## Environment baseline

Production must set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://www.example.com
```

`APP_URL` must be the exact externally accepted API origin. Host validation trusts that hostname, and the origin participates in signed verification URLs. Serve the application only through HTTPS.

Use PostgreSQL for application data and review every migration before applying it to a shared database. Run canonical seeders after migration when roles or permissions change:

```shell
php artisan db:seed --class=Database\\Seeders\\PermissionSeeder --force
php artisan db:seed --class=Database\\Seeders\\RoleSeeder --force
```

Do not run the local fixture seeders in production.

## Queue and scheduler

Production requires at least one persistent queue worker and infrastructure that invokes:

```shell
php artisan schedule:run
```

once per minute. Restart workers after deployments so they load current code:

```shell
php artisan queue:restart
```

Monitor failed jobs and retry them only after identifying whether the operation is safe to repeat. Notification payloads for sensitive account workflows are encrypted.

Scheduled responsibilities are:

| Frequency | Work |
| --- | --- |
| Every minute | Expire booking holds; run health checks |
| Every 10 minutes | Reconcile failed/pending booking refunds |
| Every 15 minutes | Clear expired password reset tokens |
| Daily | Prune Telescope data, expired Sanctum tokens, and health history |

Use `php artisan schedule:list` after deployment to confirm registration and next-run times.

## Health and monitoring

- `GET /up` is the lightweight liveness probe and does not validate dependencies.
- `GET /health` reads the latest scheduled check batch and returns only `{ "healthy": true }` or `{ "healthy": false }`.
- Healthy readiness returns `200`; stale, unavailable, or failed results return `503`.

The endpoint never runs checks on demand and never exposes diagnostic details. Results older than two minutes are unhealthy and history is retained for seven days.

Non-production checks validate the default database. Production also checks disk usage, `APP_ENV=production`, and disabled debug mode. Configure the scheduler before enabling readiness-based traffic management; otherwise results will become stale.

Monitor HTTP error rates, queue failures and latency, scheduler execution, Stripe webhook failures, payment/refund states, storage errors, and database capacity in addition to `/health`.

## Rate limiting and cache

All versioned routes use named limits. In a multi-instance deployment, configure shared Redis-backed counters:

```dotenv
CACHE_STORE=redis
RATE_LIMITER_STORE=redis
```

Authenticated global limits are keyed by user ULID and guest limits by IP. Authentication, password, verification, account-update, and Stripe webhook endpoints add narrower cumulative limits. Preserve correct proxy/client IP configuration in the hosting environment.

## Transactional email

Production email uses Resend:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME=Touring
```

Use a verified sending domain. `FRONTEND_URL` receives password-reset and verification handoffs; `APP_URL` remains the signed API origin. Monitor queue failures because registration and resend responses deliberately do not expose notification-delivery failures to clients.

## Stripe

Configure:

```dotenv
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=usd
STRIPE_CHECKOUT_HOLD_MINUTES=30
BOOKING_CANCELLATION_CUTOFF_HOURS=48
```

Register the public webhook target as `POST ${APP_URL}/api/v1/stripe/webhook` and store its signing secret. The webhook is the fulfillment authority; successful browser redirects are not.

Monitor bookings left in `pending_payment`, `cancellation_pending`, or `cancellation_failed`. The scheduler normally expires holds and reconciles refunds. The commands can also be run deliberately:

```shell
php artisan bookings:expire-holds
php artisan bookings:reconcile-refunds
```

These commands are designed for repeat execution, but investigate Stripe and queue state before manual recovery. Seat restoration must remain exactly once.

## R2 media

Spatie Media Library stores originals and conversions on the R2 disk:

```dotenv
IMAGE_DRIVER=imagick
MEDIA_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_URL=https://media.example.com
R2_REGION=auto
```

`R2_ENDPOINT` is the authenticated S3-compatible API endpoint. `R2_URL` is the public base URL returned to clients and may use a custom domain. Grant the application only the bucket permissions it needs and configure appropriate CORS and public-delivery controls outside this repository.

The cleanup command is for local and testing environments only and targets the entire configured bucket:

```shell
# Dry-run inventory
php artisan r2:purge-media

# Execute after interactive confirmation
php artisan r2:purge-media --execute

# Execute non-interactively in local/testing
php artisan r2:purge-media --execute --force
```

Execution deletes objects, verifies the bucket is empty, and then removes matching R2 media rows. Failures retain database rows. The command refuses staging and production even with `--force`.

## Telescope and sensitive data

Telescope is a development observability aid and is excluded from automatic package discovery. If deliberately enabled in an environment, restrict access and preserve redaction of passwords, confirmations, current passwords, reset tokens, authorization headers, and plaintext access tokens.

Responses containing authentication or sensitive account/booking data use no-store middleware. Confirm these headers at the edge and avoid CDN caching of authenticated API responses.

## Deployment checklist

1. Install locked dependencies and build production assets.
2. Set production environment, exact URLs, encryption key, database, Redis, queue, mail, Stripe, and R2 secrets.
3. Run reviewed migrations and canonical permission/role seeders.
4. Cache production configuration and routes according to the deployment platform.
5. Start/restart queue workers and verify the minutely scheduler.
6. Validate `/up`, wait for a scheduled health batch, then validate `/health`.
7. Verify Stripe webhook delivery and signatures in the target environment.
8. Verify queued mail and public media URLs.
9. Confirm `/docs/v1` exposure matches the organization's documentation-access policy.
10. Run a smoke test for public catalog reads and a protected request without logging secrets.

## Security constraints

- Never commit `.env`, R2, Stripe, Resend, database, or Redis credentials.
- Keep authentication-protected routes behind Sanctum and application permissions.
- Apply named limiters centrally rather than embedding numeric limits in routes.
- Keep `APP_URL` aligned with the exact public API host.
- Preserve no-store headers and Telescope redaction when adding credentials.
- Tour writes are currently protected by explicit permissions. Any temporary public-write change must be documented, isolated, and removed before production release.
