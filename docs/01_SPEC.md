# Touring API Specification

## 1. Purpose and status

Touring API is a versioned REST API for publishing and discovering guided tours. This document is the living product and API specification: it records the behavior a developer must reproduce, rather than serving only as implementation notes.

Every feature change must update this specification in the same implementation pass so that it remains the source of truth for the application's requirements and public contract.

The current implementation provides Sanctum API-token authentication, email verification, password management, permission-protected tour administration, tour-level guide teams, a public tour catalog, CRUD operations for tours and their start dates, ordered tour image galleries, Stripe-backed authenticated bookings, purchase-qualified reviews, and admin-only tour analytics. Restoration remains outside the current scope.

## 2. Technical conventions

- The application is built with Laravel 13 and PHP 8.3 or newer.
- PostgreSQL is the primary development and production database. Automated tests use in-memory SQLite for isolation and speed.
- Application-owned model primary keys are ULIDs, including users, tours, tour start dates, reviews, and health-check history. Foreign and polymorphic references to these models use the same ULID type.
- Framework and third-party infrastructure tables may retain package-compatible identifiers when replacing them would add coupling without improving the public contract. This applies to queue internals, Telescope, audit row IDs, Sanctum token row IDs, Spatie media rows, and Spatie role and permission rows. Polymorphic references from package tables to application models retain the application's ULID type. Tour images expose Spatie's integer media identifier for owned image operations; no other database sequence IDs are public.
- API routes are versioned. Version 1 is mounted below `/api/v1` and uses the `v1.` route-name prefix.
- Responses use Laravel JSON:API resources.
- Query filtering, sorting, and relationship inclusion use Spatie Laravel Query Builder.
- UTC is the canonical timezone for storing, comparing, generating, testing, and transmitting timestamps.
- API timestamps use ISO 8601 with an explicit UTC offset. Clients convert them to a user's timezone for display.
- Features that depend on a destination's wall-clock time or daylight-saving rules must additionally store an IANA timezone instead of changing the canonical UTC instant.
- User, tour, and tour start-date model changes are auditable. User audits exclude password hashes and remember tokens; password updates expose only a `password_changed` marker. Role and supporting-guide pivot changes record explicit old/new assignments.
- Tour slugs are generated from tour names and must be unique.
- Validated tour and start-date write data crosses the HTTP boundary through native readonly DTOs before model persistence.
- HTTP controllers accept validated input, delegate application use cases to actions, and serialize responses. Each action represents one use case; reusable capabilities shared by actions belong in focused services, while stable typed results or inputs use readonly DTOs.
- Authentication is the first feature implemented with this pattern: register, login, and logout delegate to dedicated actions, and token issuance and current-token revocation share an access-token service.
- Required workflows use explicit orchestration: actions and services call their required collaborators directly. Events and listeners are not used for required state changes, authorization, request correctness, or hidden sequencing. Asynchronous jobs and notifications are dispatched explicitly after surrounding database transactions commit. Events are reserved for optional fan-out, observability, or narrowly documented framework lifecycle compatibility, and application correctness must remain independent of listeners.
- Tour and tour start-date deletion workflows use soft deletes. Individual media records are hard-deleted only through the explicit owned-image endpoint after their stored files and conversions are removed.
- Authentication uses Laravel Sanctum personal access tokens supplied through the Bearer authorization scheme. Catalog, start-date, and review reads are public. Review writes require authentication and ownership; administrative tour mutations and analytics require admin-only permissions.

### 2.1 Operational health

The application exposes two unversioned, public health endpoints that are intentionally excluded from the Scramble API documentation:

- `GET /up` is Laravel's lightweight application liveness probe. It does not verify external dependencies.
- `GET /health` is the readiness probe. It returns `200` with `{ "healthy": true }` when every registered check passes and `503 Service Unavailable` with `{ "healthy": false }` when checks are unhealthy or their stored results cannot be read. It never exposes diagnostic details.

The readiness checks run every minute and their ULID-keyed results are retained in the database for seven days. The public endpoint only reads the latest scheduled result batch and treats results older than two minutes as unhealthy; requests, including those with a `fresh` query parameter, do not execute checks or write history. Notifications are disabled. Local, testing, and other non-production environments check only the default database connection. Production additionally verifies that disk usage is below the package's warning and failure thresholds of 70% and 90%, `APP_ENV` is `production`, and debug mode is disabled.

Local development runs Laravel's scheduler through `composer run dev`. Production infrastructure must invoke `php artisan schedule:run` once per minute. Expired Sanctum token records are pruned daily after they have been expired for 24 hours.

### 2.2 API security baseline

Every versioned API route uses a named global rate limiter. Guests receive 60 requests per minute per IP address. Authenticated requests receive 120 requests per minute per user ULID; the authenticated identity takes precedence over the request IP. Registration additionally allows five requests per minute per IP, login allows 30 requests per minute per IP, email-verification requests allow six requests per minute per authenticated user ULID or guest IP, and password/profile updates share a limit of ten attempts per minute per authenticated user ULID. Endpoint-specific limits are cumulative with the global limit. Login's separate five-failed-credential lockout remains keyed by normalized email and IP so request-volume protection does not weaken brute-force protection.

Rate-limit counters use the cache store named by `RATE_LIMITER_STORE`. The example environment uses the database store, while automated tests omit the setting and fall back to their default array cache. Production should set `RATE_LIMITER_STORE=redis` when Redis is available so counters are shared efficiently by every application instance.

Host-header validation trusts only the exact hostname configured in `APP_URL`; subdomains are not implicitly trusted. Production configuration must therefore set `APP_URL` to the API's canonical externally reachable URL. Successful and validation-error responses from registration and login include `Cache-Control: no-store, private` and `Pragma: no-cache` so token and credential-related payloads are not retained by clients or intermediaries. Telescope redacts passwords, password confirmations, current passwords, reset tokens, authorization headers, and returned plaintext access tokens.

Public tour and start-date reads receive the global guest/IP rate limit. Protected tour requests receive the authenticated per-user limit after Sanctum resolves the Bearer token.

## 3. Domain model

### 3.1 Tour

A tour is the public catalog item users browse. It has the following persisted fields:

| Field | Type | Rules and meaning |
| --- | --- | --- |
| `id` | ULID | Primary key. |
| `name` | string | Display name. |
| `lead_guide_id` | ULID | Required foreign key to a user whose sole role is `lead-guide`. |
| `slug` | string | Unique URL-safe value generated from `name`. |
| `duration_days` | unsigned integer | Number of days in the tour. |
| `max_group_size` | unsigned integer | Maximum number of participants. |
| `difficulty` | enum | One of `easy`, `moderate`, or `difficult`; defaults to `easy`. |
| `price` | decimal(10,2) | Base price; defaults to `0.00`. |
| `price_discount_percent` | nullable decimal(5,2) | Discount percentage. `null` means no discount. |
| `rating_avg` | nullable decimal(3,2) | Average rating. It is `null` when the tour has no ratings. |
| `rating_count` | unsigned integer | Number of ratings; defaults to `0`. |
| `summary` | string, maximum 500 characters | Compact catalog description. |
| `description` | nullable text | Full detail description. |
| `is_active` | boolean | Controls public visibility; defaults to `true`. |
| `created_at`, `updated_at` | timestamps | Internal lifecycle timestamps. |
| `deleted_at` | nullable timestamp | Marks a soft-deleted tour; `null` means the tour has not been deleted. |

