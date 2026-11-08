<?php

namespace Modules\Billing\Classes;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\BillableType;
use Modules\Billing\Models\Charge;
use Modules\Billing\Services\PriceList;
use Modules\Clock\Services\Clock;
use Modules\Network\Models\IpAddress;
use Modules\Servers\Models\Server;
use Modules\Wallet\Services\WalletService;
use RuntimeException;
use Throwable;

/**
 * Charges every started hour of every running server and every used IP address.
 *
 * It does not remember where it stopped. Each run works out all the hours that should
 * be charged up to the clock, and the unique key on `charges` lets each of them in
 * only once. So it is safe to run twice at the same time, to run again after a crash,
 * and to run after a long pause: the missed hours are simply found and charged.
 */
class BillingRunner
{
    public function __construct(
        private readonly Clock $clock,
        private readonly WalletService $wallet,
        private readonly PriceList $prices,
    ) {}

    public function run(): BillingReport
    {
        $now = $this->clock->now();
        $report = new BillingReport;

        foreach (Server::whereNotNull('started_at')->where('started_at', '<=', $now)->cursor() as $server) {
            $this->guarded($report, "server #{$server->id}", fn () => $this->serverCharges($server, $now), $now);
        }

        foreach (IpAddress::whereHas('assignments')->cursor() as $ip) {
            $this->guarded($report, "ip #{$ip->id}", fn () => $this->ipCharges($ip, $now), $now);
        }

        return $report;
    }

    /** One bad item must not stop the others: it is reported and tried again on the next run. */
    private function guarded(BillingReport $report, string $what, \Closure $dueCharges, CarbonImmutable $now): void
    {
        try {
            $due = $dueCharges();
        } catch (Throwable $e) {
            report($e);
            $report->failures[] = "{$what}: {$e->getMessage()}";

            return;
        }

        foreach ($due as $charge) {
            try {
                $this->charge($charge) ? $report->charged++ : $report->skipped++;
            } catch (Throwable $e) {
                report($e);
                $report->failures[] = "{$charge->label}: {$e->getMessage()}";
            }
        }
    }

    /** @return list<DueCharge> */
    private function serverCharges(Server $server, CarbonImmutable $now): array
    {
        $hours = $this->hours($server->started_at->toImmutable(), $server->ended_at?->toImmutable(), $now);

        return array_map(
            fn (CarbonImmutable $hour) => new DueCharge(BillableType::Server, $server->id, $server->customer_id, $hour, $server->id, "server #{$server->id} {$hour->format('Y-m-d H:i')}"),
            $this->notChargedYet(BillableType::Server, $server->id, $hours),
        );
    }

    /**
     * An address is paid for while it sits on a running server. When it moves in the middle
     * of an hour, that hour belongs to whoever had it first; the new holder pays from the next one.
     * That way there is always exactly one payer per address per hour.
     *
     * @return list<DueCharge>
     */
    private function ipCharges(IpAddress $ip, CarbonImmutable $now): array
    {
        $hours = [];
        $payer = [];
        $lastOwned = null;

        $assignments = $ip->assignments()->with('server')->orderBy('started_at')->orderBy('id')->get();

        foreach ($assignments as $assignment) {
            $server = $assignment->server;

            if ($server->started_at === null) {
                continue;
            }

            $start = $assignment->started_at->max($server->started_at)->toImmutable();
            $end = collect([$assignment->ended_at, $server->ended_at])->filter()->min()?->toImmutable();

            if ($end !== null && $end < $start) {
                continue;
            }

            foreach ($this->hours($start, $end, $now) as $hour) {
                if ($lastOwned === null || $hour > $lastOwned) {
                    $hours[] = $hour;
                    $payer[$hour->getTimestamp()] = $server;
                    $lastOwned = $hour;
                }
            }
        }

        return array_map(
            fn (CarbonImmutable $hour) => new DueCharge(
                BillableType::Ip,
                $ip->id,
                $payer[$hour->getTimestamp()]->customer_id,
                $hour,
                $payer[$hour->getTimestamp()]->id,
                "ip {$ip->address} {$hour->format('Y-m-d H:i')}",
            ),
            $this->notChargedYet(BillableType::Ip, $ip->id, $hours),
        );
    }

    /**
     * Every hour touched by [start, end), up to the hour we are in. Any started hour counts.
     *
     * @return list<CarbonImmutable>
     */
    private function hours(CarbonImmutable $start, ?CarbonImmutable $end, CarbonImmutable $now): array
    {
        $last = $now->startOfHour();

        if ($end !== null) {
            $last = $last->min($end->subSecond()->startOfHour());
        }

        $hours = [];

        for ($hour = $start->startOfHour(); $hour <= $last; $hour = $hour->addHour()) {
            $hours[] = $hour;
        }

        return $hours;
    }

    /**
     * @param  list<CarbonImmutable>  $hours
     * @return list<CarbonImmutable>
     */
    private function notChargedYet(BillableType $type, int $billableId, array $hours): array
    {
        if ($hours === []) {
            return [];
        }

        $done = Charge::where('billable_type', $type)
            ->where('billable_id', $billableId)
            ->whereBetween('hour_start', [$hours[0], end($hours)])
            ->pluck('hour_start')
            ->map(fn ($h) => $h->getTimestamp())
            ->flip();

        return array_values(array_filter($hours, fn ($hour) => ! $done->has($hour->getTimestamp())));
    }

    /** @return bool false when somebody (or an earlier run) already charged this hour */
    private function charge(DueCharge $due): bool
    {
        $amount = $this->prices->priceFor($due->type, $due->hour)
            ?? throw new RuntimeException("No {$due->type->value} price for {$due->hour}.");

        // the charge and the money movement go in together or not at all
        return DB::transaction(function () use ($due, $amount) {
            try {
                $charge = Charge::create([
                    'customer_id' => $due->customerId,
                    'billable_type' => $due->type,
                    'billable_id' => $due->billableId,
                    'hour_start' => $due->hour,
                    'amount' => $amount,
                    'server_id' => $due->serverId,
                    'created_at' => $this->clock->now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            $this->wallet->charge($due->customerId, $amount, $due->key(), $due->label, 'charge', $charge->id);

            return true;
        });
    }
}
