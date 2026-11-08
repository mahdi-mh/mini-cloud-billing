<?php

namespace Modules\Billing\Services;

use Carbon\CarbonInterface;
use Modules\Billing\Enums\BillableType;
use Modules\Billing\Models\Price;
use Modules\Clock\Services\Clock;

class PriceList
{
    public function __construct(private readonly Clock $clock) {}

    /** The price that was valid at the start of the given hour, or null if there is none yet. */
    public function priceFor(BillableType $type, CarbonInterface $hour): ?int
    {
        return Price::where('type', $type)
            ->where('effective_from', '<=', $hour)
            ->orderByDesc('effective_from')
            ->value('amount');
    }

    /**
     * A new price starts with the next full hour. The hour we are in may already be
     * charged, and charged hours are never touched.
     */
    public function change(BillableType $type, int $amount): Price
    {
        return Price::updateOrCreate(
            ['type' => $type, 'effective_from' => $this->clock->now()->startOfHour()->addHour()],
            ['amount' => $amount],
        );
    }
}
