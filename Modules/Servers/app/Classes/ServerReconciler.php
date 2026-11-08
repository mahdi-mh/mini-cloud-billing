<?php

namespace Modules\Servers\Classes;

use Modules\Clock\Services\Clock;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Interfaces\AllocatesIp;
use Modules\Servers\Models\Server;

/**
 * Compares what we think exists with what the infrastructure says exists and repairs the difference.
 * The sync job follows one server at a time; this one looks at everything, to catch what slipped through.
 */
class ServerReconciler
{
    public function __construct(
        private readonly InfraClient $infra,
        private readonly Clock $clock,
        private readonly AllocatesIp $ips,
    ) {}

    /** @return list<string> what was found and fixed */
    public function reconcile(): array
    {
        // Ask the infra first, then read our own records: every token the infra knows was
        // written down here before it was ever sent, so it can't be missing from the second read.
        $remote = collect($this->infra->all())->keyBy('token');
        $ours = Server::all()->keyBy('infra_token');

        $fixed = [];

        foreach ($remote as $token => $row) {
            $server = $ours->get($token);

            if ($server === null && $row['state'] !== 'deleted') {
                // somebody is running something we don't know about, and nobody pays for it
                $this->infra->delete($token);
                $fixed[] = "orphan {$token} on the infra: deleted";
            } elseif ($server !== null && in_array($server->status, [ServerStatus::Terminated, ServerStatus::Failed], true) && $row['state'] !== 'deleted') {
                $this->infra->delete($token);
                $fixed[] = "server #{$server->id} is {$server->status->value} here but {$row['state']} on the infra: deleted there";
            }
        }

        foreach ($ours->where('status', ServerStatus::Running) as $server) {
            $state = $remote->get($server->infra_token)['state'] ?? null;

            if ($state === null || $state === 'deleted') {
                // it is gone: stop billing for it from now
                $lost = Server::where('id', $server->id)->where('status', ServerStatus::Running)
                    ->update(['status' => ServerStatus::Terminated, 'ended_at' => $this->clock->now(), 'last_error' => 'Gone from the infrastructure.']);

                if ($lost) {
                    $this->ips->releaseAllFrom($server);
                    $fixed[] = "server #{$server->id} was running here but gone on the infra: closed";
                }
            }
        }

        return $fixed;
    }
}
