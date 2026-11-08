# Mini Cloud billing desk

Billing back office of a small cloud provider: customers with wallets, servers, IP addresses, hourly billing,
an admin panel, and a fake infrastructure service that is slow, flaky and sometimes lies.

Laravel 12 · Livewire 4 · Flux UI · MySQL · Sail · `nwidart/laravel-modules`

What matters here is how it behaves when things go wrong. Read [docs/notes.md](docs/notes.md) for the rules, the decisions,
the challenges with the tests that prove them, and what is not safe yet. Diagrams are in [docs/diagrams](docs/diagrams) (Mermaid),
the slides are [docs/presentation.pdf](docs/presentation.pdf).

## Start it

```sh
./start.sh
```

Needs only Docker (with compose). It builds the Sail image, starts everything, migrates, seeds and builds the assets.

| | |
|---|---|
| Admin panel | http://localhost:8085 |
| phpMyAdmin | http://localhost:8086 (database `minicloud`, user `sail`) |
| Fake infrastructure | http://localhost:8087/infra/servers |

Staff accounts (password `password`): `owner@minicloud.test`, `support@minicloud.test`, `viewer@minicloud.test`.

Containers: `laravel.test` (web), `queue` (queue worker), `scheduler` (runs `billing:run` and `servers:sync` every minute,
`servers:reconcile` every five), `infra` (the fake infrastructure, its own sqlite database), `mysql`, `phpmyadmin`.

Local ports can be changed in `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `PMA_PORT`, `INFRA_PORT`).

## Run the tests

```sh
docker compose exec -u sail laravel.test php artisan test
```

They use the `testing` database that Sail creates in MySQL. Some of them start real `php artisan` processes
(parallel billing runs, a run that is killed halfway, two IP moves at once), so they take about half a minute.

The test for stale search results runs in a real browser against the running stack (needs Chrome on the machine running it):

```sh
docker compose exec -u sail laravel.test php artisan db:seed --class=DemoSeeder   # servers named alpha-*, beta-*, ..
npm install && npm run test:browser          # CHROME_PATH and BASE_URL can be set
```

## Demo it

1. Sign in as `owner`. Open **Servers**, search, filter, sort, reload: the state is in the URL.
2. **New server** for a customer. It is `pending`, then `running` after a few seconds, although the infra fails
   20 % of its calls and lies in another 20 % (`FAKE_INFRA_FAIL_RATE`, `FAKE_INFRA_LIE_RATE`, `FAKE_INFRA_CREATE_DELAY` in `.env`,
   then `docker compose up -d infra`). Open the server page: its address, billing history, **Add an address**, **Move**, **Terminate**.
3. Move the clock: **+1h** / **+1d** at the bottom of the sidebar (owner only), or `docker compose exec -u sail laravel.test php artisan clock:advance "3 hours"`.
   The scheduler bills within a minute; or run it yourself: `php artisan billing:run`. Run it as often as you like, the numbers do not change.
4. Open the customer: balance, money history, servers with their statuses (verified and suspended can both be set).
   The owner can **Top up** and **Refund**; sign in as `support` or `viewer` and the buttons are gone, and the server refuses the calls anyway.
5. Try `/servers?colour=red` or `/servers?status=banana`: refused with 422, not ignored.
6. Make the infra drift and let the check repair it: `php artisan servers:reconcile`.
7. A new price from the next hour on: `php artisan billing:price server 20`.

## Layout

```
Modules/
  Clock/      the app clock (the only "now" for billing), advance/set commands, sidebar control
  Customers/  customers, their statuses, pages
  Wallet/     append-only ledger, top-up/refund with idempotency keys, customer money pages
  Network/    the IP pool, assignments, move, pages
  Servers/    servers, infra client, sync job, drift check, pages
  Billing/    prices, charges, the billing run, server billing history
  FakeInfra/  the fake infrastructure service (only serves routes when FAKE_INFRA_SERVE=true)
app/          staff users and roles (Gates), login, the strict-query middleware
docs/         notes, diagrams (Mermaid), slides
```

Roles are Gates (`app/Providers/AppServiceProvider.php`): `manage-money` and `manage-clock` are owner only,
`manage-infrastructure` is owner and support, viewers only read. Every Livewire action calls `Gate::authorize`, the buttons are only hidden in addition.

## Without Docker

Possible with PHP 8.3+, MySQL 8 and Composer: `composer install`, copy `.env.example` to `.env`, point `DB_HOST` at your MySQL
(and create a `testing` database for the tests), `php artisan key:generate`, `php artisan migrate --seed`, `npm install && npm run build`,
then run `php artisan serve`, `php artisan queue:work` and `php artisan schedule:work`. The fake infra needs
`FAKE_INFRA_SERVE=true php artisan migrate --database=infra --path=Modules/FakeInfra/database/infra` and a second server process, see `compose.yaml`.
