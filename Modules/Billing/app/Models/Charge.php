<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Billing\Database\Factories\ChargeFactory;
use Modules\Billing\Enums\BillableType;
use Modules\Customers\Models\Customer;
use Modules\Servers\Models\Server;
use Modules\Wallet\Models\LedgerEntry;

#[UseFactory(ChargeFactory::class)]
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'billable_type', 'billable_id', 'hour_start', 'amount', 'server_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'billable_type' => BillableType::class,
            'amount' => 'integer',
            'hour_start' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** The money movement this charge made. */
    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(LedgerEntry::class, 'reference_id')->where('reference_type', 'charge');
    }
}
