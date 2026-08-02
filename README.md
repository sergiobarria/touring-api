# Touring REST API

Laravel 13 REST API for publishing and discovering guided tours.

The living product and API contract is in [docs/01_SPEC.md](docs/01_SPEC.md). Interactive versioned API documentation is available at `/docs/v1` while the application is running, with its OpenAPI document at `/docs/v1.json`.

Tour catalog reads and departure reads are public and rate limited. Tour creation, updates, deletion, guide teams, images, departures, and analytics are protected by admin-only permissions. A local `php artisan migrate:fresh --seed` creates development lead guides, supporting guides, and assigned tour teams; local fixture seeding also writes tour media to the configured disk.

## Tour media

Tour originals and synchronous WebP conversions are managed by Spatie Media Library on Cloudflare R2. Copy `.env.example` to `.env` and configure `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, and the account's S3-compatible `R2_ENDPOINT`. `R2_URL` is the public URL used in API payloads; development currently uses the configured `r2.dev` hostname.

Never commit R2 credentials.

To inspect or clear the development media bucket:

```shell
# Dry run
php artisan r2:purge-media

# Interactive execution
php artisan r2:purge-media --execute

# Non-interactive local/testing execution
php artisan r2:purge-media --execute --force
```

The purge command clears the entire configured bucket and matching media rows. It refuses to run outside the `local` and `testing` environments.
