<div class="space-y-8">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('customers.index') }}" wire:navigate>Customers</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $customer->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $customer->name }}</flux:heading>
                <flux:text>{{ $customer->email }}</flux:text>
            </div>

            {{-- a customer can have several statuses at once --}}
            <div class="flex flex-wrap items-center gap-2" data-testid="statuses">
                @foreach ($types as $type)
                    @if ($statuses->has($type->value))
                        <flux:badge :color="$type->color()">{{ ucfirst($type->value) }}</flux:badge>
                    @endif
                @endforeach
                @if ($statuses->isEmpty())
                    <flux:text size="sm">No status</flux:text>
                @endif

                @can('manage-infrastructure')
                    <flux:dropdown>
                        <flux:button size="sm" icon-trailing="chevron-down">Change status</flux:button>
                        <flux:menu>
                            @foreach ($types as $type)
                                <flux:menu.item wire:click="toggle('{{ $type->value }}')">
                                    {{ $statuses->has($type->value) ? 'Remove' : 'Set' }} {{ $type->value }}
                                </flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endcan
            </div>
        </div>
    </div>

    <livewire:wallet.customer-balance :customer="$customer" :key="'balance-'.$customer->id" />

    <livewire:servers.customer-servers :customer="$customer" :key="'servers-'.$customer->id" />

    <livewire:wallet.customer-ledger :customer="$customer" :key="'ledger-'.$customer->id" />
</div>
