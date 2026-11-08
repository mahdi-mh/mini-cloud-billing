<?php

namespace Modules\Servers\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Servers\Models\Server;
use Modules\Servers\Services\ServerService;

#[Title('Server')]
class ServerShow extends Component
{
    public Server $server;

    public function mount(Server $server): void
    {
        $this->server = $server;
    }

    public function terminate(ServerService $servers): void
    {
        Gate::authorize('manage-infrastructure');

        $this->server = $servers->terminate($this->server);
        $this->modal('terminate')->close();
        Flux::toast('Termination requested. Billing for this server stopped.');
    }

    public function render()
    {
        $this->server->refresh()->load('customer');

        return view('servers::livewire.server-show', [
            'waiting' => $this->server->status->value === 'pending' || $this->server->status->value === 'terminating',
        ]);
    }
}
