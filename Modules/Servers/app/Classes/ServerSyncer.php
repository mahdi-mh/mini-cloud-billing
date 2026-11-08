<?php

namespace Modules\Servers\Classes;

use Modules\Clock\Services\Clock;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Exceptions\InfraUnavailable;
use Modules\Servers\Interfaces\AllocatesIp;
use Modules\Servers\Models\Server;

/**
 * Brings one server and the infrastructure in line. One call is one pass and is safe to repeat:
 * the infra is keyed by our token, and every change of status is a compare-and-set,
 * so two passes at the same time can't both win.
 */
class ServerSyncer
{
    public function __construct(
        private readonly InfraClient $infra,
        private readonly Clock $clock,
        private readonly AllocatesIp $ips,
    ) {}

    /** @return bool true when there is nothing left to do */
    public function sync(Server $server): bool
    {
        return match ($server->status) {
            ServerStatus::Pending => $this->syncPending($server),
            ServerStatus::Terminating => $this->syncTerminating($server),
            default => true,
        };
    }

    private function syncPending(Server $server): bool
    {
        try {
            $state = $this->infra->state($server->infra_token);

            if ($state === null) {
                // never created, or the create call really failed: ask again (same token, so no duplicate)
                try {
                    $this->infra->create($server->infra_token, $server->name);
                } catch (InfraUnavailable $e) {
                    // it may or may not have happened, the next pass will see
                    return $this->giveUpOrWait($server, $e->getMessage());
                }

                return $this->giveUpOrWait($server, null);
            }

            if ($state === 'running') {
                $this->transition($server, ServerStatus::Pending, ServerStatus::Running, ['started_at' => $this->clock->now()]);

                return true;
            }

            if (in_array($state, ['stopped', 'deleted'], true)) {
                $this->fail($server, "Infra reports the new server as {$state}.");

                return true;
            }

            return $this->giveUpOrWait($server, null);
        } catch (InfraUnavailable $e) {
            return $this->giveUpOrWait($server, $e->getMessage());
        }
    }

    private function syncTerminating(Server $server): bool
    {
        try {
            $state = $this->infra->state($server->infra_token);

            if ($state === null || $state === 'deleted') {
                $this->transition($server, ServerStatus::Terminating, ServerStatus::Terminated);

                return true;
            }

            $this->infra->delete($server->infra_token);
        } catch (InfraUnavailable $e) {
            $server->forceFill(['last_error' => $e->getMessage()])->saveQuietly();
        }

        // checked again on the next pass; a delete that "failed" may well have worked
        return false;
    }

    /** @return bool true when we gave up */
    private function giveUpOrWait(Server $server, ?string $error): bool
    {
        $attempts = $server->sync_attempts + 1;
        $server->forceFill(['sync_attempts' => $attempts, 'last_error' => $error])->saveQuietly();

        if ($attempts < config('servers.sync_max_attempts')) {
            return false;
        }

        // only if we really moved it to failed (someone else may have made it running meanwhile)
        if ($this->fail($server, $error ?? 'The infrastructure never got the server ready.')) {
            // make sure nothing is left running behind our back
            try {
                $this->infra->delete($server->infra_token);
            } catch (InfraUnavailable) {
                // the drift check will find it if it exists
            }
        }

        return true;
    }

    private function fail(Server $server, string $reason): bool
    {
        if (! $this->transition($server, ServerStatus::Pending, ServerStatus::Failed, ['last_error' => $reason, 'ended_at' => $this->clock->now()])) {
            return false;
        }

        $this->ips->releaseAllFrom($server);

        return true;
    }

    /** Compare-and-set on the status. */
    private function transition(Server $server, ServerStatus $from, ServerStatus $to, array $extra = []): bool
    {
        $changed = Server::where('id', $server->id)->where('status', $from)->update(['status' => $to, ...$extra]) === 1;

        $server->refresh();

        return $changed;
    }
}
