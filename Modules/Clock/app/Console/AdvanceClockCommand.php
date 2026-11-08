<?php

namespace Modules\Clock\Console;

use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Modules\Clock\Services\Clock;

class AdvanceClockCommand extends Command
{
    protected $signature = 'clock:advance {amount : for example "90 minutes", "3 hours", "2 days"}';

    protected $description = 'Move the application clock forward';

    public function handle(Clock $clock): int
    {
        try {
            $seconds = (int) CarbonInterval::make($this->argument('amount'))->totalSeconds;
            $now = $clock->advance($seconds);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Clock is now '.$now->toDateTimeString().' UTC');

        return self::SUCCESS;
    }
}
