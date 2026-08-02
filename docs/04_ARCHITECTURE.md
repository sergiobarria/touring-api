# Application Architecture

## Shape of the application

Touring is a Laravel 13 JSON API. Routes are dispatched from `routes/api.php` to the v1 definitions in `routes/api_v1.php`. Unversioned browser routes expose `/health`, Laravel's `/up` liveness route, the welcome page, and version-specific Scramble documentation.

Application responsibilities are separated as follows:

- **Form requests** validate input and reject unsupported fields.
- **Controllers** accept validated input, invoke one application use case, and serialize its result.
- **Actions** in `app/Actions/<Domain>` implement individual use cases through typed `handle(...)` methods.
- **Services** in `app/Services/<Domain>` contain capabilities shared by actions or meaningful integration boundaries.
- **Readonly DTOs** carry stable validated inputs and integration results.
- **Models and policies** own persistence relationships and ownership decisions.
- **Resources** produce the public JSON representations.

Interfaces are introduced only for multiple implementations or real external boundaries. Controllers do not accumulate business orchestration, and services are not created merely to rename an action.

## Explicit orchestration

Actions and services call every required collaborator directly. Application events and listeners are not used for authorization, critical state transitions, response correctness, or hidden sequencing. Framework lifecycle events are tolerated only when application correctness remains independent of a listener.

Queued jobs and notifications are dispatched explicitly after surrounding database transactions commit. This prevents consumers from observing state that may still roll back.

## Transaction boundaries

Transactions protect workflows whose records must agree:

- Registration/account creation and primary-role assignment.
- Email/password mutations and token or reset-token revocation.
- Tour guide-team replacement and audit records.
- Departure capacity changes.
- Booking creation, seat reservation, and idempotency records.
- Webhook fulfillment, expiration, cancellation/refund transitions, and seat restoration.
- Review mutation and tour rating recomputation.
- User deletion and affected review aggregate cleanup.

Pessimistic locking serializes capacity and aggregate changes where concurrent requests could violate invariants. External payment calls are coordinated so retries remain safe and database state exposes recoverable transitions.

## Authentication and authorization

Laravel Sanctum stores hashed personal access tokens. Spatie Permission provides canonical roles and permissions, while policies enforce ownership. `UserRoleService` replaces a user's sole role instead of attaching additional roles.

Rate limiting is registered centrally as named limiters. Sensitive responses pass through `PreventResponseCaching`, and Telescope redacts credentials, authorization headers, reset tokens, and plaintext tokens.

## Storage and media

Spatie Media Library owns tour images. The configured `MEDIA_DISK` is normally the Cloudflare R2 disk backed by Flysystem's S3 adapter. Original uploads and synchronous Imagick WebP conversions share the media lifecycle. Database deletion follows successful storage cleanup so failures remain visible and recoverable.

## Stripe integration

`StripeGateway` defines the payment boundary and `StripePaymentGateway` implements it with Stripe's SDK. Booking application behavior depends on the boundary rather than SDK calls in controllers.

Checkout sessions reserve seats before redirect. Signed webhook processing is the source of truth for paid fulfillment. Cancellation uses explicit pending and failed states because a refund is an external operation that may require later reconciliation. Idempotency keys, unique external identifiers, row locks, and state checks make client and webhook retries safe.

## Email, queues, and scheduling

Verification, password-reset, booking, and cancellation notifications use Laravel notifications. Reset and verification payloads are encrypted when queued. Production requires a persistent queue worker.

The scheduler runs:

- Booking hold expiration every minute.
- Health checks every minute.
- Failed booking-refund reconciliation every ten minutes.
- Expired password-reset cleanup every fifteen minutes.
- Telescope pruning, expired Sanctum-token pruning, and health-history pruning daily.

Local `composer run dev` runs `schedule:work`; production infrastructure invokes `schedule:run` every minute.

## Auditing and observability

OwenIt Auditing records selected user, tour, departure, guide-team, and role changes while excluding secrets. Laravel Telescope supports development inspection but is not package-auto-discovered by default. Spatie Health persists scheduled readiness results and the public controller returns only a boolean outcome.

Logs, failed queue jobs, Stripe events, scheduler execution, and `/health` should be monitored in production. Domain recovery commands are described in [Operations](06_OPERATIONS.md).

## Major flows

### Booking

```text
request -> validation -> booking action -> lock departure -> reserve seats
        -> free: confirm
        -> paid: create Stripe Checkout -> await signed webhook -> confirm
        -> timeout: scheduled expiration -> restore seats
```

### Cancellation

```text
owned confirmed booking -> validate cutoff -> mark/request cancellation
        -> free: cancel + restore seats
        -> paid: refund succeeds -> cancel + restore seats
        -> refund fails -> cancellation_failed -> scheduled reconciliation
```

### Review

```text
authenticated author -> verify past confirmed purchase -> lock tour
        -> create/update/delete review -> recompute rating aggregate -> commit
```

## Architectural constraints

- Preserve UTC normalization and ULID identity across application-owned boundaries.
- Do not bypass actions from HTTP controllers for multi-step behavior.
- Do not dispatch asynchronous work before commit.
- Do not make correctness depend on optional listeners.
- Do not authorize by hard-coded role name when a permission or policy represents the capability.
- Keep Stripe, storage, and other external failures explicit and retryable where the domain allows.
