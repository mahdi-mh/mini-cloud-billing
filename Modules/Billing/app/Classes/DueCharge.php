<?php

namespace Modules\Billing\Classes;

use Carbon\CarbonImmutable;
use Modules\Billing\Enums\BillableType;

/** One hour of one resource that should have been charged. */
final readonly class DueCharge
{
    public function __construct(
        public BillableType $type,
        public int $billableId,
        public int $customerId,
        public CarbonImmutable $hour,
        public int $serverId,
        public string $label,
    ) {}

    /** Same resource + same hour = same key, wherever and however often it is computed. */
    public function key(): string
    {
        return "charge:{$this->type->value}:{$this->billableId}:{$this->hour->format('Y-m-d\TH')}";
    }
}
