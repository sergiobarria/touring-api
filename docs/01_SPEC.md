# Touring API Specification

## 1. Purpose and status

Touring API is a versioned REST API for publishing and discovering guided tours. This document is the living product and API specification: it records the behavior a developer must reproduce, rather than serving only as implementation notes.

Every feature change must update this specification in the same implementation pass so that it remains the source of truth for the application's requirements and public contract.

The current implementation provides a public tour catalog and supports creating, partially updating, and soft-deleting tours. Restoration, booking, authentication, reviews, and media management are outside the current scope.

## 2. Technical conventions

- The application is built with Laravel 13 and PHP 8.3 or newer.
- PostgreSQL is the primary development and production database. Automated tests use in-memory SQLite for isolation and speed.
- Public identifiers are ULIDs. Database sequence IDs must not be exposed.
- API routes are versioned. Version 1 is mounted below `/api/v1` and uses the `v1.` route-name prefix.
- Responses use Laravel JSON:API resources.
- Query filtering, sorting, and relationship inclusion use Spatie Laravel Query Builder.
- UTC is the canonical timezone for storing, comparing, generating, testing, and transmitting timestamps.
- API timestamps use ISO 8601 with an explicit UTC offset. Clients convert them to a user's timezone for display.
- Features that depend on a destination's wall-clock time or daylight-saving rules must additionally store an IANA timezone instead of changing the canonical UTC instant.
- Tour and tour start-date model changes are auditable.
- Tour slugs are generated from tour names and must be unique.
- Validated tour write data crosses the HTTP boundary through a native readonly DTO before model persistence.
- All application deletion workflows use soft deletes. The application must not expose hard-delete operations.
- Authentication is intentionally deferred during development, so the current endpoints are public. Destructive endpoints must be protected before production use.

## 3. Domain model

### 3.1 Tour

A tour is the public catalog item users browse. It has the following persisted fields:

| Field | Type | Rules and meaning |
| --- | --- | --- |
| `id` | ULID | Primary key. |
| `name` | string | Display name. |
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

Rating values must be coherent: a tour without ratings has `rating_avg = null` and `rating_count = 0`; a rated tour has a non-null average and a positive count.

Tour names do not need to be unique. Slugs are generated and updated from names, with numeric suffixes added when necessary. Slugs belonging to soft-deleted tours remain reserved.

### 3.2 Tour start date

A tour can have many scheduled start dates. Each start date belongs to exactly one tour.

| Field | Type | Rules and meaning |
| --- | --- | --- |
| `id` | ULID | Primary key. |
| `tour_id` | ULID | Foreign key to `tours.id`. |
| `start_datetime_utc` | timestamp | Scheduled start in UTC. |
| `available_spots` | unsigned integer | Remaining capacity; defaults to `0`. |
| `is_active` | boolean | Whether this departure is active; defaults to `true`. |
| `created_at`, `updated_at` | timestamps | Internal lifecycle timestamps. |
| `deleted_at` | nullable timestamp | Marks a soft-deleted start date; `null` means it has not been deleted. |

Soft-deleting a tour does not delete or modify its start dates. This preserves the complete schedule for a future restoration. The database foreign key retains a cascading hard-delete constraint as an integrity fallback, but application workflows must not invoke it. The combination of `tour_id` and `start_datetime_utc` is indexed.

## 4. Public API conventions

### 4.1 JSON:API resource types

- Tours use type `tours`.
- Tour start dates use type `tour_start_dates`.
- Every resource contains its ULID as `id` in addition to its `type`.

Internal visibility flags and timestamps are not returned as tour attributes. A start date's `is_active` value is currently part of its public resource representation.

Tour write requests use a plain top-level JSON object. Tour responses retain the JSON:API resource representation.

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

