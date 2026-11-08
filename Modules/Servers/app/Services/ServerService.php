<?php

namespace Modules\Servers\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clock\Services\Clock;
use Modules\Customers\Enums\CustomerStatusType;
use Modules\Customers\Models\Customer;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Exceptions\ServerNotAllowed;
use Modules\Servers\Interfaces\AllocatesIp;
use Modules\Servers\Jobs\SyncServer;
use Modules\Servers\Models\Server;

class ServerService
{
    public function __construct(private readonly Clock $clock, private readonly AllocatesIp $ips) {}

    /**
     * Records the server and reserves its IP in one transaction, then lets the sync job
     * talk to the infrastructure. Nothing here depends on the infrastructure answering.
     */
    public function create(Customer $customer, string $name): Server
    {
        if ($customer->hasStatus(CustomerStatusType::Suspended)) {
            throw new ServerNotAllowed('A suspended customer cannot create servers.');
        }

        $server = DB::transaction(function () use ($customer, $name) {
            $server = Server::create([
                'customer_id' => $customer->id,
                'name' => $name,
                'infra_token' => (string) Str::uuid(),
                'status' => ServerStatus::Pending,
                'requested_at' => $this->clock->now(),
            ]);

            // throws when the pool is empty, which also rolls the server back
            $this->ips->allocateFor($server);

            return $server;
        });

        SyncServer::dispatch($server->id)->afterCommit();

        return $server;
    }

    /** Stops billing right away and frees the IPs. Asking twice does nothing the second time. */
    public function terminate(Server $server): Server
    {
        $changed = DB::transaction(function () use ($server) {
            $locked = Server::lockForUpdate()->findOrFail($server->id);

            if (! $locked->status->isActive()) {
                return false;
            }

            $locked->update(['status' => ServerStatus::Terminating, 'ended_at' => $this->clock->now()]);
            $this->ips->releaseAllFrom($locked);

            return true;
        });

        if ($changed) {
            SyncServer::dispatch($server->id)->afterCommit();
        }

        return $server->refresh();
    }
}
