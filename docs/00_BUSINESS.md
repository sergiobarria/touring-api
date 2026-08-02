# Touring Business

> Touring and the history below are fictional. This document provides consistent product context for the application; it does not claim real customers, revenue, or operations.

## Origin

Touring began as a small product idea after its founders repeatedly encountered the same problem while planning trips: remarkable local guides were difficult to discover, while travelers had little confidence that a tour's schedule, capacity, quality, and guide identity were current.

The fictional company was formed to make guided travel easier to evaluate and book. Its first product is a curated marketplace where a traveler can discover an experience, choose a scheduled departure, pay for every traveler in a party, and leave a review after the trip. Touring's operating team uses the same platform to assemble guide teams, maintain media and schedules, and understand demand.

## Customers and partners

Touring serves three groups:

- **Travelers** want trustworthy descriptions, clear prices, real availability, secure payment, and reviews from people who actually traveled.
- **Guides** want qualified demand and an accurate representation of the experiences they lead. A lead guide owns delivery accountability; supporting guides provide additional capacity and expertise.
- **Touring operators** curate the catalog, schedule departures, manage guide assignments, monitor bookings, and use analytics for planning.

The current API models Touring as the merchant and catalog operator. It is not yet a self-service marketplace where independent suppliers publish or settle their own inventory.

## Value proposition

Touring reduces uncertainty on both sides of a guided experience:

- Curated descriptions and ordered image galleries help travelers understand the experience before committing.
- Difficulty, duration, group size, price, discount, guides, and departure dates make tours comparable.
- Capacity is held atomically during checkout so overselling is avoided.
- Tour and price details are snapshotted into a booking, preserving what the customer purchased.
- Reviews require a confirmed booking for a past departure, improving trust in ratings.
- Operators receive a consistent view of schedules, ratings, demand, and guide assignments.

## Business model

The intended model is a commission or margin retained from each completed booking. The current software records the traveler-facing USD total and processes payment through Touring's Stripe account, but it does not calculate commissions, guide payouts, taxes, or accounting settlement.

Touring may also use discounts as a merchandising tool. Discounts are part of the tour offer and are snapshotted at purchase time so later catalog changes cannot alter an existing booking.

## Operating model

1. An administrator creates a tour, assigns its lead guide and optional supporting guides, uploads its gallery, and publishes active departure dates.
2. Travelers browse active tours and inspect upcoming schedules, prices, capacity, ratings, and guide teams.
3. A verified traveler submits a roster. Touring temporarily reserves the required seats and opens Stripe Checkout, or confirms immediately when the total is free.
4. A signed Stripe webhook confirms paid bookings. Expired checkout holds return seats to inventory.
5. A timely cancellation requests a full refund. Seats return only after a successful refund or immediately for a free booking.
6. After a confirmed departure is in the past, the purchaser may leave one review for the tour.
7. Operators use rankings, grouped statistics, and monthly departure plans to support merchandising and staffing decisions.

## Why the product has these controls

- **Departures and capacity:** a tour description is reusable, but every scheduled departure has its own inventory. Reserved spots distinguish temporary or confirmed commitments from remaining availability.
- **Guide teams:** one lead guide establishes accountability; up to four supporting guides reflect the staffing needs of larger or more demanding tours.
- **Verified accounts:** booking and review privileges are attached to a durable customer identity, while email verification reduces delivery and recovery risk.
- **Payment webhooks:** the browser returning from checkout is not proof of payment. Stripe's signed server-to-server event is the fulfillment authority.
- **Idempotency:** network retries must not create duplicate holds or charges.
- **Qualified reviews:** restricting reviews to completed purchases makes the rating a signal of delivered experience rather than browsing sentiment.
- **Ordered media:** the first image is the cover, allowing operators to control the primary merchandising asset without a separate cover record.
- **Permissions and auditing:** catalog and user administration can affect revenue, safety, and access, so changes are capability-protected and traceable.
- **Analytics:** top tours support merchandising, grouped statistics expose portfolio shape, and monthly plans support scheduling and guide allocation.

## Current product stage

Touring is represented as an early operational product with a curated supply model. The implemented API covers the core customer lifecycle and internal catalog controls, but production operation still depends on a separate frontend, customer support processes, financial reconciliation outside the application, infrastructure monitoring, and careful credential management.

Important business risks include payment or webhook outages, stale inventory, storage failures, incorrect guide assignments, fraudulent account access, and cancellation disputes. The API mitigates several of these risks through transactions, signed webhooks, recovery commands, permissions, auditing, and health checks; it does not replace operational ownership.

## Plausible future direction

Future product discovery may consider supplier self-service, richer public guide profiles, multiple currencies, taxes, promotion codes, guest checkout, booking amendments, partial refunds, waitlists, client-controlled gallery ordering, payouts, and deeper demand reporting. These are opportunities, not committed features, and require explicit specification before implementation.
