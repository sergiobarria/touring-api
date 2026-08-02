# API Behavior

This document describes Touring's HTTP conventions and non-obvious workflows. The generated OpenAPI UI at `/docs/v1` and JSON document at `/docs/v1.json` are authoritative for field-level request and response schemas.

## Base conventions

- Version 1 is served below `/api/v1`.
- JSON requests should send `Accept: application/json`; JSON writes use a plain top-level object.
- Image uploads use `multipart/form-data`.
- Resource responses contain `type`, `id`, and `attributes`, following the project's JSON:API-style representation.
- Resource types are `users`, `tours`, `tour_start_dates`, `tour_images`, `bookings`, and `reviews`.
- User, tour, departure, booking, and review IDs are ULIDs. Tour image IDs are integers.
- Timestamps are ISO 8601 UTC values.
- `PATCH` is used for partial updates. Unsupported fields and empty patches are validation errors.

## Authentication and authorization

Sanctum tokens use the Bearer scheme:

```http
Authorization: Bearer <token>
```

Registration and login return a user resource plus a one-time plaintext `token` and token metadata. Registration returns `201 Created`; login returns `200 OK`. Logout returns `204 No Content` and revokes only the current token.

Catalog, departure, and review reads are public. Review writes require authentication and author ownership. Booking operations require an authenticated, verified account and are scoped to the owner. User management, tour mutations, image/departure management, and analytics require their corresponding permissions.

Authorization occurs before validation or target disclosure on administrative operations. Missing or invalid authentication returns `401`; insufficient capability or ownership returns `403`; nested resources that do not belong to their parent return `404`.

## Query conventions

Tour listing supports pagination, allowed sorting, text/exact/range filters, sparse fieldsets, and optional `startDates` inclusion through Spatie Query Builder. Examples:

```http
GET /api/v1/tours?sort=price,-rating_avg&per_page=15&page=2
GET /api/v1/tours?filter[difficulty]=moderate&filter[price][from]=500&filter[price][to]=1500
GET /api/v1/tours?fields[tours]=name,slug,price
GET /api/v1/tours?include=startDates&fields[tour_start_dates]=start_datetime_utc,available_spots
```

`id` and `type` remain present with sparse fieldsets. Fieldsets affect serialization rather than database projection. Unsupported filters, sorts, and includes return `400 Bad Request`; invalid values return `422 Unprocessable Entity`.

Collection pagination metadata and links retain the active query string. Review lists are deterministic and newest-first; departure lists are chronological. Consult OpenAPI for each endpoint's allowed query values and limits.

## Endpoint groups

| Group | Routes | Access and purpose |
| --- | --- | --- |
| Authentication | `/auth/*` | Register, login, logout, password recovery/change, email verification, and current profile |
| Users | `/users/*` | Permission-protected account listing, creation, inspection, role replacement, and deletion |
| Tours | `/tours`, `/tours/{tour}` | Public active catalog reads; permission-protected writes |
| Images | `/tours/{tour}/images/*` | Permission-protected gallery upload and owned image deletion |
| Departures | `/tours/{tour}/start-dates/*` | Public reads; permission-protected writes |
| Reviews | `/tours/{tour}/reviews/*` | Public reads; authenticated qualified/owned writes |
| Bookings | `/bookings/*` | Verified owner creation, listing, detail, and cancellation |
| Stripe webhook | `/stripe/webhook` | Signed Stripe event ingestion with a dedicated rate limiter |
| Analytics | `/tour-analytics/*` | Permission-protected top tours, grouped statistics, and monthly plan |

Use `php artisan route:list --path=api/v1` for the exact registered methods and names.

## Stateful workflows

### Email verification and password management

Registration queues a verification message after commit. The frontend receives a link containing the temporary signed API verification URL. Resending is authenticated and returns `204` even when already verified. Password-reset requests return `202` generically; successful reset and authenticated password change return `204`.

### Booking checkout

Booking creation requires an idempotency key in the schema-defined request header and a traveler roster. A free total returns a confirmed booking. A paid total returns a pending booking with Stripe Checkout data. The client must redirect to Stripe; it must not treat the browser success URL as fulfillment proof. Only a matching signed webhook confirms payment.

Repeated webhook events and booking requests are safe. A hold that expires or a confirmed booking that is successfully cancelled restores its seats exactly once.

### Reviews

Public users can list and retrieve reviews. An authenticated user can create one only after a confirmed purchased departure is in the past. Updates and deletes require ownership; mismatched tour/review nesting returns `404`.

### Tour deletion

Tour and departure deletion is soft deletion and returns `204`. Deleted resources disappear from normal public and administrative route binding, but historical related data remains. Restoration endpoints do not exist.

## Status and error behavior

- `200 OK`: successful read, login, or partial update.
- `201 Created`: registration and resource creation; create operations include a `Location` header where defined by OpenAPI.
- `202 Accepted`: generic password-reset request acceptance.
- `204 No Content`: successful logout, password mutation, notification resend, deletion, or cancellation response where documented.
- `400 Bad Request`: unsupported Query Builder capability.
- `401 Unauthorized`: missing, malformed, expired, or revoked Bearer token.
- `403 Forbidden`: authenticated user lacks permission or ownership.
- `404 Not Found`: unknown, deleted, inactive where public visibility requires active status, or incorrectly nested resource.
- `405 Method Not Allowed`: an unregistered method such as `PUT` on partial-update resources.
- `422 Unprocessable Entity`: validation, business-rule, credentials, or unknown write-field failure.
- `429 Too Many Requests`: rate limiting or credential lockout.

Laravel validation responses contain a message and field-keyed errors. Credential and recovery responses avoid revealing whether an email exists.

## Rate limits and caching

Every versioned route receives the named global API limiter except the Stripe webhook, which uses its dedicated limiter. Guests receive 60 requests per minute per IP; authenticated requests receive 120 per minute per user. More restrictive cumulative limiters protect registration, login, verification, account updates, password recovery, and Stripe events.

Login also locks a normalized email/IP pair after repeated credential failures. Production should use a shared Redis `RATE_LIMITER_STORE` across application instances.

Token, credentials, current-account, booking, and other sensitive responses use `Cache-Control: no-store, private` and `Pragma: no-cache` where configured. Telescope redacts secrets and credential fields.

## API verification

```shell
php artisan route:list --path=api/v1
php artisan scramble:analyze --api=v1
php artisan scramble:export --api=v1
```

Behavioral feature tests cover authentication, authorization, catalog queries, writes, images, departures, reviews, bookings, analytics, health, and security.
