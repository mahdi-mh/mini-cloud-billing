<?php

namespace Modules\Wallet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Enums\LedgerEntryType;
use Modules\Wallet\Models\LedgerEntry;

class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => LedgerEntryType::TopUp,
            'amount' => 1000,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}
