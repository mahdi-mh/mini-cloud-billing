<div @if ($waiting) wire:poll.3s @endif class="space-y-8">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('servers.index') }}" wire:navigate>Servers</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $server->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $server->name }}</flux:heading>
                <flux:badge :color="$server->status->color()">{{ ucfirst($server->status->value) }}</flux:badge>
            </div>

            @can('manage-infrastructure')
                @if ($server->status->isActive())
                    <flux:modal.trigger name="terminate">
                        <flux:button variant="danger" icon="trash">Terminate</flux:button>
                    </flux:modal.trigger>
                @endif
            @endcan
        </div>
    </div>

    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div>
            <flux:text size="sm">Customer</flux:text>
            <flux:link href="{{ route('customers.show', $server->customer_id) }}" wire:navigate>{{ $server->customer->name }}</flux:link>
        </div>
        <div>
            <flux:text size="sm">Requested (UTC)</flux:text>
            <div>{{ $server->requested_at->format('Y-m-d H:i') }}</div>
        </div>
        <div>
            <flux:text size="sm">Billed from (UTC)</flux:text>
            <div>{{ $server->started_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
        <div>
            <flux:text size="sm">Billed until (UTC)</flux:text>
            <div>{{ $server->ended_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
    </dl>

    @if ($server->last_error && ! in_array($server->status->value, ['running', 'terminated']))
        <flux:callout variant="warning" icon="exclamation-triangle" heading="The infrastructure had trouble" text="{{ $server->last_error }}" />
    @endif

    <livewire:network.server-ips :server="$server" :key="'ips-'.$server->id" />

    <livewire:billing.server-charges :server="$server" :key="'charges-'.$server->id" lazy />

    @can('manage-infrastructure')
        <flux:modal name="terminate" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Terminate {{ $server->name }}?</flux:heading>
                    <flux:text class="mt-2">Billing stops now and its IP addresses go back to the pool. This cannot be undone.</flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                    <flux:button variant="danger" wire:click="terminate">Terminate</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
