# Domain Contract

This document defines Touring's entities, relationships, lifecycle rules, and business invariants. HTTP representations belong in [API behavior](03_API.md).

## Users, roles, and tokens

A user has a ULID, name, unique normalized lowercase email address, password hash, optional email-verification timestamp, and timestamps. A user has exactly one primary role: `user`, `guide`, `lead-guide`, or `admin`.

Authorization is capability-based. Canonical user permissions are `users.view-any`, `users.view`, `users.create`, `users.update-role`, and `users.delete`. Tour permissions are `tours.create`, `tours.update`, `tours.delete`, `tours.manage-images`, `tours.manage-start-dates`, and `tours.view-analytics`. Only the canonical `admin` role receives them. Self-service account behavior is ownership-based.

Each registration or login creates a Sanctum token named `auth-token` with wildcard abilities and a 30-day expiration. Multiple tokens may coexist. Logout revokes only the current token; password reset revokes all tokens; an authenticated password change preserves only the current token.

Administrators may create accounts, replace roles, and delete other users. They cannot change their own role or delete themselves through the management API. Assigned guides must be removed or replaced before an incompatible role change or deletion. Users with booking history cannot be deleted.

## Tours and guide teams

A tour is a public catalog item with:

- A ULID, display name, unique generated slug, summary, and optional description.
- Duration in days, maximum group size, and `easy`, `moderate`, or `difficult` difficulty.
- Decimal base price and an optional percentage discount.
- Authoritative nullable average rating and non-negative rating count.
- Active visibility, timestamps, and a soft-delete timestamp.
- Exactly one lead guide and zero through four supporting guides.

Tour names need not be unique. Slugs are generated from names and receive numeric suffixes when necessary; soft-deleted tours continue reserving their slugs.

The lead guide must have the sole `lead-guide` role. Supporting guides must have the sole `guide` role, be unique within the team, and cannot include the lead. Public tour representations expose guide IDs and names, never guide emails.

`duration_weeks` is computed as days divided by seven and rounded to one decimal. `upcoming_dates` contains ordered active, non-deleted departure instants strictly after the current UTC time; sold-out departures remain because this value describes schedule rather than availability.

Soft deletion preserves the tour's departures, reviews, guide team, and media. Restoration is not implemented.

## Departures and capacity

A tour start date represents one scheduled departure. It has a ULID, tour reference, UTC start instant, available spots, reserved spots, active flag, timestamps, and soft-delete timestamp.

The tour and UTC instant pair is unique, including when equivalent offsets normalize to the same instant. Soft-deleted rows continue reserving that pair. A departure's available plus reserved spots may never exceed the tour's maximum group size. Tour capacity cannot be reduced below the inventory already represented by its departures.

`available_spots` is client-manageable within those constraints. `reserved_spots` is internal and changes only through booking workflows. Historical departures remain part of operational records even though public tour schedule summaries include only future active ones.

## Images

Tour images are Spatie Media Library records in the `tour-images` collection on the configured media disk. A tour may have zero through ten images. Collection order is display order, the first item is the cover, and clients cannot reorder images directly.

Accepted originals are JPEG, PNG, and WebP files up to 10 MB. Upload synchronously creates WebP `card` (1200×800 centered crop) and `thumbnail` (480×320 centered crop) conversions. Deleting an image removes its original and conversions, then closes the ordering gap. A storage failure must not silently remove only the database row.

## Bookings and travelers

A booking belongs to one verified user, one tour, and one departure. It snapshots the tour name, departure instant, purchaser identity, discount, USD unit price, total, and traveler roster so later catalog edits do not rewrite purchase history. Money is stored as integer cents; discounted unit prices round half-up to the nearest cent.

Each traveler has a full name, RFC-valid email address, and E.164 phone number. Traveler count is ticket quantity. Booking status is one of:

- `pending_payment`
- `confirmed`
- `cancellation_pending`
- `cancelled`
- `expired`
- `cancellation_failed`

A user-scoped hash of the required client idempotency key prevents duplicate holds. Booking and relevant Stripe identifiers are unique.

Creating a booking locks the active future departure and atomically transfers the requested quantity from available to reserved spots. Paid holds last 30 minutes by default and open card-only USD Stripe Checkout. Free or fully discounted bookings confirm without Stripe. A paid booking confirms only from a valid, matching `checkout.session.completed` webhook.

Expired holds restore seats exactly once through Stripe expiration handling or the scheduled recovery command. Confirmed bookings can be cancelled in full until the configured cutoff, 48 hours before departure by default. Free cancellations complete immediately. Paid cancellations restore seats only after Stripe confirms the full refund. A failed refund preserves capacity and records a retryable failure state based on the original timely request.

## Reviews and ratings

A review belongs to one user and one tour and contains an integer rating from 1 through 5 plus non-blank text of at most 2,000 characters. A user may have at most one review per tour.

Review creation requires a confirmed booking for that tour whose snapshotted departure instant is in the past. The qualification is rechecked inside the review transaction. Authors alone may update or delete their reviews.

Reviews are hard-deleted, allowing a later replacement. After every review mutation, the tour is locked and its count and average are recomputed in the same transaction. An unreviewed tour has `rating_avg = null` and `rating_count = 0`; otherwise the average is rounded to two decimal places. Deleting a user removes their reviews and recomputes affected tour aggregates atomically.

## Email and account lifecycle

Registration creates an unverified `user` account and queues an encrypted verification notification after commit. Verification links expire after `EMAIL_VERIFICATION_EXPIRE` minutes and bind the signed URL to the user and current email fingerprint. Verification is idempotent.

Changing an email requires the current password, normalizes and checks the new address, clears verification, invalidates reset tokens for the old and new addresses, and queues a new verification message after commit. Submitting the same normalized email preserves verification.

Password reset uses Laravel's broker and returns a generic response regardless of account existence. A successful reset changes the password, rotates the remember token, and revokes every API token atomically.

## Auditing and retention

User, tour, and departure changes are audited. User audit data excludes password hashes and remember tokens; password changes expose only a marker. Role and supporting-guide pivot changes record explicit old and new assignments.

Soft deletion preserves historical catalog relationships. Permanent user deletion is constrained by guide assignments and booking history, and cleans up authentication and permission state through the managed workflow.

## Required verification

Tests must cover role uniqueness and permissions; tour and guide constraints; UTC departure uniqueness and capacity; image ordering and cleanup; booking idempotency, concurrency, webhooks, expiration and refunds; review qualification and aggregate recomputation; account security; soft-deletion visibility; and audit secrecy.
