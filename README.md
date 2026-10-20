# SheSaves

Upload your grocery receipts and SheSaves builds a price history for every product you buy, then
tells you where each one (or your whole shopping list) is cheapest. Made for Australian supermarkets.

- **Receipts:** upload photos or PDFs; items, prices, discounts, store and date are read
  automatically (PDF text, or AWS Textract for photos) and queued for a quick review.
- **Products:** receipt lines are matched to products by brand, size and variant, so "PAULS FC
  MILK 2 LT" and "PAULS FULL CREAM MILK 2L" are the same product but 1.25L and 2L never are.
  Every confirmation is remembered.
- **Prices:** latest price at each store, cheapest store, savings, lowest / highest / average and
  a price history chart.
- **Shopping lists:** totals per store, which stores are missing prices, and a split-shop plan.
- **Dashboard:** potential savings, price changes and the biggest savings opportunities.

Laravel 13 API (`api/`) and Vue 3 + Tailwind CSS SPA (`frontend/`), run with `docker-compose.yml`.
The design, data model and matching rules are in [docs/architecture.md](docs/architecture.md).

Built on the `htb-backend-base` starter (sign-in, 2FA, users and roles). The two repositories don't
share git history, so starter fixes are copied across by hand.

## Email templates

Admins can see every email the app sends under **Email Templates**, preview each one with sample data
and send a test copy (subject prefixed with `[TEST]`). Access needs the `manage email templates` ability,
which super admins get; on an existing database run `php artisan abilities:refresh` to add it.

When the app adds an email, register it in `api/app/Providers/EmailTemplateServiceProvider.php`:

```php
$templates->register(new EmailTemplate(
    key: 'order_shipped',
    name: 'Order Shipped',
    type: EmailTemplateType::Transactional,   // or Marketing
    triggeredBy: 'order.shipped',
    description: 'Tells the customer their order is on its way.',
    sample: fn (User $recipient) => new OrderShippedNotification(Order::factory()->make()),
));
```

`sample` returns the real notification built with sample data, so previews match what users get. Test
copies go to real inboxes, so never put real tokens or signed links in a sample.
`tests/Feature/EmailTemplateTest.php` renders and test-sends every registered template.

## Getting started

Requirements: Docker with the Compose plugin.

```sh
cp api/.env.example api/.env
docker compose build
docker compose run --rm fpm composer install
docker compose run --rm fpm php artisan key:generate
docker compose up -d
docker compose exec fpm php artisan migrate --seed
```

`migrate --seed` also adds the Australian supermarkets and a category tree (`CatalogSeeder`). Receipts
are read by the `queue` service, so keep `QUEUE_CONNECTION=database` in `api/.env`. PDF receipts work out
of the box; to read photos, set up AWS Textract (see "Turning on photo OCR" in
[docs/architecture.md](docs/architecture.md)).

| Service             | URL                   |
| ------------------- | --------------------- |
| SPA                 | http://localhost:8080 |
| API                 | http://localhost:8000 |
| Emails (MailDev)    | http://localhost:8025 |
| MySQL (from host)   | `localhost:33061` (see `DB_*` in `api/.env`) |

Seeded logins (password `Secret*12345` for both):

- `admin@example.com`: super admin
- `user@example.com`: standard user

Notes:

- Sign-up, email verification and password reset emails are caught by MailDev.
- With `APP_ENV=local`, 2FA SMS codes are captured instead of sent and show up in
  `api/storage/logs/laravel.log`. Set `TWO_FACTOR_SKIP=true` in `api/.env` to accept any code locally.
- The SPA's `node_modules` live in a Docker volume. After changing `frontend/package.json`, rebuild
  the image and recreate that volume: `docker compose build vue`, then
  `docker compose rm -sf vue && docker volume rm <project>_node_modules && docker compose up -d vue`
  (`docker volume ls` shows the exact name).
- Social login (Google / Facebook) is optional: fill in the `GOOGLE_*` / `FACEBOOK_*` keys in
  `api/.env`. Callback URLs point at the API, e.g. `http://localhost:8000/auth/google/callback`.

## Checks

```sh
# API tests (in-memory SQLite, no .env or database needed)
cd api && php vendor/bin/phpunit

# Frontend type check and production build
cd frontend && npm ci && npm run typecheck && npm run build
```

Inside Docker: `docker compose exec fpm php vendor/bin/phpunit` and
`docker compose exec vue npm run typecheck`.

## Security

The original repository committed `ec2-pem.pem` (an RSA private key). That EC2 key pair must be
rotated and the file purged from the original repository's git history (git filter-repo / BFG).
It is not included here, and `*.pem`, `*.key` and `.env` files are gitignored.
