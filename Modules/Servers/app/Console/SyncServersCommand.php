<?php

namespace Modules\Servers\Console;

use Illuminate\Console\Command;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Jobs\SyncServer;
use Modules\Servers\Models\Server;

class SyncServersCommand extends Command
{
    protected $signature = 'servers:sync';

    protected $description = 'Queue a sync for every server that is waiting on the infrastructure';

    public function handle(): int
    {
        // safety net for jobs that were lost (worker restart, crash): the job itself keeps repeating
        $ids = Server::whereIn('status', [ServerStatus::Pending, ServerStatus::Terminating])->pluck('id');

        $ids->each(fn ($id) => SyncServer::dispatch($id));

        $this->info("Queued {$ids->count()} server(s).");

        return self::SUCCESS;
    }
}
