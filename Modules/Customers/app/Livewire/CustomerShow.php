<?php

namespace Modules\Customers\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Customers\Enums\CustomerStatusType;
use Modules\Customers\Models\Customer;

#[Title('Customer')]
class CustomerShow extends Component
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function toggle(string $status): void
    {
        Gate::authorize('manage-infrastructure');

        $type = CustomerStatusType::from($status);

        $this->customer->hasStatus($type) ? $this->customer->removeStatus($type) : $this->customer->addStatus($type);
    }

    public function render()
    {
        return view('customers::livewire.customer-show', [
            'statuses' => $this->customer->statuses()->get()->keyBy(fn ($s) => $s->status->value),
            'types' => CustomerStatusType::cases(),
        ]);
    }
}
