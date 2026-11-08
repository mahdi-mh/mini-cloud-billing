<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Enums\BillableType;
use Modules\Billing\Services\PriceList;

class ChangePriceCommand extends Command
{
    protected $signature = 'billing:price {type : server or ip} {amount : cents per started hour}';

    protected $description = 'Set a new hourly price. It applies from the next full hour, never to hours already charged';

    public function handle(PriceList $prices): int
    {
        $type = BillableType::tryFrom($this->argument('type'));

        if ($type === null || ! ctype_digit($this->argument('amount')) || (int) $this->argument('amount') < 1) {
            $this->error('Use: billing:price server|ip <cents>');

            return self::FAILURE;
        }

        $price = $prices->change($type, (int) $this->argument('amount'));

        $this->info("{$type->value}: {$price->amount} cents per hour from {$price->effective_from->toDateTimeString()} UTC");

        return self::SUCCESS;
    }
}
