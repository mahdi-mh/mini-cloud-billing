<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Enums\BillableType;

class Price extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'amount', 'effective_from'];

    protected function casts(): array
    {
        return ['type' => BillableType::class, 'amount' => 'integer', 'effective_from' => 'datetime'];
    }
}
