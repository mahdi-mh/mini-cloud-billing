<?php

namespace Modules\Customers\Livewire;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Customers\Models\Customer;
use Throwable;

#[Title('Customers')]
class CustomerIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $validator = Validator::make(request()->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_if($validator->fails(), 422, $validator->errors()->first());
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q');
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage-infrastructure');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:customers,email'],
        ]);

        $customer = Customer::create($data);

        $this->redirectRoute('customers.show', $customer, navigate: true);
    }

    public function render()
    {
        $customers = null;
        $error = null;

        try {
            $like = '%'.addcslashes($this->q, '%_\\').'%';

            $customers = Customer::query()
                ->with('statuses')
                ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderBy('name')
                ->paginate(15);
        } catch (Throwable $e) {
            report($e);
            $error = 'The customer list could not be loaded.';
        }

        return view('customers::livewire.customer-index', ['customers' => $customers, 'error' => $error]);
    }
}
