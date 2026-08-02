# Touring API Specification

## Purpose

This document is the entry point to Touring's living product and engineering contract. Touring is a versioned REST API for publishing and discovering guided tours, managing departures and guide teams, taking bookings, collecting qualified reviews, and supporting administrative planning.

The generated OpenAPI document owns exhaustive HTTP field schemas. The Markdown documentation owns business intent, cross-cutting behavior, domain invariants, workflows, and operating requirements. Implemented behavior and documentation must change together.

## Current capabilities

- Sanctum registration, login, logout, email verification, password recovery, and self-service account updates.
- Exactly one primary role per user, with capability-based administrative authorization.
- Public tour discovery with filtering, sorting, pagination, sparse fieldsets, and start-date inclusion.
- Administrative tour, guide-team, image-gallery, and departure management.
- Verified-user bookings, Stripe Checkout fulfillment, expiring seat holds, cancellation, and refund reconciliation.
- Purchase-qualified reviews with transactional rating aggregate maintenance.
- Administrative top-tour, statistics, and monthly-plan analytics.
- Auditing, named rate limiters, readiness checks, queue-backed email, scheduled cleanup, and R2 media storage.

## System-wide requirements

- The application requires PHP 8.3 or newer and Laravel 13. PostgreSQL is the primary application database; automated tests use SQLite.
- Application-owned records use ULIDs. Package infrastructure may retain package-compatible identifiers. Tour image operations expose Spatie Media Library's integer media ID.
- Version 1 is mounted under `/api/v1` with the `v1.` route-name prefix.
- UTC is canonical for stored and transmitted instants. API timestamps use ISO 8601 with an explicit offset.
- Public resources follow the project's JSON:API-style resource representation. Write requests use plain top-level JSON objects except multipart image uploads.
- Controllers remain thin, actions represent application use cases, and shared domain/integration behavior belongs in focused services.
- Required workflows use direct orchestration. Correctness, authorization, and state transitions may not depend on optional event listeners.
- Cross-record state changes that must remain consistent are atomic. Jobs and notifications required after a transaction are dispatched after commit.
- Tours and departures are soft-deleted. Reviews and individual media items are hard-deleted through their explicit workflows.
- Authentication uses Sanctum Bearer tokens. Authorization checks permissions or ownership policies, never role-name conditionals in application behavior.
- External input is validated and unsupported write fields are rejected.
- Token-bearing and other sensitive responses use the reusable no-store middleware.

## Contract documents

| Document | Contract owned |
| --- | --- |
| [Business](00_BUSINESS.md) | Fictional company context and why the product behaves as it does |
| [Domain](02_DOMAIN.md) | Entities, relationships, lifecycle rules, and invariants |
| [API](03_API.md) | Public HTTP conventions, endpoint groups, errors, and workflows |
| [Architecture](04_ARCHITECTURE.md) | Application structure, transaction boundaries, and integrations |
| [Development](05_DEVELOPMENT.md) | Local setup, fixtures, testing, and contribution workflow |
| [Operations](06_OPERATIONS.md) | Deployment configuration, scheduled work, health, and maintenance |

The interactive v1 reference is served at `/docs/v1`; its OpenAPI document is served at `/docs/v1.json`.

## Documentation policy

Every feature change must update the documents whose contracts it changes in the same implementation pass:

1. Update [Business](00_BUSINESS.md) only when product positioning or business policy changes.
2. Update [Domain](02_DOMAIN.md) for entity, relationship, lifecycle, or invariant changes.
3. Update [API](03_API.md) and generated OpenAPI annotations for HTTP behavior changes.
4. Update [Architecture](04_ARCHITECTURE.md) for new boundaries, integrations, or orchestration patterns.
5. Update [Development](05_DEVELOPMENT.md) or [Operations](06_OPERATIONS.md) for workflow and infrastructure changes.
6. Keep the README concise and update it when onboarding or the top-level feature set changes.

Do not duplicate full request/response schemas in Markdown. Prefer a behavioral example and link to generated OpenAPI. When code, tests, and documentation disagree, resolve the discrepancy rather than documenting both behaviors.

## Deferred scope

The following capabilities are not part of the current public contract:

- Restoring soft-deleted tours or departures.
- Refresh tokens, token listing, and user-initiated revoke-all workflows.
- Client-controlled image reordering, direct/presigned uploads, and queued conversions.
- Guest checkout, booking amendments, partial cancellation or refunds, taxes, promotion codes, multiple currencies, delayed payment methods, Stripe Connect, and Stripe customer synchronization.
- Administrative booking operations beyond the existing recovery commands.
- Invitation-specific administrator-created account onboarding.
- A separate profile domain for biographies, locale, avatars, or public guide profiles.

Each deferred capability requires an explicit domain and API specification before implementation.
