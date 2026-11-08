<?php

namespace Modules\Network\Livewire;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Network\Models\IpAddress;
use Throwable;

#[Title('IP addresses')]
class IpIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $state = '';

    public function mount(): void
    {
        $validator = Validator::make(request()->query(), [
            'q' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', Rule::in(['free', 'used'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_if($validator->fails(), 422, $validator->errors()->first());
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'state');
        $this->resetPage();
    }

    public function render()
    {
        $ips = null;
        $error = null;

        try {
            $ips = IpAddress::query()
                ->with('currentAssignment.server.customer')
                ->when($this->q !== '', fn ($q) => $q->where('address', 'like', '%'.addcslashes($this->q, '%_\\').'%'))
                ->when($this->state === 'free', fn ($q) => $q->whereDoesntHave('currentAssignment'))
                ->when($this->state === 'used', fn ($q) => $q->whereHas('currentAssignment'))
                ->orderBy('id')
                ->paginate(20);
        } catch (Throwable $e) {
            report($e);
            $error = 'The address list could not be loaded.';
        }

        return view('network::livewire.ip-index', ['ips' => $ips, 'error' => $error]);
    }
}
