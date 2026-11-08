<?php

namespace Modules\Servers\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Servers\Classes\ServerSyncer;
use Modules\Servers\Models\Server;

class SyncServer implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $serverId) {}

    // one waiting job per server is enough
    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public function handle(ServerSyncer $syncer): void
    {
        $server = Server::find($this->serverId);

        if ($server && ! $syncer->sync($server)) {
            // not there yet: look again in a few seconds
            self::dispatch($this->serverId)->delay(now()->addSeconds((int) config('servers.sync_delay')));
        }
    }
}
