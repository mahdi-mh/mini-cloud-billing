<?php

namespace Modules\Servers\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Customers\Models\Customer;
use Modules\Network\Models\IpAssignment;
use Modules\Servers\Database\Factories\ServerFactory;
use Modules\Servers\Enums\ServerStatus;

#[UseFactory(ServerFactory::class)]
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'name', 'infra_token', 'status', 'requested_at', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ipAssignments(): HasMany
    {
        return $this->hasMany(IpAssignment::class);
    }

    /** The addresses the server holds right now. */
    public function currentIpAssignments(): HasMany
    {
        return $this->ipAssignments()->whereNull('ended_at');
    }
}
