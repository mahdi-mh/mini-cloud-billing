<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Classes\BillingRunner;

class RunBillingCommand extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Charge every hour of usage that is due (safe to run at any time, any number of times)';

    public function handle(BillingRunner $runner): int
    {
        $report = $runner->run();

        $this->info("Charged {$report->charged}, already charged {$report->skipped}.");

        foreach ($report->failures as $failure) {
            $this->error("Could not bill {$failure}");
        }

        return $report->failed() ? self::FAILURE : self::SUCCESS;
    }
}
