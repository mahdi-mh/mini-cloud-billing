<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\BillableType;
use Modules\Billing\Models\Charge;
use Modules\Customers\Models\Customer;

class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'billable_type' => BillableType::Server,
            'billable_id' => fake()->numberBetween(1, 1000),
            'hour_start' => now()->startOfHour(),
            'amount' => 12,
            'created_at' => now(),
        ];
    }
}
