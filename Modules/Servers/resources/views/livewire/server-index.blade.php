<div @if ($waiting) wire:poll.3s @endif>
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">Servers</flux:heading>

        @can('manage-infrastructure')
            <flux:modal.trigger name="new-server">
                <flux:button variant="primary" icon="plus">New server</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" placeholder="Search name, customer or IP" clearable />
        </div>

        <flux:select wire:model.live="status" class="sm:w-44">
            <flux:select.option value="">Any status</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option value="{{ $s->value }}">{{ ucfirst($s->value) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="customer" class="sm:w-56">
            <flux:select.option value="">Any customer</flux:select.option>
            @foreach ($customers as $c)
                <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($q !== '' || $status !== '' || $customer !== '')
            <flux:button variant="ghost" wire:click="clearFilters" icon="x-mark">Clear</flux:button>
        @endif
    </div>

    <div class="mt-6">
        {{-- loading: shown while a search, sort or page change is on its way --}}
        <div wire:loading.delay wire:target="q,status,customer,sortBy,gotoPage,nextPage,previousPage,clearFilters" class="mb-3" data-state="loading">
            <flux:text class="flex items-center gap-2"><flux:icon.loading class="size-4" /> Loading servers…</flux:text>
        </div>

        @if ($error)
            {{-- failed --}}
            <div data-state="error">
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $error }}" text="Nothing is lost, the data is fine. Try again in a moment.">
                    <x-slot name="actions">
                        <flux:button wire:click="$refresh" size="sm">Try again</flux:button>
                    </x-slot>
                </flux:callout>
            </div>
        @elseif ($servers->isEmpty())
            {{-- empty --}}
            <div data-state="empty">
                @if ($q !== '' || $status !== '' || $customer !== '')
                    <flux:callout icon="magnifying-glass" heading="No server matches these filters">
                        <x-slot name="actions">
                            <flux:button wire:click="clearFilters" size="sm">Clear filters</flux:button>
                        </x-slot>
                    </flux:callout>
                @else
                    <flux:callout icon="server" heading="There are no servers yet" text="Servers created for a customer show up here." />
                @endif
            </div>
        @else
            <div data-state="ready" wire:loading.class="opacity-50" wire:target="q,status,customer,sortBy,gotoPage,nextPage,previousPage">
                <flux:table :paginate="$servers">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sort === 'name'" :direction="$dir" wire:click="sortBy('name')">Name</flux:table.column>
                        <flux:table.column>Customer</flux:table.column>
                        <flux:table.column sortable :sorted="$sort === 'status'" :direction="$dir" wire:click="sortBy('status')">Status</flux:table.column>
                        <flux:table.column>IP addresses</flux:table.column>
                        <flux:table.column sortable :sorted="$sort === 'requested_at'" :direction="$dir" wire:click="sortBy('requested_at')">Requested (UTC)</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($servers as $server)
                            <flux:table.row :key="$server->id">
                                <flux:table.cell>
                                    <flux:link href="{{ route('servers.show', $server) }}" wire:navigate>{{ $server->name }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:link href="{{ route('customers.show', $server->customer_id) }}" wire:navigate>{{ $server->customer->name }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$server->status->color()" inset="top bottom">{{ ucfirst($server->status->value) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">
                                    {{ $server->currentIpAssignments->map(fn ($a) => $a->ipAddress->address)->join(', ') ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell>{{ $server->requested_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    @can('manage-infrastructure')
        <flux:modal name="new-server" class="md:w-96">
            <form wire:submit="create" class="space-y-6">
                <flux:heading size="lg">New server</flux:heading>

                <flux:select wire:model="newCustomer" label="Customer" placeholder="Choose a customer">
                    @foreach ($customers as $c)
                        <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="newName" label="Name" placeholder="web-1" />

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Create</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