`difficulty` and `duration_days` are indexed for catalog queries.

The model exposes a computed `duration_weeks` attribute. It is calculated as `duration_days / 7`, rounded to one decimal place. For example, 10 days is returned as `1.4` weeks.

The model also exposes `upcoming_dates`, an ordered array of ISO 8601 UTC strings derived from its start dates. It contains every non-deleted, active start date strictly later than the current UTC time. Sold-out dates remain present because this attribute describes the schedule rather than booking availability. Tours without qualifying dates return an empty array.

Ratings are authoritative aggregates of review rows. A tour without reviews has `rating_avg = null` and `rating_count = 0`; a reviewed tour stores the review count and average integer rating rounded to two decimal places. Review mutations lock the tour and recompute both values in the same transaction.

Tour names do not need to be unique. Slugs are generated and updated from names, with numeric suffixes added when necessary. Slugs belonging to soft-deleted tours remain reserved.

Every tour has exactly one lead guide and zero through four supporting guides, for at most five assigned people. Supporting assignments use the unique `guide_tour` pivot and may reference only users whose sole role is `guide`; the lead cannot also be a supporting guide. User deletion is restricted by both guide foreign keys, and the user-management API rejects deletion or an incompatible role change while assignments remain. Tour soft deletion preserves its team. Public tour resources expose `lead_guide` as `{id, name}` and `guides` as a name-then-ULID ordered array of `{id, name}` objects; guide emails are never exposed there.

### 3.2 Tour start date

A tour can have many scheduled start dates. Each start date belongs to exactly one tour.

| Field | Type | Rules and meaning |
| --- | --- | --- |
| `id` | ULID | Primary key. |
| `tour_id` | ULID | Foreign key to `tours.id`. |
| `start_datetime_utc` | timestamp | Scheduled start in UTC. |
| `available_spots` | unsigned integer | Remaining capacity; defaults to `0` and cannot exceed the tour's `max_group_size`. |
| `reserved_spots` | unsigned integer | Internal count of spots held by pending or confirmed bookings; defaults to `0` and is not client-writable. |
| `is_active` | boolean | Whether this departure is active; defaults to `true`. |
| `created_at`, `updated_at` | timestamps | Internal lifecycle timestamps. |
| `deleted_at` | nullable timestamp | Marks a soft-deleted start date; `null` means it has not been deleted. |

Soft-deleting a tour does not delete or modify its start dates. This preserves the complete schedule for a future restoration. The database foreign key retains a cascading hard-delete constraint as an integrity fallback, but application workflows must not invoke it.

The combination of `tour_id` and `start_datetime_utc` is unique. Inputs representing the same instant with different UTC offsets are duplicates because timestamps are normalized to UTC. Soft-deleted records continue reserving their tour and instant. A separate `start_datetime_utc` index supports analytics queries spanning every tour in a calendar year. Available plus reserved spots may never exceed the tour maximum; start-date edits and tour capacity reductions enforce this invariant.

### 3.3 Tour image

Tour images are managed by Spatie Media Library in the `tour-images` collection and stored on the `r2` filesystem disk. A tour can contain from zero through ten images. Their collection order is their display order; the first image is the cover. Clients cannot reorder images in this iteration. Deleting an image closes the resulting position gap while preserving the relative order of the remaining images.

Accepted originals are JPEG, PNG, and WebP files no larger than 10 MB each. Every upload synchronously creates these WebP conversions:

| Conversion | Dimensions | Behavior |
| --- | --- | --- |
| `card` | 1200×800 | Centered crop. |
| `thumbnail` | 480×320 | Centered crop. |

The ordered `images` attribute appears on every tour representation. Tours without images return an empty array. Each embedded image object contains:

| Field | Type | Meaning |
| --- | --- | --- |
| `id` | integer | Spatie media identifier. |
| `position` | integer | One-based collection position. |
| `is_cover` | boolean | `true` only when `position` is `1`. |
| `name` | string | Display name derived from the uploaded filename. |
| `mime_type` | nullable string | Detected MIME type of the original. |
| `size_bytes` | integer | Original file size in bytes. |
| `original_url` | string | Public URL for the original. |
| `card_url` | string | Public URL for the card conversion. |
| `thumbnail_url` | string | Public URL for the thumbnail conversion. |

Soft-deleting a tour preserves its media and R2 objects. Individual image deletion removes the original and every conversion. Storage deletion failures propagate so the database transaction can retain the media row rather than silently orphaning storage objects.

### 3.4 Tour review

A review belongs to exactly one tour and one user. A user may review a given tour at most once; the database enforces this with a unique `(tour_id, user_id)` constraint.

| Field | Type | Rules and meaning |
| --- | --- | --- |
| `id` | ULID | Primary key. |
| `tour_id` | ULID | Foreign key to the reviewed tour. |
| `user_id` | ULID | Foreign key to the author. |
| `rating` | unsigned integer | Required integer from 1 through 5. |
| `review` | text | Required non-whitespace text, maximum 2,000 characters. |
| `created_at`, `updated_at` | timestamps | Public lifecycle timestamps. |

Reviews are hard-deleted so an author may submit another later. Soft-deleting a tour preserves its reviews but makes its nested review endpoints unreachable. Permanently deleting a user removes that user's reviews and atomically recomputes each affected tour aggregate.

A review may be created only when its author owns a confirmed booking for the tour whose snapshotted departure instant is in the past. This requirement is rechecked inside the review transaction.

### 3.5 User and API token

A user has a ULID primary key, name, unique lowercase email address, hashed password, nullable verification timestamp, and timestamps. Authentication responses expose only the user's ULID, name, and email address.

Users can have multiple Sanctum personal access tokens, one for each registration or login. Every token uses the internal name `auth-token` and stores a hash of the secret, wildcard abilities, its owning user ULID, and a 30-day expiration timestamp. The plaintext token is returned only when it is created. Logging out deletes only the token used for that request, leaving other tokens valid.

### 3.6 Roles and permissions

Authorization uses Spatie Laravel Permission. Every user has exactly one primary role: `user`, `guide`, `lead-guide`, or `admin`. Public registration always assigns `user`; clients cannot request a role. Changing a role replaces the current role through the shared user-role service, and the database enforces at most one role row per model. The role seeder backfills existing users without a role as `user`.

Application authorization checks capabilities rather than role names. User-management permissions are `users.view-any`, `users.view`, `users.create`, `users.update-role`, and `users.delete`. Tour permissions are `tours.create`, `tours.update`, `tours.delete`, `tours.manage-images`, `tours.manage-start-dates`, and `tours.view-analytics`. Only `admin` receives these permissions. The remaining roles intentionally receive none of them; self-service profile behavior is ownership-based.

