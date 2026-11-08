<div>
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">Customers</flux:heading>

        @can('manage-infrastructure')
            <flux:modal.trigger name="new-customer">
                <flux:button variant="primary" icon="plus">New customer</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="mt-6 flex items-end gap-3">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" placeholder="Search name or email" clearable />
        </div>
        @if ($q !== '')
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Clear</flux:button>
        @endif
    </div>

    <div class="mt-6">
        <div wire:loading.delay wire:target="q,gotoPage,nextPage,previousPage,clearFilters" class="mb-3" data-state="loading">
            <flux:text class="flex items-center gap-2"><flux:icon.loading class="size-4" /> Loading customers…</flux:text>
        </div>

        @if ($error)
            <div data-state="error">
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $error }}">
                    <x-slot name="actions"><flux:button size="sm" wire:click="$refresh">Try again</flux:button></x-slot>
                </flux:callout>
            </div>
        @elseif ($customers->isEmpty())
            <div data-state="empty">
                @if ($q !== '')
                    <flux:callout icon="magnifying-glass" heading="No customer matches “{{ $q }}”">
                        <x-slot name="actions"><flux:button size="sm" wire:click="clearFilters">Clear search</flux:button></x-slot>
                    </flux:callout>
                @else
                    <flux:callout icon="users" heading="There are no customers yet" />
                @endif
            </div>
        @else
            <div data-state="ready" wire:loading.class="opacity-50" wire:target="q,gotoPage,nextPage,previousPage">
                <flux:table :paginate="$customers">
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Email</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($customers as $customer)
                            <flux:table.row :key="$customer->id">
                                <flux:table.cell><flux:link href="{{ route('customers.show', $customer) }}" wire:navigate>{{ $customer->name }}</flux:link></flux:table.cell>
                                <flux:table.cell>{{ $customer->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex gap-1">
                                        @forelse ($customer->statuses as $status)
                                            <flux:badge size="sm" :color="$status->status->color()">{{ ucfirst($status->status->value) }}</flux:badge>
                                        @empty
                                            <span class="text-zinc-400">—</span>
                                        @endforelse
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    @can('manage-infrastructure')
        <flux:modal name="new-customer" class="md:w-96">
            <form wire:submit="create" class="space-y-6">
                <flux:heading size="lg">New customer</flux:heading>
                <flux:input wire:model="name" label="Name" />
                <flux:input wire:model="email" label="Email" type="email" />
                <div class="flex"><flux:spacer /><flux:button type="submit" variant="primary">Create</flux:button></div>
            </form>
        </flux:modal>
    @endcan
</div>
