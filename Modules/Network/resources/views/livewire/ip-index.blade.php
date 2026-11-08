<div>
    <flux:heading size="xl" level="1">IP addresses</flux:heading>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-64">
            <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" placeholder="Search address" clearable />
        </div>
        <flux:select wire:model.live="state" class="sm:w-44">
            <flux:select.option value="">Free and used</flux:select.option>
            <flux:select.option value="free">Free</flux:select.option>
            <flux:select.option value="used">Used</flux:select.option>
        </flux:select>
        @if ($q !== '' || $state !== '')
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Clear</flux:button>
        @endif
    </div>

    <div class="mt-6">
        <div wire:loading.delay wire:target="q,state,gotoPage,nextPage,previousPage,clearFilters" class="mb-3" data-state="loading">
            <flux:text class="flex items-center gap-2"><flux:icon.loading class="size-4" /> Loading addresses…</flux:text>
        </div>

        @if ($error)
            <div data-state="error">
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $error }}">
                    <x-slot name="actions"><flux:button size="sm" wire:click="$refresh">Try again</flux:button></x-slot>
                </flux:callout>
            </div>
        @elseif ($ips->isEmpty())
            <div data-state="empty">
                <flux:callout icon="magnifying-glass" heading="No address matches these filters">
                    <x-slot name="actions"><flux:button size="sm" wire:click="clearFilters">Clear filters</flux:button></x-slot>
                </flux:callout>
            </div>
        @else
            <div data-state="ready" wire:loading.class="opacity-50" wire:target="q,state,gotoPage,nextPage,previousPage">
                <flux:table :paginate="$ips">
                    <flux:table.columns>
                        <flux:table.column>Address</flux:table.column>
                        <flux:table.column>State</flux:table.column>
                        <flux:table.column>Server</flux:table.column>
                        <flux:table.column>Customer</flux:table.column>
                        <flux:table.column>Since (UTC)</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($ips as $ip)
                            @php($holder = $ip->currentAssignment)
                            <flux:table.row :key="$ip->id">
                                <flux:table.cell class="font-mono">{{ $ip->address }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$holder ? 'blue' : 'zinc'">{{ $holder ? 'Used' : 'Free' }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($holder)
                                        <flux:link href="{{ route('servers.show', $holder->server_id) }}" wire:navigate>{{ $holder->server->name }}</flux:link>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $holder?->server->customer->name }}</flux:table.cell>
                                <flux:table.cell>{{ $holder?->started_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>
</div>