`PermissionSeeder` creates the canonical permissions before `RoleSeeder` creates roles, synchronizes their permission sets, and backfills role-less users. Both seeders are idempotent and run in every environment through `DatabaseSeeder`. The setup workflow runs these two seeders explicitly after migrating, without implicitly loading local tour fixtures. Existing deployments must run `php artisan db:seed --class=Database\\Seeders\\PermissionSeeder` followed by `php artisan db:seed --class=Database\\Seeders\\RoleSeeder` after migrating this phase.

The first administrator is bootstrapped from an existing account with `php artisan users:promote-admin <user-ulid-or-email>`. Promotion replaces the existing role and does not create an account.

### 3.7 Password management and transactional email

Password reset uses Laravel's database-backed password broker. Reset links hand off to `${FRONTEND_URL}/reset-password` with the one-time token and normalized email in the query string. Reset requests always return the same accepted response whether the account exists or not. A successful reset changes the password, rotates the remember token, and revokes every Sanctum token. Authenticated password changes require the current password and preserve only the Bearer token used for the request.

Password mutation and token revocation are invoked directly by authentication actions and do not depend on application events or listeners. Login throttling retains Laravel's `Lockout` event solely as a framework-compatible security lifecycle signal; the rate limiter enforces the lockout directly and no listener is required.

Forgot-password and reset submissions each have an independent limit of five requests per IP per minute, separate from registration and login counters. Reset-link notifications are queued with encrypted job payloads, and Laravel's password broker time-boxes both known and unknown email requests to reduce account-enumeration signals. Expired reset tokens are cleared every fifteen minutes. Registration, login, forgot-password, reset-password, and authenticated password-change responses are not cacheable.

Production email uses Laravel's Resend transport and requires `MAIL_MAILER=resend`, `RESEND_API_KEY`, a verified-domain `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, canonical `APP_URL`, and `FRONTEND_URL`. Local development may retain `MAIL_MAILER=log`.

### 3.8 Email verification

Newly registered users start unverified and receive an encrypted queued verification notification after the registration transaction commits. The email links to `${FRONTEND_URL}/verify-email` with a temporary signed API URL in the `verification_url` query parameter. The signature expires after `EMAIL_VERIFICATION_EXPIRE` minutes, which defaults to 60. `APP_URL` must match the public API origin because it is part of the signature.

Following a valid signed API URL marks the matching email as verified and is idempotent. Both the URL signature and the SHA-1 email fingerprint must match; changed email addresses therefore invalidate older links. Verification mutates state explicitly through an action and does not dispatch Laravel's `Verified` event. An authenticated unverified user may request another notification; verified users receive the same `204 No Content` response without another email. Notification queue failures are reported but do not change registration or resend responses.

### 3.9 Administrative user management

Authenticated administrators can list and inspect users, create an account with any canonical role, replace another user's role, and delete another user. Every operation is authorized by its corresponding `users.*` permission rather than by checking the `admin` role name. Non-admin roles receive `403 Forbidden` before validation or target lookup, preventing validation and ULID-enumeration leaks.

Administrative creation requires `name`, normalized `email`, `password`, `password_confirmation`, and one of `user`, `guide`, `lead-guide`, or `admin`. It creates the user and sole role atomically, returns the managed user without an API token, and queues the standard verification email after commit. The initial password must be delivered to the user through a secure channel; invitation-specific password setup remains deferred.

Role replacement and deletion are forbidden for the currently authenticated administrator's own account. Assigned lead/supporting guides must be replaced or removed from every tour before an incompatible role change or user deletion. Deletion is permanent and atomically removes the user's reviews, recomputes affected tour ratings, removes authentication state and the role assignment, then deletes the account.

### 3.10 Self-service profiles

Account identity remains on the `users` table because the current editable fields are only `name` and `email`; a separate one-to-one profile record would add lifecycle and consistency overhead without storing distinct profile-domain data. A profile table should be introduced later only when fields such as biography, avatar preferences, locale, or guide-specific public information establish a meaningful independent profile boundary.

Every authenticated role can update its own name or email. Name changes do not require password confirmation. Email changes require `current_password`, normalize the new address, enforce uniqueness, clear `email_verified_at`, and invalidate password-reset tokens for both the old and new addresses atomically before queuing a new verification notification after commit. Submitting the same normalized email leaves verification unchanged. Password changes remain isolated on `PUT /api/v1/auth/password` and are not accepted by the profile endpoint. Password and profile updates share the named account-update limiter to constrain current-password guessing with a compromised token.

### 3.11 Booking and traveler

A booking belongs to one verified user, one tour, and one tour start date. It snapshots the tour name, departure instant, purchaser identity, discount, USD unit price, total, and traveler roster so later catalog edits do not change the purchase record. Money is stored as integer cents. Effective unit price applies the tour percentage discount and rounds half-up to the nearest cent.

Booking statuses are `pending_payment`, `confirmed`, `cancellation_pending`, `cancelled`, `expired`, and `cancellation_failed`. Each traveler has a full name, RFC-valid email, and E.164 phone number; traveler count is the ticket quantity. Booking and Stripe identifiers are unique, and a user-scoped hash of the required client idempotency key prevents duplicate holds. Users with booking history cannot be deleted.

Creating a paid booking locks its active future departure, atomically consumes the requested spots, and holds them for 30 minutes while a card-only USD Stripe Checkout Session is open. Free and fully discounted bookings confirm without Stripe. Paid fulfillment occurs only from a signed, matching `checkout.session.completed` webhook. Expiration restores seats exactly once through the webhook or the minutely recovery command.

Confirmed bookings may be cancelled in full through 48 hours before the snapshotted departure. Free cancellations complete synchronously. Paid cancellations remain pending until Stripe reports a successful full refund; only then are seats restored. Failed refunds preserve capacity and permit another cancellation attempt based on the original timely request.

## 4. Public API conventions

### 4.1 JSON:API resource types

- Tours use type `tours`.
- Tour start dates use type `tour_start_dates`.
- Tour reviews use type `reviews`.
- Standalone tour image responses use type `tour_images`.
- Users use type `users`.
- User, tour, start-date, and review resources contain their ULID as `id`; tour image resources contain their integer media identifier as `id`. Every resource also contains its `type`.

Internal visibility flags and timestamps are not returned as tour attributes. A start date's `is_active` value is currently part of its public resource representation.

Tour, start-date, and review write requests use a plain top-level JSON object. Image uploads use `multipart/form-data`. Responses retain the JSON:API resource representation.

### 4.2 Sparse fieldsets

Clients can limit attributes with a comma-separated fieldset keyed by JSON:API resource type:

```http
GET /api/v1/tours?fields[tours]=name,slug
GET /api/v1/tours/{tour}?fields[tours]=name,description
GET /api/v1/tours?include=startDates&fields[tour_start_dates]=available_spots
```

