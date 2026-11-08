<?php

namespace Modules\Servers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Customers\Models\Customer;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Models\Server;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->unique()->slug(2),
            'infra_token' => (string) Str::uuid(),
            'status' => ServerStatus::Pending,
            'requested_at' => now(),
        ];
    }

    public function running(string $startedAt = null): static
    {
        return $this->state(fn () => [
            'status' => ServerStatus::Running,
            'started_at' => $startedAt ?? now(),
        ]);
    }
}
