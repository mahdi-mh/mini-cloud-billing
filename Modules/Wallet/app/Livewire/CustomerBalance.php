<?php

namespace Modules\Wallet\Livewire;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Exceptions\IdempotencyConflict;
use Modules\Wallet\Services\WalletService;
use Modules\Wallet\Support\Money;

class CustomerBalance extends Component
{
    public Customer $customer;

    public string $topUpAmount = '';

    public string $refundAmount = '';

    // One key per form: pressing the button twice, or a retried request, is the same top-up.
    public string $topUpKey = '';

    public string $refundKey = '';

    public function mount(): void
    {
        $this->topUpKey = (string) Str::uuid();
        $this->refundKey = (string) Str::uuid();
    }

    public function topUp(WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $cents = $this->cents('topUpAmount');

        try {
            $wallet->topUp($this->customer, $cents, "topup:{$this->topUpKey}", 'Top-up by staff');
        } catch (IdempotencyConflict $e) {
            $this->addError('topUpAmount', $e->getMessage());

            return;
        }

        $this->reset('topUpAmount');
        $this->topUpKey = (string) Str::uuid();
        $this->modal('top-up')->close();
        $this->dispatch('wallet-updated');
        \Flux\Flux::toast('Top-up recorded.');
    }

    public function refund(WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $cents = $this->cents('refundAmount');

        try {
            $wallet->refund($this->customer, $cents, "refund:{$this->refundKey}", 'Refund by staff');
        } catch (IdempotencyConflict $e) {
            $this->addError('refundAmount', $e->getMessage());

            return;
        }

        $this->reset('refundAmount');
        $this->refundKey = (string) Str::uuid();
        $this->modal('refund')->close();
        $this->dispatch('wallet-updated');
        \Flux\Flux::toast('Refund recorded.');
    }

    private function cents(string $field): int
    {
        $cents = Money::parse($this->{$field});

        if (! $cents) {
            throw ValidationException::withMessages([$field => 'Enter an amount like 10 or 10.50.']);
        }

        return $cents;
    }

    #[On('wallet-updated')]
    public function refresh(): void
    {
        // the event itself is enough: it makes the component render again
    }

    public function render()
    {
        return view('wallet::livewire.customer-balance', [
            'balance' => app(WalletService::class)->balance($this->customer),
        ]);
    }
}