Resource `id` and `type` remain present even when sparse fields are requested. Sparse fieldsets affect attributes; they do not perform database column projection.

In Scramble's interactive UI, array-valued parameters must be entered as JSON arrays, for example `["name", "slug"]`. In a normal URL, the equivalent value is the comma-separated string `name,slug`.

### 4.3 Relationship inclusion

The only public include is `startDates`:

```http
GET /api/v1/tours?include=startDates
GET /api/v1/tours/{tour}?include=startDates
```

When requested, `relationships.startDates.data` contains resource identifiers and the complete start-date resources appear in the top-level `included` array. If `startDates` is not included, its full attributes are not side-loaded. This is intentional JSON:API behavior.

Unsupported includes return `400 Bad Request`.

### 4.4 Authentication endpoints

Authentication requests use plain top-level JSON objects. Successful registration and login responses use a JSON:API `users` resource plus top-level token metadata:

```json
{
  "data": {
    "type": "users",
    "id": "01K2EXAMPLEUSERULID0000000",
    "attributes": {
      "name": "Jane Doe",
      "email": "jane@example.com"
    }
  },
  "meta": {
    "access_token": "1|plain-text-token",
    "token_type": "Bearer",
    "expires_at": "2026-09-01T12:00:00+00:00"
  }
}
```

#### Register

```http
POST /api/v1/auth/register
```

The request requires `name`, `email`, `password`, and `password_confirmation`. Emails are trimmed and normalized to lowercase before validation, and passwords use the application's default Laravel password rules and must be confirmed. Unknown fields, including `device_name` and `role`, are rejected. A successful request atomically creates the user, assigns the `user` role, creates a 30-day token, and returns `201 Created`. Roles are internal and are not added to the authentication response in this phase.

Registration is limited to five requests per IP address per minute in addition to the global API limit. Registration responses are not cacheable.

#### Login

```http
POST /api/v1/auth/login
```

The request requires `email` and `password`. Unknown fields, including `device_name`, are rejected. A successful request returns `200 OK` with a new independent 30-day token; existing tokens remain valid. Unknown users and incorrect passwords return the same generic validation error to avoid account enumeration. Login is limited to 30 requests per IP address per minute in addition to the global API limit. Independently, five failed attempts for the same normalized email and IP address within one minute cause subsequent attempts to return `429 Too Many Requests`; a successful login clears that failure counter. Login responses are not cacheable.

#### Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer <token>
```

Logout requires `auth:sanctum`, deletes the current token, and returns `204 No Content`. Missing, malformed, expired, and previously revoked tokens return `401 Unauthorized`. Other tokens owned by the same user remain valid.

#### Password reset and change

- `POST /api/v1/auth/forgot-password` accepts only `email` and returns `202 Accepted` generically.
- `POST /api/v1/auth/reset-password` accepts `email`, `token`, `password`, and `password_confirmation`; success returns `204 No Content` and revokes all tokens.
- `PUT /api/v1/auth/password` requires `auth:sanctum` and accepts `current_password`, `password`, and `password_confirmation`; success returns `204 No Content`, preserves the current token, and revokes the user's other tokens.

#### Email verification

- `GET /api/v1/auth/email/verify/{user}/{hash}` requires a valid, unexpired URL signature; success returns `204 No Content`.
- `POST /api/v1/auth/email/verification-notification` requires `auth:sanctum`, accepts no request fields, queues another verification email when needed, and returns `204 No Content`.
- Both endpoints use the named email-verification limiter and return non-cacheable responses.

#### Administrative users

- `GET /api/v1/users` lists users with their role and verification timestamp.
- `POST /api/v1/users` requires `name`, `email`, `password`, `password_confirmation`, and `role`; success returns `201 Created`.
- `GET /api/v1/users/{user}` returns one managed user by ULID.
- `PATCH /api/v1/users/{user}/role` accepts only `role` and replaces the target's sole role.
- `DELETE /api/v1/users/{user}` permanently deletes the target and returns `204 No Content`.
- Every endpoint requires `auth:sanctum`, the matching user-management permission, and returns a non-cacheable response because managed-user representations contain account data.

#### Current user

- `GET /api/v1/auth/me` requires `auth:sanctum` and is available to every role.
- It returns the authenticated user's name, email, sole role, and email-verification timestamp without requiring an administrative permission.
- The response is non-cacheable. Extended profile-domain fields remain deferred.
- `PATCH /api/v1/auth/me` accepts `name`, `email`, and conditionally `current_password`; at least one of `name` or `email` is required.
- Changing email requires the current password and starts email verification again. The endpoint never changes roles or passwords.

## 5. Tour endpoints

### 5.1 List active tours

```http
GET /api/v1/tours
```

Returns a paginated collection containing active tours only.

Each list resource exposes these attributes by default:

- `name`
- `slug`
- `max_group_size`
- `duration_days`
- `duration_weeks`
- `upcoming_dates`
- `difficulty`
- `price`
- `price_discount_percent`
- `summary`
- `rating_avg`
- `rating_count`
- `images`

The detail-only `description` field is omitted from the list representation.

#### Pagination

| Parameter | Type | Default | Rules |
| --- | --- | --- | --- |
| `page` | integer | `1` | Minimum `1`. |
| `per_page` | integer | `15` | Minimum `1`, maximum `100`. |

Example:

```http
GET /api/v1/tours?page=2&per_page=25
```

Pagination links preserve the current query string. The response includes Laravel pagination `links` and `meta`, including the current page, per-page count, and total result count.

#### Sorting

The `sort` parameter accepts comma-separated fields. A leading `-` selects descending order.

Allowed sort fields are:

- `name`
- `price`
- `max_group_size`
- `duration_days`
- `created_at`

Examples:

```http
GET /api/v1/tours?sort=price
GET /api/v1/tours?sort=-price,name
```

When `sort` is omitted, tours are ordered by `created_at` ascending and then `name` ascending.

#### Filtering

Filters use bracket notation below the `filter` parameter.

| Filter | Match behavior | Validation |
| --- | --- | --- |
| `filter[name]` | Partial text match | String, maximum 255 characters. |
| `filter[slug]` | Partial text match | String, maximum 255 characters. |
| `filter[difficulty]` | Exact match | `easy`, `moderate`, or `difficult`. |
| `filter[duration_days]` | Exact match | Integer, minimum `1`. |
| `filter[max_group_size]` | Exact match | Integer, minimum `1`. |
| `filter[min_price]` | Inclusive lower bound on base price | Numeric, minimum `0`. |
| `filter[max_price]` | Inclusive upper bound on base price | Numeric, minimum `0`. |

When both price bounds are supplied, `max_price` must be greater than or equal to `min_price`.

Examples:

```http
GET /api/v1/tours?filter[name]=Forest
GET /api/v1/tours?filter[difficulty]=easy
GET /api/v1/tours?filter[duration_days]=5&filter[max_group_size]=20
GET /api/v1/tours?filter[min_price]=300&filter[max_price]=800
```

Filtering, sorting, pagination, includes, and sparse fields may be combined in one request.

`upcoming_dates` can be requested by itself as a sparse field:

```http
GET /api/v1/tours?fields[tours]=upcoming_dates
```

### 5.2 Retrieve an active tour

```http
GET /api/v1/tours/{tour}
```

`{tour}` is the tour ULID. The endpoint returns `404 Not Found` when the identifier is unknown or malformed, or when the matching tour is inactive.

The detail resource exposes all list attributes plus `description`. It supports the same `include=startDates` relationship and sparse fieldsets as the list endpoint.

Example:

```http
GET /api/v1/tours/01JEXAMPLEULID?include=startDates&fields[tours]=name,description
```

### 5.3 Create a tour

```http
POST /api/v1/tours
```

Creates a tour and returns its complete detail resource with `201 Created`. The response includes a `Location` header containing the new detail endpoint URL.

Example request:

```json
{
  "name": "The Forest Hiker",
  "lead_guide_id": "01JLEADGUIDEEXAMPLE00000000",
  "guide_ids": ["01JGUIDEEXAMPLE000000000001"],
  "duration_days": 5,
  "max_group_size": 25,
  "difficulty": "easy",
  "price": 397,
  "price_discount_percent": 10,
  "summary": "Breathtaking hike through the Canadian Banff National Park.",
  "description": "A complete tour description.",
  "is_active": true
}
```

The required fields are `name`, `lead_guide_id`, `duration_days`, `max_group_size`, `difficulty`, `price`, and `summary`. `guide_ids` defaults to an empty list, `description` and `price_discount_percent` default to `null`, and `is_active` defaults to `true`.

### 5.4 Partially update a tour

```http
PATCH /api/v1/tours/{tour}
```

Partially updates any non-deleted tour by ULID, including inactive tours, and returns its complete detail resource with `200 OK`. At least one writable field is required. Omitted fields retain their current values, while an explicit `null` clears `description` or `price_discount_percent`.

Only PATCH is supported. PUT requests return `405 Method Not Allowed`.

Example request:

```json
{
  "price": 425.50,
  "guide_ids": [],
  "description": null,
  "is_active": false
}
```

#### Tour write validation

| Field | Validation |
| --- | --- |
| `name` | String, maximum 255 characters. |
| `lead_guide_id` | Existing user ULID with the sole `lead-guide` role; required on create. |
| `guide_ids` | Zero through four distinct user ULIDs with the sole `guide` role; replaces supporting assignments when supplied. |
| `duration_days` | Integer from 1 through 255. |
| `max_group_size` | Integer from 1 through 255. |
| `difficulty` | `easy`, `moderate`, or `difficult`. |
| `price` | Decimal from 0 through 99,999,999.99, with at most two decimal places. |
| `price_discount_percent` | Nullable decimal greater than 0 and at most 100, with at most two decimal places. |
| `summary` | String, maximum 500 characters. |
| `description` | Nullable string, maximum 65,535 characters. |
| `is_active` | Boolean. |

The server manages `id`, `slug`, ratings, timestamps, `duration_weeks`, `upcoming_dates`, images, and start dates. These and all unknown request fields are rejected with `422 Unprocessable Entity`. Tour data and guide synchronization are atomic; omitted assignment fields remain unchanged on PATCH and `guide_ids: []` removes every supporting guide.

### 5.5 Delete a tour

```http
DELETE /api/v1/tours/{tour}
```

`{tour}` is the tour ULID. The endpoint can delete an active or inactive tour because `is_active` controls publication rather than deletion eligibility.

A successful request soft-deletes the tour by setting `deleted_at` and returns `204 No Content` with an empty body. Related start dates, media rows, and R2 objects remain unchanged. The deleted tour is excluded from ordinary model queries and from the public list and detail endpoints.

Unknown, malformed, or already-deleted identifiers return `404 Not Found`. Deleted slugs remain reserved by the global unique constraint. No restore or hard-delete endpoint is currently available.

### 5.6 Upload tour images

```http
POST /api/v1/tours/{tour}/images
Content-Type: multipart/form-data
```

The request must contain one or more files under `images[]`. A single request can contain at most ten files, and the cumulative gallery cannot exceed ten images. Only JPEG, PNG, and WebP files up to 10 MB each are accepted. Unknown fields are rejected.

```shell
curl -X POST "${APP_URL}/api/v1/tours/${TOUR_ID}/images" \
  -H 'Accept: application/json' \
  -F 'images[]=@/path/to/cover.jpg' \
  -F 'images[]=@/path/to/gallery.webp'
