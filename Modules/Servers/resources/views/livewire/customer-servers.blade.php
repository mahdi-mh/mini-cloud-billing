<div>
    <flux:heading size="lg" level="2">Servers</flux:heading>

    <div class="mt-3">
        @if ($error)
            <div data-state="error">
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $error }}">
                    <x-slot name="actions"><flux:button size="sm" wire:click="$refresh">Try again</flux:button></x-slot>
                </flux:callout>
            </div>
        @elseif ($servers->isEmpty())
            <div data-state="empty">
                <flux:callout icon="server" heading="This customer has no servers" />
            </div>
        @else
            <div data-state="ready">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>IP addresses</flux:table.column>
                        <flux:table.column>Requested (UTC)</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($servers as $server)
                            <flux:table.row :key="$server->id">
                                <flux:table.cell><flux:link href="{{ route('servers.show', $server) }}" wire:navigate>{{ $server->name }}</flux:link></flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$server->status->color()">{{ ucfirst($server->status->value) }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $server->currentIpAssignments->map(fn ($a) => $a->ipAddress->address)->join(', ') ?: '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $server->requested_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>
</div>
