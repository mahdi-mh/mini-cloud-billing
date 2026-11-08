<?php

namespace Modules\Network\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Clock\Services\Clock;
use Modules\Network\Exceptions\IpMoveRefused;
use Modules\Network\Exceptions\NoFreeIpAddress;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Servers\Interfaces\AllocatesIp;
use Modules\Servers\Models\Server;

class IpService implements AllocatesIp
{
    public function __construct(private readonly Clock $clock) {}

    public function allocateFor(Server $server): void
    {
        $this->addIp($server);
    }

    /** Take the next free address of the pool and put it on the server. */
    public function addIp(Server $server): IpAssignment
    {
        if (! $server->status->isActive()) {
            throw new IpMoveRefused('The server is not active.');
        }

        // two requests can pick the same address; the unique index decides, the loser just picks again
        for ($try = 0; $try < 3; $try++) {
            try {
                return DB::transaction(function () use ($server) {
                    $ip = IpAddress::whereDoesntHave('assignments', fn ($q) => $q->whereNull('ended_at'))
                        ->orderBy('id')
                        ->lock('for update skip locked')
                        ->first() ?? throw new NoFreeIpAddress;

                    return $this->open($ip, $server);
                });
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new NoFreeIpAddress;
    }

    /**
     * Move an address from one server to another (maybe another customer's).
     * The caller says where it believes the address is, so a second, simultaneous
     * move of the same address is refused instead of silently winning.
     */
    public function move(IpAddress $ip, Server $from, Server $to): IpAssignment
    {
        return DB::transaction(function () use ($ip, $from, $to) {
            $now = $this->clock->now();

            // everybody touching this address queues up here
            $ip = IpAddress::lockForUpdate()->findOrFail($ip->id);
            $current = $ip->assignments()->whereNull('ended_at')->first();

            if (! $current || $current->server_id !== $from->id) {
                throw new IpMoveRefused('The address is no longer on that server.');
            }

            if ($from->id === $to->id) {
                throw new IpMoveRefused('The address is already on that server.');
            }

            $target = Server::lockForUpdate()->findOrFail($to->id);

            if (! $target->status->isActive()) {
                throw new IpMoveRefused('The target server is not active.');
            }

            if (IpAssignment::where('server_id', $from->id)->whereNull('ended_at')->count() <= 1) {
                throw new IpMoveRefused('Every server keeps at least one address.');
            }

            $current->update(['ended_at' => $now]);

            return $this->open($ip, $target, $now);
        }, attempts: 3);
    }

    public function releaseAllFrom(Server $server): void
    {
        IpAssignment::where('server_id', $server->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => $this->clock->now()]);
    }

    private function open(IpAddress $ip, Server $server, $at = null): IpAssignment
    {
        return IpAssignment::create([
            'ip_address_id' => $ip->id,
            'server_id' => $server->id,
            'started_at' => $at ?? $this->clock->now(),
        ]);
    }
}