The required fields are `name`, `duration_days`, `max_group_size`, `difficulty`, `price`, and `summary`. `description` and `price_discount_percent` default to `null`, while `is_active` defaults to `true`.

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
  "description": null,
  "is_active": false
}
```

#### Tour write validation

| Field | Validation |
| --- | --- |
| `name` | String, maximum 255 characters. |
| `duration_days` | Integer from 1 through 255. |
| `max_group_size` | Integer from 1 through 255. |
| `difficulty` | `easy`, `moderate`, or `difficult`. |
| `price` | Decimal from 0 through 99,999,999.99, with at most two decimal places. |
| `price_discount_percent` | Nullable decimal greater than 0 and at most 100, with at most two decimal places. |
| `summary` | String, maximum 500 characters. |
| `description` | Nullable string, maximum 65,535 characters. |
| `is_active` | Boolean. |

The server manages `id`, `slug`, ratings, timestamps, `duration_weeks`, `upcoming_dates`, and start dates. These and all unknown request fields are rejected with `422 Unprocessable Entity` rather than silently ignored. Tour creation and update do not create, replace, or remove start dates.

### 5.5 Delete a tour

```http
DELETE /api/v1/tours/{tour}
```

`{tour}` is the tour ULID. The endpoint can delete an active or inactive tour because `is_active` controls publication rather than deletion eligibility.

A successful request soft-deletes the tour by setting `deleted_at` and returns `204 No Content` with an empty body. Related start dates remain unchanged. The deleted tour is excluded from ordinary model queries and from the public list and detail endpoints.

Unknown, malformed, or already-deleted identifiers return `404 Not Found`. Deleted slugs remain reserved by the global unique constraint. No restore or hard-delete endpoint is currently available.

## 6. Errors and validation

- Successful list and detail requests return `200 OK`.
- Successful tour creation returns `201 Created`, a detail resource, and a `Location` header.
- Successful partial updates return `200 OK` with the updated detail resource.
- A successful tour deletion returns `204 No Content` with an empty body.
- Invalid list or write input, unknown write fields, and empty PATCH requests return `422 Unprocessable Entity` with Laravel validation errors.
- Unsupported filters, sorts, or includes return `400 Bad Request`.
- A missing, inactive, unknown, malformed, or soft-deleted tour identifier on the detail endpoint returns `404 Not Found`.
- An unknown, malformed, or already-deleted tour identifier on the delete endpoint returns `404 Not Found`.
- An unknown, malformed, or soft-deleted tour identifier on the PATCH endpoint returns `404 Not Found`.
- PUT is not registered for tours and returns `405 Method Not Allowed`.

## 7. Routing and documentation

`routes/api.php` is the API version dispatcher. Version 1 routes are defined in `routes/api_v1.php` and mounted with the `v1` URL and route-name prefixes.

Only these tour routes are currently public:

| Method | URI | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/tours` | List active tours. |
| `POST` | `/api/v1/tours` | Create a tour. |
| `GET` | `/api/v1/tours/{tour}` | Retrieve one active tour by ULID. |
| `PATCH` | `/api/v1/tours/{tour}` | Partially update an active or inactive tour. |
| `DELETE` | `/api/v1/tours/{tour}` | Soft-delete an active or inactive tour by ULID. |

Scramble exposes version-specific OpenAPI documentation:

- Interactive documentation: `/docs/v1`
- OpenAPI document: `/docs/v1.json`
- Documented server base path: `/api/v1`
- Tour operations are grouped under the plural `Tours` tag.

The default Scramble `/docs/api` and `/docs/api.json` routes are disabled so documentation cannot mix API versions.

## 8. Development data

The development seeder creates 20 tours, each with between three and five start dates. It runs only in the `local` environment.

Tour factory data follows these rules:

- Names use a unique `Tour ???-###` pattern and slugs are derived from those names.
- Duration ranges from 1 to 30 days.
- Maximum group size ranges from 4 to 20.
- Difficulty is selected from the `TourDifficulty` enum.
- Base price ranges from 299.00 to 2999.00.
- A discount is generated about 30% of the time and uses a realistic increment: 5%, 10%, 15%, 20%, or 25%.
- A tour has ratings about 80% of the time. Rated tours receive an average from 3.50 to 5.00 and a positive rating count; unrated tours receive a null average and zero count.

Generated start dates occur from 1 to 180 days in the future, use UTC, add a randomized daytime hour and either zero or 30 minutes, and are active about 90% of the time.

## 9. Acceptance and verification

Feature coverage must verify:

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

Use the following commands during verification:

```shell
composer test
vendor/bin/pint --dirty --format agent
php artisan scramble:analyze --api=v1
```

## 10. Deferred scope

The following capabilities are intentionally not part of the current public contract and must be specified before implementation:

- Creating and updating tour start dates.
- Restoring soft-deleted tours or start dates.
- Deleting individual tour start dates through the API.
- Authentication and authorization.
- Tour images or media.
- Reviews and rating submission.
- Booking and inventory workflows.
- Discounted-price calculation or promotion rules.
