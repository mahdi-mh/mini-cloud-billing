<?php

namespace Modules\Network\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Network\Models\IpAddress;

class IpAddressFactory extends Factory
{
    protected $model = IpAddress::class;

    public function definition(): array
    {
        return ['address' => fake()->unique()->ipv4()];
    }
}
