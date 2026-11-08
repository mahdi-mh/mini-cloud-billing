<?php

namespace Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Database\Factories\LedgerEntryFactory;
use Modules\Wallet\Enums\LedgerEntryType;

#[UseFactory(LedgerEntryFactory::class)]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'type', 'amount', 'idempotency_key', 'description', 'reference_type', 'reference_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
