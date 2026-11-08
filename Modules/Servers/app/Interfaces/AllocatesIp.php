<?php

namespace Modules\Servers\Interfaces;

use Modules\Servers\Models\Server;

/**
 * What servers need from the IP pool. The network module implements it,
 * so servers never has to know how addresses are stored.
 */
interface AllocatesIp
{
    /** Give the server an address from the pool. Throws when the pool is empty. */
    public function allocateFor(Server $server): void;

    /** Take back every address the server holds. */
    public function releaseAllFrom(Server $server): void;
}