```

A successful upload returns `201 Created` with a JSON:API collection containing only the newly created `tour_images` resources. Each resource uses its media identifier as `id` and exposes `position`, `is_cover`, `name`, `mime_type`, `size_bytes`, `original_url`, `card_url`, and `thumbnail_url` below `attributes`.

Multi-file uploads are all-or-nothing from the API's perspective. If storage or synchronous conversion fails, the database transaction is rolled back and cleanup is attempted for files created earlier in the request.

The endpoint accepts active and inactive tours. Unknown, malformed, or soft-deleted tour identifiers return `404 Not Found`.

### 5.7 Delete a tour image

```http
DELETE /api/v1/tours/{tour}/images/{image}
```

The media identifier must belong to the specified tour and its `tour-images` collection. A successful request removes the original and conversions, hard-deletes the media row, normalizes the remaining positions, and returns `204 No Content`. A mismatched owner, unknown image, unknown tour, or soft-deleted tour returns `404 Not Found`.

## 6. Tour start-date endpoints

Start-date management is nested below its owning tour. All five endpoints accept active or inactive tours, historical or future dates, and only non-deleted records. A soft-deleted parent, unknown or malformed identifier, or start date belonging to another tour returns `404 Not Found`.

### 6.1 List a tour's start dates

```http
GET /api/v1/tours/{tour}/start-dates
```

Returns all non-deleted start dates ordered by `start_datetime_utc` ascending and then ULID. Historical, future, active, inactive, available, and sold-out dates are included. The endpoint uses the same `page` and `per_page` parameters and validation as the tour list.

### 6.2 Retrieve a tour start date

```http
GET /api/v1/tours/{tour}/start-dates/{tourStartDate}
```

Returns the JSON:API `tour_start_dates` resource when it belongs to the parent tour.

### 6.3 Create a tour start date

```http
POST /api/v1/tours/{tour}/start-dates
```

Creates a departure and returns `201 Created`, its complete resource, and a `Location` header for the nested detail endpoint.

```json
{
  "start_datetime_utc": "2026-08-10T04:30:00-05:00",
  "available_spots": 12,
  "is_active": true
}
```

`start_datetime_utc` is required and must be an ISO 8601 datetime with `Z` or an explicit numeric offset. It is normalized and stored as UTC; the example is therefore the same instant as `2026-08-10T09:30:00Z`. `available_spots` defaults to `0`, and `is_active` defaults to `true`.

### 6.4 Partially update a tour start date

```http
PATCH /api/v1/tours/{tour}/start-dates/{tourStartDate}
```

Partially updates a departure and returns `200 OK`. At least one writable field is required; omitted fields retain their values. PUT is not supported.

### 6.5 Delete a tour start date

```http
DELETE /api/v1/tours/{tour}/start-dates/{tourStartDate}
```

Soft-deletes the departure and returns `204 No Content`. It disappears from start-date reads, relationship inclusion, and `upcoming_dates`. Repeated deletion returns `404 Not Found`; no restore or hard-delete endpoint is available.

#### Start-date write validation

| Field | Validation |
| --- | --- |
| `start_datetime_utc` | Timezone-aware ISO 8601 datetime. Required on create and unique within the tour after UTC normalization, including soft-deleted records. |
| `available_spots` | Integer from `0` through the parent tour's `max_group_size`. |
| `is_active` | Boolean. |

The server derives `tour_id` from the nested URL and manages identifiers and timestamps. These fields and all unknown fields are rejected with `422 Unprocessable Entity`. Concurrent duplicate creation or update is also translated from the database uniqueness constraint into a validation error.

## 7. Tour review endpoints

Review reads are public. Write operations require Sanctum authentication. Reviews may be created for active or inactive tours, but a soft-deleted or unknown parent returns `404 Not Found`. Reviews are exposed only through these paginated nested endpoints and are not an allowed tour `include`.

### 7.1 List a tour's reviews

```http
GET /api/v1/tours/{tour}/reviews
```

Returns non-deleted reviews ordered by `created_at` descending and ULID descending. Pagination accepts `page` and `per_page`, defaulting to 1 and 15 with a maximum page size of 100. Each JSON:API `reviews` resource exposes `rating`, `review`, `created_at`, `updated_at`, and `author` as `{id, name}`.

### 7.2 Retrieve a review

```http
GET /api/v1/tours/{tour}/reviews/{review}
```

Returns the review only when it belongs to the parent tour. Mismatched or unknown identifiers return `404 Not Found`.

### 7.3 Create a review

```http
POST /api/v1/tours/{tour}/reviews
```

Requires an integer `rating` from 1 through 5 and a non-whitespace `review` string of at most 2,000 characters. The author is always the authenticated user. A successful request returns `201 Created`, the review resource, and a `Location` header. A second review by the same user for the same tour returns `422 Unprocessable Entity`; the database unique constraint also protects concurrent submissions.

### 7.4 Partially update a review

```http
PATCH /api/v1/tours/{tour}/reviews/{review}
```

The owner may update `rating`, `review`, or both. At least one field is required, unknown fields are rejected, and PUT is not supported. Non-owners receive `403 Forbidden`, including administrators.

### 7.5 Delete a review

```http
DELETE /api/v1/tours/{tour}/reviews/{review}
```

The owner may hard-delete the review and receives `204 No Content`. The user can subsequently submit a replacement review. Create, update, and delete recompute the tour's rating count and average atomically.

## 8. Booking endpoints

All booking endpoints require Sanctum authentication and return `Cache-Control: no-store`. Creation additionally requires a verified email and a UUID `Idempotency-Key` header.

- `POST /api/v1/bookings` accepts `tour_start_date_id` and one through 255 `travelers`. It returns `201 Created` for a new booking and `200 OK` for an identical replay. Reusing a key with different input returns `409 Conflict`.
- `GET /api/v1/bookings` returns the current user's bookings newest first with standard `page` and `per_page` pagination.
- `GET /api/v1/bookings/{booking}` returns an owned booking, traveler roster, checkout URL only while payment is pending, price/departure snapshots, status timestamps, and cancellation eligibility. Other users receive `404 Not Found`.
- `POST /api/v1/bookings/{booking}/cancel` cancels the whole booking under the configured cutoff; partial cancellation and edits are unsupported.
- `POST /api/v1/stripe/webhook` is public, signature-verified against the raw request body, independently rate-limited, and idempotent by Stripe event ID. It consumes `checkout.session.completed`, `checkout.session.expired`, `refund.updated`, and `refund.failed`.

The scheduler runs `bookings:expire-holds` every minute and `bookings:reconcile-refunds` every ten minutes. Confirmation, cancellation-success, and cancellation-failure notifications are encrypted queued jobs dispatched after commit.

Stripe configuration is:

```dotenv
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=usd
STRIPE_CHECKOUT_HOLD_MINUTES=30
BOOKING_CANCELLATION_CUTOFF_HOURS=48
```

Hosted Checkout success and cancellation URLs are derived from `FRONTEND_URL`; no publishable key is required.

## 9. Tour analytics endpoints

Tour analytics are admin-only, read-only views of the active catalog protected by `tours.view-analytics`. All analytics exclude inactive and soft-deleted tours. Monthly analytics additionally exclude inactive and soft-deleted start dates. These endpoints have fixed behavior and do not expose pagination, filtering, custom sorting, includes, or sparse fieldsets.

### 9.1 List top tours

```http
GET /api/v1/tour-analytics/top-tours
```

Returns at most five JSON:API `tours` resources. Rated tours are ordered by `rating_avg` descending, then `price` ascending, and finally ULID ascending. Tours without a rating are ordered after all rated tours. Each resource exposes only `name`, `price`, `rating_avg`, `summary`, `difficulty`, and `images`; request query parameters cannot alter this fieldset or ordering.

### 9.2 Get tour statistics

```http
GET /api/v1/tour-analytics/stats
```

Includes tours with `rating_avg >= 4.5`, groups them by difficulty, and orders groups by average price ascending and then difficulty. Counts are integers and rating/price aggregates are JSON numbers rounded to two decimal places.

```json
{
  "data": {
    "stats": [
      {
        "difficulty": "easy",
        "num_tours": 2,
        "num_ratings": 137,
        "avg_rating": 4.75,
        "avg_price": 525.5,
        "min_price": 350,
        "max_price": 701
      }
    ]
  }
}
```

When no tours qualify, `stats` is an empty array.

### 9.3 Get a monthly tour plan

```http
GET /api/v1/tour-analytics/monthly-plan/{year}
```

`{year}` must contain exactly four digits and range from `1000` through `9999`. Invalid years return `422 Unprocessable Entity` with a validation error for `year`.

The endpoint includes active departures from the inclusive start through the inclusive end of the requested UTC calendar year. Departures are grouped by numeric month. Groups are ordered by `num_tour_starts` descending and then month ascending. Tour names within a group follow departure datetime and ULID order. A tour name is repeated when that tour has multiple departures in the same month, matching the one-name-per-start behavior. Months without departures are omitted.

```json
{
  "data": {
    "plan": [
      {
        "month": 7,
        "num_tour_starts": 3,
        "tours": ["Forest Hiker", "Sea Explorer", "Forest Hiker"]
      }
    ]
  }
}
```

When no departures qualify, `plan` is an empty array.

## 10. Errors and validation

- Successful registration and login return `201 Created` and `200 OK`, respectively, with the user resource and one-time plaintext token metadata.
- Successful logout returns `204 No Content` and revokes only the current token.
- Forgot-password requests return `202 Accepted` generically. Successful password resets and authenticated password changes return `204 No Content`.
- Invalid reset credentials, incorrect current passwords, weak or unconfirmed passwords, and unsupported password fields return `422 Unprocessable Entity`.
- Invalid authentication input, duplicate emails, and incorrect credentials return `422 Unprocessable Entity`; incorrect credentials never distinguish an unknown email from a wrong password.
- Missing, malformed, expired, and revoked Bearer tokens return `401 Unauthorized` on protected endpoints.
- Global API limits, authentication request ceilings, and failed-credential lockouts return `429 Too Many Requests` with retry information.
- Successful list and detail requests return `200 OK`.
- Successful tour creation returns `201 Created`, a detail resource, and a `Location` header.
- Successful partial updates return `200 OK` with the updated detail resource.
- A successful tour deletion returns `204 No Content` with an empty body.
- Successful start-date creation, update, and deletion return `201 Created`, `200 OK`, and `204 No Content`, respectively.
- Successful review creation, update, and deletion return `201 Created`, `200 OK`, and `204 No Content`, respectively.
- Successful image upload and deletion return `201 Created` and `204 No Content`, respectively.
- Invalid list or write input, unknown write fields, and empty PATCH requests return `422 Unprocessable Entity` with Laravel validation errors.
- Unsupported filters, sorts, or includes return `400 Bad Request`.
- A missing, inactive, unknown, malformed, or soft-deleted tour identifier on the detail endpoint returns `404 Not Found`.
- An unknown, malformed, or already-deleted tour identifier on the delete endpoint returns `404 Not Found`.
- An unknown, malformed, or soft-deleted tour identifier on the PATCH endpoint returns `404 Not Found`.
- PUT is not registered for tours and returns `405 Method Not Allowed`.
- Missing parents, mismatched ownership, and unknown, malformed, or soft-deleted start dates return `404 Not Found`.
- Missing or soft-deleted tour parents, mismatched image ownership, and unknown images return `404 Not Found` for image writes.
- Empty image uploads, unsupported image types, files over 10 MB, cumulative galleries over ten images, and unknown upload fields return `422 Unprocessable Entity`.
- PUT is not registered for start dates and returns `405 Method Not Allowed`.
- Duplicate reviews, invalid ratings, blank or overlong review text, unknown review fields, and empty review PATCH requests return `422 Unprocessable Entity`.
- Review writes require authentication; non-owner review updates and deletes return `403 Forbidden`. Mismatched nested reviews and soft-deleted parents return `404 Not Found`. PUT is not registered for reviews.
- Invalid monthly-plan years return `422 Unprocessable Entity`.

## 11. Routing and documentation

`routes/api.php` is the API version dispatcher. Version 1 routes are defined in `routes/api_v1.php` and mounted with the `v1` URL and route-name prefixes.

These routes are public and receive the global guest/IP rate limit:

| Method | URI | Purpose |
| --- | --- | --- |
| `POST` | `/api/v1/auth/register` | Create a user and issue an API token. |
| `POST` | `/api/v1/auth/login` | Exchange credentials for an API token. |
| `POST` | `/api/v1/auth/forgot-password` | Queue a password reset link without exposing account existence. |
| `POST` | `/api/v1/auth/reset-password` | Reset a password using a broker token and revoke all API tokens. |
| `GET` | `/api/v1/auth/email/verify/{user}/{hash}` | Verify the matching email through a temporary signed URL. |
| `GET` | `/api/v1/tours` | List active tours. |
| `GET` | `/api/v1/tours/{tour}` | Retrieve one active tour by ULID. |
| `GET` | `/api/v1/tours/{tour}/start-dates` | List all non-deleted start dates for a tour. |
| `GET` | `/api/v1/tours/{tour}/start-dates/{tourStartDate}` | Retrieve an owned start date. |
| `GET` | `/api/v1/tours/{tour}/reviews` | List reviews newest-first. |
| `GET` | `/api/v1/tours/{tour}/reviews/{review}` | Retrieve an owned review. |

The authenticated routes are:

| Method | URI | Middleware | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/logout` | `auth:sanctum` | Revoke the current Bearer token. |
| `GET` | `/api/v1/auth/me` | `auth:sanctum` | Return the authenticated user's account profile and role. |
| `PATCH` | `/api/v1/auth/me` | `auth:sanctum` | Update the authenticated user's name or email. |
| `POST` | `/api/v1/auth/email/verification-notification` | `auth:sanctum` | Queue another verification email when the account remains unverified. |
| `PUT` | `/api/v1/auth/password` | `auth:sanctum` | Change the password and revoke every other API token. |
| `GET` | `/api/v1/users` | `auth:sanctum`, `users.view-any` | List managed users. |
| `POST` | `/api/v1/users` | `auth:sanctum`, `users.create` | Create a user with a sole role. |
| `GET` | `/api/v1/users/{user}` | `auth:sanctum`, `users.view` | Retrieve a managed user. |
| `PATCH` | `/api/v1/users/{user}/role` | `auth:sanctum`, `users.update-role` | Replace another user's role. |
| `DELETE` | `/api/v1/users/{user}` | `auth:sanctum`, `users.delete` | Permanently delete another user and authentication state. |
| `POST` | `/api/v1/tours` | `auth:sanctum`, `tours.create` | Create a tour and guide team. |
| `PATCH` | `/api/v1/tours/{tour}` | `auth:sanctum`, `tours.update` | Update a tour or replace guide assignments. |
| `DELETE` | `/api/v1/tours/{tour}` | `auth:sanctum`, `tours.delete` | Soft-delete a tour. |
| `POST` | `/api/v1/tours/{tour}/images` | `auth:sanctum`, `tours.manage-images` | Upload tour images. |
| `DELETE` | `/api/v1/tours/{tour}/images/{image}` | `auth:sanctum`, `tours.manage-images` | Delete an owned tour image. |
| `POST` | `/api/v1/tours/{tour}/start-dates` | `auth:sanctum`, `tours.manage-start-dates` | Create a departure. |
| `PATCH`, `DELETE` | `/api/v1/tours/{tour}/start-dates/{tourStartDate}` | `auth:sanctum`, `tours.manage-start-dates` | Update or delete a departure. |
| `POST` | `/api/v1/tours/{tour}/reviews` | `auth:sanctum` | Create the current user's review. |
| `PATCH`, `DELETE` | `/api/v1/tours/{tour}/reviews/{review}` | `auth:sanctum`, ownership policy | Update or delete the current user's review. |
| `GET` | `/api/v1/tour-analytics/*` | `auth:sanctum`, `tours.view-analytics` | View tour analytics. |

