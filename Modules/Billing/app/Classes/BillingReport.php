<?php

namespace Modules\Billing\Classes;

class BillingReport
{
    public int $charged = 0;

    /** already charged by an earlier or a parallel run */
    public int $skipped = 0;

    /** @var list<string> */
    public array $failures = [];

    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
