# Notes

What was built, why, what was left out. The diagrams are in `docs/diagrams/` (Mermaid sources).

## How billing works (the rules the code follows)

- Money is an integer in cents. The balance is never stored, it is `SUM(ledger_entries.amount)`.
- Time is the app clock (`Clock` service, table `clock_state`). It moves only when somebody advances it
  (`clock:advance`, `clock:set`, or the +1h/+1d buttons for the owner). Nothing in billing calls `now()`.
- An hour is a clock hour in UTC. Any hour that has started counts as a full hour.
  - A server is billed from the moment it was seen running (`started_at`) until termination was asked (`ended_at`).
    Started 10:20, ended 10:40: one hour. Ended exactly 11:00: one hour. Ended 11:00:01: two hours.
  - An address is billed while it sits on a running server. If it moves in the middle of an hour, that hour belongs
    to whoever had it first, and the new holder pays from the next hour. So there is exactly one payer per address per hour.
- The billing run does not remember where it stopped. It works out every hour that is due up to the clock and
  tries to insert each one. `UNIQUE (billable_type, billable_id, hour_start)` lets each hour in once.
  That is why running it twice, at the same time, after a crash or after a pause of days all work.

## Main decisions and what was rejected

| Decision | Rejected alternative | Why |
|---|---|---|
| Exactly-once through a unique key on `charges` (and on `ledger_entries.idempotency_key`), charge and ledger entry in one transaction | A "billed until" cursor per server | A cursor is state that can be wrong after a crash or a parallel run. A unique key cannot. The cursor also makes a pause hard to catch up. |
| One IP, one open assignment through a unique index on a generated column (`open_ip_id`), plus a row lock in `move()` | Only locking in code. A trigger. | MySQL has no partial/exclusion index, but a unique index on a generated column that is NULL when closed gives the same thing. The lock only makes the loser fail early with a clear message; the index is the guarantee. |
| `move()` takes the server the caller believes the address is on | Last write wins | Two staff members looking at the same page must not both succeed. The second is told the address is no longer there. |
| The infra only knows our token (`infra_token`, a uuid we create first); every call is `PUT/GET/DELETE /servers/{token}` | Storing the id the infra returns | If the answer is lost or a lie, we still know the token. Asking again with the same token can never make a second server. |
| Status changes are compare-and-set (`UPDATE .. WHERE status = 'pending'`) | Locking the row during the HTTP call | Two sync passes at once cannot both win, and no lock is held while waiting for a slow service. |
| Balance = sum of an append-only ledger, with MySQL triggers refusing UPDATE/DELETE | A balance column | History can't be edited, even from a console. |
| Manual clock stored in the database | Real time plus an offset | Tests and demos are exact; every process (web, queue, scheduler) sees the same time. |
| Livewire 4 for the list, relying on its request handling (a new `wire:model.live` request supersedes the one in flight) | Our own sequence numbers | Less code. It is checked in a real browser (see below). |
| Class based Livewire components, one `Modules/<Name>` per area, `AllocatesIp` interface for "give this server an address" | One big app folder | Modules own their tables, services and pages. The pages of one module are placed in another module's page as components (`<livewire:wallet.customer-ledger>` on the customer page). |
| The fake infra is the same codebase, a separate container, its own sqlite database | A second Laravel app | One composer install, still a separate process and database that the app only reaches over HTTP. |

## Challenges: how they are solved and which test proves it