Scramble exposes version-specific OpenAPI documentation:

- Interactive documentation: `/docs/v1`
- OpenAPI document: `/docs/v1.json`
- Documented server base path: `/api/v1`
- Authentication operations are grouped under `Authentication`, tour operations under `Tours`, image operations under `Tour Images`, start-date operations under `Tour Start Dates`, review operations under `Tour Reviews`, and analytics under `Tour Analytics`.
- Scramble documents Sanctum-protected operations with the Bearer security scheme and explicitly marks unprotected operations as public.

The default Scramble `/docs/api` and `/docs/api.json` routes are disabled so documentation cannot mix API versions.

## 12. Development data

`DatabaseSeeder` always runs `PermissionSeeder` followed by `RoleSeeder`. In the local environment it then creates ten lead guides, twenty-five supporting guides, and twenty-five regular users before loading tour and review fixtures. Automated feature tests seed canonical authorization data when protected behavior is exercised.

The development seeder creates 20 tours, each with one lead guide, zero through four unique supporting guides, between three and five start dates, and one or two randomly selected images. It then assigns each tour between three and ten reviews from distinct regular users and derives the tour rating aggregates from those rows. These fixtures run only in the `local` environment.

Tour factory data follows these rules:

- Names use a unique `Tour ???-###` pattern and slugs are derived from those names.
- Duration ranges from 1 to 30 days.
- Maximum group size ranges from 4 to 20.
- Difficulty is selected from the `TourDifficulty` enum.
- Base price ranges from 299.00 to 2999.00.
- A discount is generated about 30% of the time and uses a realistic increment: 5%, 10%, 15%, 20%, or 25%.
- Tour factories always start with a null average and zero rating count; only persisted review rows populate these aggregates.
- The opt-in `withImages(minimum: 1, maximum: 2)` factory state copies randomly selected JPEG, PNG, or WebP fixtures from `data/assets` into the configured media disk. Source fixtures remain unchanged, and invalid ranges or a missing fixture set fail explicitly.

