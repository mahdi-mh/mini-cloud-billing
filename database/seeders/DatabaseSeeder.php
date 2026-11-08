<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Billing\Database\Seeders\PriceSeeder;
use Modules\Customers\Enums\CustomerStatusType;
use Modules\Customers\Models\Customer;
use Modules\Network\Database\Seeders\IpPoolSeeder;
use Modules\Wallet\Services\WalletService;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StaffRole::cases() as $role) {
            User::firstOrCreate(
                ['email' => "{$role->value}@minicloud.test"],
                ['name' => ucfirst($role->value).' Staff', 'password' => 'password', 'role' => $role],
            );
        }

        $this->call([IpPoolSeeder::class, PriceSeeder::class]);

        $wallet = app(WalletService::class);

        foreach (['Acme Hosting' => 5000, 'Globex Labs' => 2500, 'Initech' => 0] as $name => $balance) {
            $customer = Customer::firstOrCreate(
                ['email' => str($name)->slug().'@example.test'],
                ['name' => $name],
            );
            $customer->addStatus(CustomerStatusType::Verified);

            if ($balance > 0) {
                $wallet->topUp($customer, $balance, "seed:{$customer->id}", 'Starting balance');
            }
        }
    }
}
