<?php

namespace Modules\FakeInfra\Models;

use Illuminate\Database\Eloquent\Model;

class InfraServer extends Model
{
    protected $connection = 'infra';

    protected $primaryKey = 'token';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['token', 'name', 'state', 'ready_at'];

    protected function casts(): array
    {
        return ['ready_at' => 'datetime'];
    }

    /** A server that is still "creating" becomes "running" once its delay is over (real time). */
    public function settle(): static
    {
        if ($this->state === 'creating' && $this->ready_at->lte(now())) {
            $this->update(['state' => 'running']);
        }

        return $this;
    }
}