Generated start dates occur from 1 to 180 days in the future, use UTC, add a randomized daytime hour and either zero or 30 minutes, and are active about 90% of the time.

## 13. R2 media configuration and cleanup

Spatie Media Library stores originals and conversions on the `r2` disk. Configure these variables locally; credentials must never be committed:

```dotenv
IMAGE_DRIVER=imagick
MEDIA_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_URL=https://pub-0b685df326cd47c6a26c9f3ca20af7f8.r2.dev
R2_REGION=auto
```

`R2_ENDPOINT` is the account's authenticated S3-compatible API endpoint used for storage operations. `R2_URL` is the public base URL returned in API payloads. Development currently uses the configured `r2.dev` URL; production can use a custom public domain without code changes. The filesystem is configured to throw and report storage failures.

The cleanup command is restricted to the `local` and `testing` environments and targets the entire configured R2 bucket, not only one tour:

```shell
# Inspect the object count, total size, and matching media-row count.
php artisan r2:purge-media

# Purge after interactive confirmation.
php artisan r2:purge-media --execute

# Purge without confirmation for non-interactive local/testing workflows.
php artisan r2:purge-media --execute --force
```

Execution deletes every object, verifies the bucket is empty, and only then deletes media rows whose original or conversions disk is `r2`. If inspection, deletion, or verification fails, the command exits unsuccessfully and retains the database rows. It refuses staging and production even when `--force` is supplied.

