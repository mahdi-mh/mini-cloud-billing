<?php

namespace Modules\Network\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Network\Models\IpAddress;

class IpPoolSeeder extends Seeder
{
    /** The fixed pool: a slice of the documentation range 203.0.113.0/24. */
    public function run(): void
    {
        foreach (range(10, 59) as $last) {
            IpAddress::firstOrCreate(['address' => "203.0.113.{$last}"]);
        }
    }
}
