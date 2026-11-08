<?php

namespace Modules\Clock\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Clock\Services\Clock;

class SetClockCommand extends Command
{
    protected $signature = 'clock:set {datetime : UTC, for example "2026-01-01 10:20:00"}';

    protected $description = 'Set the application clock (forward only)';

    public function handle(Clock $clock): int
    {
        try {
            $now = $clock->set(Carbon::parse($this->argument('datetime'), 'UTC'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Clock is now '.$now->toDateTimeString().' UTC');

        return self::SUCCESS;
    }
}
