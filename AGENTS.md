# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel 13 REST API. Application code belongs in `app/`; controllers live in `app/Http/Controllers`, application actions in `app/Actions/<Domain>`, reusable domain services in `app/Services/<Domain>`, DTOs in `app/DataTransferObjects`, models in `app/Models`, and providers in `app/Providers`. Define API endpoints in `routes/api.php` and browser routes in `routes/web.php`. Migrations, factories, and seeders are under `database/`. Vite compiles `resources/js/app.js` and `resources/css/app.css` to `public/`. Keep feature tests in `tests/Feature`, unit tests in `tests/Unit`, fixture/import data in `data/`, and requirements in `docs/`.

## Build, Test, and Development Commands

- `composer run setup` installs PHP/Node dependencies, creates `.env`, generates the app key, migrates, runs the canonical permission and role seeders, and builds assets.
- `composer run dev` starts the Laravel server, queue listener, Pail logs, and Vite together.
- `composer test` clears cached configuration and runs the full test suite.
- `php artisan test --compact --filter=testName` runs a focused Pest test.
- `npm run dev` starts Vite only; `npm run build` creates production assets.
- `vendor/bin/pint --dirty --format agent` formats changed PHP files.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, four-space indentation (two spaces for YAML), and a final newline. Follow PSR-4 namespaces (`App\` maps to `app/`) and Laravel conventions: `TourController`, singular models such as `Tour`, plural database tables, and timestamped snake-case migrations. Use explicit parameter, return, property, and class-constant types; for example, write `private const string TOKEN_NAME = 'auth-token';` instead of an untyped constant. Declare classes `readonly` when their instance state is immutable, and avoid redundant `readonly` modifiers on properties of a readonly class. Use descriptive method names, constructor property promotion, and braces for every control structure. Prefer Artisan generators, for example `php artisan make:controller TourController --no-interaction`.

## Application Architecture

Keep controllers thin: accept validated requests, delegate application behavior, and serialize the result. Put each use case in a dedicated action with a typed `handle(...)` method under `app/Actions/<Domain>`. Extract a service under `app/Services/<Domain>` only when a capability is shared by actions or represents a meaningful integration boundary; do not create a service merely to relocate one action's implementation. Prefer concrete constructor or method injection through Laravel's container. Add interfaces only when multiple implementations or a real external boundary justify them. Use readonly DTOs when inputs or results benefit from a stable typed boundary.

Prefer explicit orchestration over application events and listeners. Actions and services must call required collaborators directly; do not use events or listeners for required business steps, state transitions, authorization, or response-critical work. Dispatch queued jobs or notifications explicitly for asynchronous work, and dispatch them only after any surrounding database transaction has committed. Reserve events for genuinely optional fan-out or observability and for narrowly documented framework lifecycle compatibility. Correctness must never depend on an event listener, and every framework-event exception must remain safe when no application listener is registered.

Use Spatie Permission for authorization. Users have exactly one primary role; replace roles through `UserRoleService` rather than attaching additional roles. Authorize application behavior with permission checks or policies instead of role-name checks. Keep canonical permissions in `PermissionSeeder`, canonical roles and their permission mappings in `RoleSeeder`, and run the permission seeder first. Self-service behavior shared by roles should use ownership policies rather than a `user`-only permission.

## Testing Guidelines

Tests use Pest 5 with PHPUnit. Name files by behavior or subject, ending in `Test.php` (for example, `TourListingTest.php`). Prefer feature tests for API behavior and unit tests for isolated logic. Use model factories for setup and enable `RefreshDatabase` when persistence isolation is needed. The test environment uses in-memory SQLite. No numeric coverage threshold is configured; new behavior and regressions should nevertheless include focused tests.

## Commit & Pull Request Guidelines

Recent history mixes concise imperatives with Conventional Commit prefixes. Prefer a present-tense summary such as `feat: add tour availability endpoint` or `fix: reject expired tokens`; avoid `WIP` commits in review-ready branches. Pull requests should explain the motivation, link issues, list migration or configuration impacts, and include test results. Add request/response examples for API changes and screenshots only for visible UI changes.

## Security & Configuration

Copy `.env.example` locally and never commit secrets or environment-specific credentials. Review migrations before running them against shared databases. Keep authentication-protected routes behind Sanctum middleware and validate all external input. Define API and endpoint-specific throttles as named limiters in the dedicated rate-limit provider instead of embedding numeric limits in routes. Use `RATE_LIMITER_STORE=redis` in multi-instance production environments when Redis is available, and keep `APP_URL` aligned with the exact externally accepted API host. Apply the reusable `no-store` middleware to endpoints that return tokens or other sensitive response data, and update Telescope redaction whenever new credential fields or secrets are introduced. Tour write routes remain public only as an explicit interim state and must be authorization-protected before production use.

Use Laravel's password broker for reset tokens and route reset links through the configured `FRONTEND_URL`. Queue reset notifications with encrypted job payloads. Password resets revoke every API token; authenticated password changes preserve only the current token. Keep password mutation and token revocation atomic.
