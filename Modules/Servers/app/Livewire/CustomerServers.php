<?php

namespace Modules\Servers\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Modules\Customers\Models\Customer;
use Modules\Servers\Models\Server;
use Throwable;

/** The customer's servers and where each of them stands right now. */
#[Lazy]
class CustomerServers extends Component
{
    public Customer $customer;

    public function placeholder(): View
    {
        return view('servers::livewire.customer-servers-placeholder');
    }

    public function render()
    {
        $servers = null;
        $error = null;

        try {
            $servers = Server::where('customer_id', $this->customer->id)
                ->with('currentIpAssignments.ipAddress')
                ->orderByDesc('id')->get();
        } catch (Throwable $e) {
            report($e);
            $error = 'The servers could not be loaded.';
        }

        return view('servers::livewire.customer-servers', ['servers' => $servers, 'error' => $error]);
    }
}
