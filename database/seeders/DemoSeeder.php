<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Customers\Models\Customer;
use Modules\Servers\Models\Server;
use Modules\Servers\Services\ServerService;

/** A few servers to click around with. The sync job brings them to "running". */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $servers = app(ServerService::class);

        $plan = [
            'acme-hosting@example.test' => ['alpha-web', 'alpha-db'],
            'globex-labs@example.test' => ['beta-web'],
            'initech@example.test' => ['gamma-api'],
        ];

        foreach ($plan as $email => $names) {
            $customer = Customer::where('email', $email)->firstOrFail();

            foreach ($names as $name) {
                if (! Server::where('customer_id', $customer->id)->where('name', $name)->exists()) {
                    $servers->create($customer, $name);
                }
            }
        }
    }
}
