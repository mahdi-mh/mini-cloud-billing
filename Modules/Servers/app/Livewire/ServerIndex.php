<?php

namespace Modules\Servers\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Customers\Models\Customer;
use Modules\Network\Exceptions\NoFreeIpAddress;
use Modules\Servers\Classes\ServerSearch;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Exceptions\ServerNotAllowed;
use Modules\Servers\Services\ServerService;
use Throwable;

#[Title('Servers')]
class ServerIndex extends Component
{
    use WithPagination;

    // everything the list shows is in the URL, so a reload or a shared link gives the same list
    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $customer = '';

    #[Url(except: 'requested_at')]
    public string $sort = 'requested_at';

    #[Url(except: 'desc')]
    public string $dir = 'desc';

    // "new server" form
    public string $newName = '';

    public string $newCustomer = '';

    public function mount(): void
    {
        // the middleware refuses unknown parameters, this refuses bad values
        $validator = Validator::make(request()->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ServerStatus::class)],
            'customer' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(ServerSearch::SORTABLE)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_if($validator->fails(), 422, $validator->errors()->first());
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'status', 'customer'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        abort_unless(in_array($column, ServerSearch::SORTABLE, true), 422);

        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'status', 'customer');
        $this->resetPage();
    }

    public function create(ServerService $servers): void
    {
        Gate::authorize('manage-infrastructure');

        $this->validate([
            'newName' => ['required', 'string', 'max:60'],
            'newCustomer' => ['required', 'exists:customers,id'],
        ]);

        try {
            $servers->create(Customer::findOrFail($this->newCustomer), $this->newName);
        } catch (ServerNotAllowed|NoFreeIpAddress $e) {
            $this->addError('newCustomer', $e->getMessage());

            return;
        }

        $this->reset('newName', 'newCustomer');
        $this->modal('new-server')->close();
        Flux::toast('Server requested. It will be running in a few seconds.');
    }

    public function render(ServerSearch $search)
    {
        $servers = null;
        $error = null;

        try {
            $servers = $search->paginate($this->only('q', 'status', 'customer', 'sort', 'dir'));
        } catch (Throwable $e) {
            report($e);
            $error = 'The server list could not be loaded.';
        }

        return view('servers::livewire.server-index', [
            'servers' => $servers,
            'error' => $error,
            'statuses' => ServerStatus::cases(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'waiting' => $servers?->contains(fn ($s) => in_array($s->status, [ServerStatus::Pending, ServerStatus::Terminating], true)),
        ]);
    }
}
