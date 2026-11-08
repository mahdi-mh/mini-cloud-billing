<?php

namespace Modules\Clock\Tests\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait FakesClock
{
    /** Put the app clock at an exact moment, even in the past (tests only). */
    protected function setClock(string $utc): void
    {
        DB::table('clock_state')->updateOrInsert(['id' => 1], ['now' => Carbon::parse($utc, 'UTC')]);
    }
}
