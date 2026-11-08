<?php

namespace Modules\Billing\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Billing\Models\Charge;
use Modules\Servers\Models\Server;
use Throwable;

/** What was charged for one server and for the addresses it held, hour by hour. */
#[Lazy]
class ServerCharges extends Component
{
    use WithPagination;

    public Server $server;

    public function placeholder(): View
    {
        return view('billing::livewire.charges-placeholder');
    }

    public function render()
    {
        $charges = null;
        $error = null;

        try {
            $charges = Charge::where('server_id', $this->server->id)
                ->with(['customer', 'ipAddress'])
                ->orderByDesc('hour_start')->orderByDesc('id')
                ->paginate(10, pageName: 'charges');
        } catch (Throwable $e) {
            report($e);
            $error = 'The billing history could not be loaded.';
        }

        return view('billing::livewire.server-charges', ['charges' => $charges, 'error' => $error]);
    }
}