## 14. Acceptance and verification

Feature coverage must verify:

- Registration validation, lowercase email uniqueness, password hashing, ULID users, immediate token issuance, and response secrecy.
- Default registration roles, one-role database enforcement, role replacement, role-less user backfill, explicit admin permissions, idempotent authorization seeders, and first-admin promotion by ULID or email.
- Valid and invalid login behavior, generic credential failures, independent concurrent tokens, fixed internal token naming, and failed-attempt throttling.
- Bearer authentication, 30-day expiration, current-token logout, preservation of other tokens, and daily expired-token pruning.
- Active-only list and detail visibility.
- Default, ascending, descending, and multi-column ordering.
- Partial text, exact value, and inclusive price-range filters.
- Pagination defaults, custom sizes and pages, query-string preservation, and limits.
- List and detail sparse fieldsets.
- `startDates` relationship linkage and included resources.
- The computed `duration_weeks` value.
- Ordered UTC `upcoming_dates`, including sold-out dates while excluding past, current-time, inactive, and soft-deleted dates.
- Soft deletion of active and inactive tours while preserving their start dates.
- Soft-deleted tours being excluded from normal queries and public endpoints.
- Soft-delete support for tour start dates.
- Typed tour creation with generated unique slugs, defaults, validation, resource output, and a location header.
- Partial updates that preserve omitted and server-managed data while allowing nullable values to be cleared.
- Rejection of invalid, empty, unknown-field, read-only-field, and PUT write requests.
- Validation errors, unsupported query capabilities, and missing tour behavior.
- Nested start-date ownership, inactive-tour access, chronological pagination, and historical-date access.
- Start-date creation defaults, UTC normalization, ULIDs, resource locations, partial updates, and soft deletion.
- Start-date validation for invalid or missing datetimes, capacity, unknown/read-only fields, empty PATCH, and PUT.
- UTC-equivalent duplicate prevention, including soft-deleted records, and database race protection.
- Rejection of tour capacity reductions below existing start-date availability.
- Fixed top-tour limits, fieldsets, null placement, ordering, and active-only visibility.
- Tour-stat grouping, thresholds, aggregate values, numeric normalization, ordering, and empty results.
- Monthly-plan UTC boundaries, grouping, repeated names, deterministic ordering, historical dates, visibility exclusions, year validation, and empty results.
- Ordered images and cover selection in tour list, detail, write, and top-tour analytics payloads without N+1 queries.
- Single and multi-file uploads, synchronous conversions, public URLs, cumulative limits, MIME and size validation, and atomic failure cleanup.
- Owned image deletion, file and conversion removal, normalized positions, storage-failure rollback, and preservation after tour soft deletion.
- Factory and seeder fixture handling without moving source assets.
- Public review reads, authenticated writes, ownership authorization, nested ownership, one-review uniqueness, validation, deterministic pagination, aggregate recomputation, account-deletion cleanup, and coherent review seed fixtures.
- Verified-user booking validation, price snapshots and rounding, traveler rosters, idempotent Checkout creation, concurrent capacity protection, free bookings, signed replay-safe webhooks, hold expiration, owned reads, refund cancellation, reconciliation, and exactly-once seat restoration.
- R2 purge dry-run immutability, confirmation and force modes, object verification, media-row cleanup, failure handling, and production refusal.

Use the following commands during verification:

```shell
composer test
vendor/bin/pint --dirty --format agent
php artisan scramble:analyze --api=v1
php artisan scramble:export --api=v1
```

## 15. Deferred scope

The following capabilities are intentionally not part of the current public contract and must be specified before implementation:

- Restoring soft-deleted tours or start dates.
- Admin user-management endpoints, resource authorization policies, granular token abilities, and protection of tour endpoints.
- Email verification, refresh tokens, token listing, and user-initiated revoke-all workflows.
- Client-controlled image reordering.
- Direct or presigned uploads and queued image conversions.
- Guest checkout, partial booking changes or refunds, taxes, promotion codes, multiple currencies, delayed payment methods, Stripe Connect, customer synchronization, and administrative booking operations.
