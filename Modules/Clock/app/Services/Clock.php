<?php

namespace Modules\Clock\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only source of "now" for anything related to billing.
 * Time does not move by itself: it only moves when somebody advances it.
 */
class Clock
{
    public function now(): CarbonImmutable
    {
        $now = DB::table('clock_state')->where('id', 1)->value('now');

        if ($now === null) {
            // first read: start from the real time
            DB::table('clock_state')->insertOrIgnore(['id' => 1, 'now' => CarbonImmutable::now('UTC')->startOfSecond()]);
            $now = DB::table('clock_state')->where('id', 1)->value('now');
        }

        return CarbonImmutable::parse($now, 'UTC');
    }

    /** Start of the hour we are in. */
    public function currentHour(): CarbonImmutable
    {
        return $this->now()->startOfHour();
    }

    public function set(CarbonInterface $at): CarbonImmutable
    {
        $at = CarbonImmutable::instance($at)->utc()->startOfSecond();

        // going back in time would break the "charged once" history
        if ($at->lt($this->now())) {
            throw new \InvalidArgumentException('The clock cannot go backwards.');
        }

        DB::table('clock_state')->updateOrInsert(['id' => 1], ['now' => $at]);

        return $at;
    }

    public function advance(int $seconds): CarbonImmutable
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Advance by a positive number of seconds.');
        }

        return $this->set($this->now()->addSeconds($seconds));
    }
}
