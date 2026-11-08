<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Enums\BillableType;
use Modules\Billing\Models\Price;

class PriceSeeder extends Seeder
{
    /** Minor units (cents) per started hour. */
    public function run(): void
    {
        Price::firstOrCreate(['type' => BillableType::Server, 'effective_from' => '2000-01-01 00:00:00'], ['amount' => 12]);
        Price::firstOrCreate(['type' => BillableType::Ip, 'effective_from' => '2000-01-01 00:00:00'], ['amount' => 2]);
    }
}