| Challenge | How | Test |
|---|---|---|
| Exactly-once billing | unique key + one transaction per charge, hours recomputed from the clock on every run | `Modules/Billing/tests/Feature/BillingRaceTest.php`: three `billing:run` processes at the same moment, and a run killed with SIGKILL halfway followed by another run. `BillingRulesTest`: same hour twice, a pause of 50 hours charges 50 hours once, a failing item leaves nothing half written. |
| One IP, one payer | `ip_assignments.open_ip_id` unique, `ip_assignments_period_order` check, row lock + expected holder in `move()`, `UNIQUE(ip, hour)` on charges | `Modules/Network/tests/Feature/IpRaceTest.php` (two real processes, and one slow transaction with a second move arriving while it is open), `IpRulesTest` (the database refuses a second open assignment), `BillingRulesTest::test_an_ip_that_moves_mid_hour_is_paid_by_one_customer_in_every_hour` and `::test_the_database_refuses_two_payers_for_the_same_ip_in_the_same_hour`. |
| Stale search results | Livewire supersedes older `wire:model.live` requests; state is in the URL | `tests/Browser/stale-search.mjs` (`npm run test:browser`, needs Chrome and the running stack): the answer for "alpha" is delayed 3 s and arrives after the answer for "beta"; the list must still show beta. |
| The infra lies (optional) | token based idempotent calls, sync job that re-reads the state, compare-and-set, `servers:reconcile` | `Modules/Servers/tests/Feature/ServerLifecycleTest.php` (create that reports an error but happened, create that really failed, delete that lies, give up after N passes), `DriftTest.php` (orphans, lost servers, zombies). |
| Downtime (optional) | free with the rule above | `BillingRulesTest::test_a_stopped_billing_job_catches_up_with_every_missed_hour_once` |
| One bad record (optional) | each item has its own try/catch and transaction, failures are listed in the report and retried on the next run | `BillingRulesTest::test_one_failing_item_does_not_stop_the_others_and_leaves_nothing_half_written` |
| Price change (optional) | a price row starts at the next full hour; charges keep the amount they were made with | `BillingRulesTest::test_a_new_price_starts_with_the_next_hour_and_never_touches_charged_hours` |

Tests seen failing without the fix (then restored):

- `ip_assignments.open_ip_id` unique index removed and the row lock removed from `move()`: `IpRaceTest` (both tests) and the "database refuses a second open assignment" test failed.
- Unique key on `charges` and on `ledger_entries.idempotency_key` removed: `BillingRaceTest::test_three_runs_at_the_same_moment...` and "database refuses two payers" failed.
- `wire:model.live` changed to `wire:model.live.async` (requests no longer supersede each other): the browser test failed with the alpha servers on screen.

## Not built

Running out of money (notice, suspension, termination) and restore of a terminated server. Customers can be marked
`suspended` by hand, and a suspended customer cannot create servers, that is all.

## What is not safe yet, and what I would do with more time

- The billing run reads every server and address on every run. Fine for a take-home, not for millions of rows:
  it needs a bounded candidate query (for example only items whose last charged hour is behind) while keeping the unique key as the guarantee.
- MySQL cannot say "no two closed periods of one address overlap". Only "one open period" is in the database; the closing and opening of a move
  happen in one transaction, and the billing side is protected separately by `UNIQUE(address, hour)`.
- The drift check handles unknown servers on the infra, servers we bill that the infra lost, and zombies. It does not look at `stopped`, and `all()` is not paginated.
- Giving up on a pending server counts passes, not elapsed time.
- `Servers` and `Network` know each other (the server list shows addresses). The `AllocatesIp` interface only covers allocation. A read model would break the cycle.
- The browser test needs Chrome and a running stack, so it is not part of `php artisan test`. Headless Chrome did not deliver real key presses to the page here, so it fires the `input` event the way typing would.
- Only login is rate limited. There is no audit log of who did what.
- No UI for prices or for the billing run (they have commands: `billing:price`, `billing:run`).
- Tombstones of deleted servers stay on the fake infra forever.

## "A customer says we charged them for an IP they removed yesterday"

1. Customer page, "Money history": find the charges described as `ip 203.0.113.x <hour>`.
2. Server page, "Held before": when did that address leave the customer's server (`ended_at`)?
   Or ask the database directly:

   ```sql
   -- who held the address, and when
   SELECT a.server_id, s.customer_id, a.started_at, a.ended_at
   FROM ip_assignments a
   JOIN servers s ON s.id = a.server_id
   JOIN ip_addresses ip ON ip.id = a.ip_address_id
   WHERE ip.address = '203.0.113.12' ORDER BY a.started_at;

   -- what was charged for it, to whom
   SELECT c.hour_start, c.customer_id, c.server_id, c.amount, le.id AS ledger_entry
   FROM charges c
   JOIN ip_addresses ip ON ip.id = c.billable_id
   LEFT JOIN ledger_entries le ON le.reference_type = 'charge' AND le.reference_id = c.id
   WHERE c.billable_type = 'ip' AND ip.address = '203.0.113.12' ORDER BY c.hour_start;
   ```
3. A charge is right when that customer's server held the address at some moment of that hour and nobody held it earlier in the same hour.
   Removed at 10:30 means the 10:00 hour is correctly charged (a started hour is a full hour). Any later hour charged to them is wrong.
4. If they are right, the owner refunds that amount from the customer page. The ledger is never edited: the refund is a new entry and the charge stays visible next to it.
