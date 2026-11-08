<?php

namespace Modules\Network\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Network\Database\Factories\IpAddressFactory;

#[UseFactory(IpAddressFactory::class)]
class IpAddress extends Model
{
    /** @use HasFactory<IpAddressFactory> */
    use HasFactory;

    protected $fillable = ['address'];

    public function assignments(): HasMany
    {
        return $this->hasMany(IpAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(IpAssignment::class)->whereNull('ended_at');
    }
}
