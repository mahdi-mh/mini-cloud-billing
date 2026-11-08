<?php

namespace Modules\Customers\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clock\Services\Clock;
use Modules\Customers\Database\Factories\CustomerFactory;
use Modules\Customers\Enums\CustomerStatusType;

#[UseFactory(CustomerFactory::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email'];

    public function statuses(): HasMany
    {
        return $this->hasMany(CustomerStatus::class);
    }

    public function hasStatus(CustomerStatusType $type): bool
    {
        return $this->statuses()->where('status', $type)->exists();
    }

    public function addStatus(CustomerStatusType $type): void
    {
        $this->statuses()->firstOrCreate(['status' => $type], ['since' => app(Clock::class)->now()]);
    }

    public function removeStatus(CustomerStatusType $type): void
    {
        $this->statuses()->where('status', $type)->delete();
    }
}
