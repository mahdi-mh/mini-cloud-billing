<?php

namespace Modules\Network\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Network\Exceptions\IpMoveRefused;
use Modules\Network\Exceptions\NoFreeIpAddress;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Network\Services\IpService;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Models\Server;

/** The addresses of one server: what it holds now, what it held before, and the actions on them. */
class ServerIps extends Component
{
    public Server $server;

    public ?int $movingIp = null;

    public string $targetServer = '';

    public function addIp(IpService $ips): void
    {
        Gate::authorize('manage-infrastructure');

        try {
            $ips->addIp($this->server);
        } catch (NoFreeIpAddress|IpMoveRefused $e) {
            \Flux\Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function startMove(int $ipId): void
    {
        Gate::authorize('manage-infrastructure');

        $this->movingIp = $ipId;
        $this->reset('targetServer');
        $this->resetErrorBag();
        $this->modal('move-ip')->show();
    }

    public function move(IpService $ips): void
    {
        Gate::authorize('manage-infrastructure');

        $this->validate(['targetServer' => ['required', 'exists:servers,id']]);

        try {
            // we say where we believe the address is: if somebody moved it meanwhile, this is refused
            $ips->move(IpAddress::findOrFail($this->movingIp), $this->server, Server::findOrFail($this->targetServer));
        } catch (IpMoveRefused $e) {
            $this->addError('targetServer', $e->getMessage());

            return;
        }

        $this->modal('move-ip')->close();
        \Flux\Flux::toast('Address moved.');
    }

    public function render()
    {
        $current = $this->server->currentIpAssignments()->with('ipAddress')->get();

        return view('network::livewire.server-ips', [
            'current' => $current,
            'history' => IpAssignment::where('server_id', $this->server->id)->whereNotNull('ended_at')
                ->with('ipAddress')->orderByDesc('ended_at')->limit(20)->get(),
            'targets' => Server::whereIn('status', [ServerStatus::Pending, ServerStatus::Running])
                ->where('id', '!=', $this->server->id)->with('customer')->orderBy('name')->get(),
            'canAdd' => $this->server->status->isActive(),
        ]);
    }
}
