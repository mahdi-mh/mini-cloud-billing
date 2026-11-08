<?php

namespace Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customers\Enums\CustomerStatusType;

class CustomerStatus extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_id', 'status', 'since'];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatusType::class,
            'since' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
