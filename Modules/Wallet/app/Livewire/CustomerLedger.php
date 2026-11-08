<?php

namespace Modules\Wallet\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Models\LedgerEntry;
use Throwable;

/** The money history: every movement, newest first. */
#[Lazy]
class CustomerLedger extends Component
{
    use WithPagination;

    public Customer $customer;

    public function placeholder(): View
    {
        return view('wallet::livewire.ledger-placeholder');
    }

    #[On('wallet-updated')]
    public function refresh(): void
    {
        // the event itself is enough: it makes the component render again
    }

    public function render()
    {
        $entries = null;
        $error = null;

        try {
            $entries = LedgerEntry::where('customer_id', $this->customer->id)
                ->orderByDesc('id')
                ->paginate(10, pageName: 'ledger');
        } catch (Throwable $e) {
            report($e);
            $error = 'The money history could not be loaded.';
        }

        return view('wallet::livewire.customer-ledger', ['entries' => $entries, 'error' => $error]);
    }
}
