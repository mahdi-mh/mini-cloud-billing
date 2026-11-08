@use('Modules\Wallet\Support\Money')

<div>
    <flux:heading size="lg" level="2">Money history</flux:heading>

    <div class="mt-3">
        @if ($error)
            <div data-state="error">
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $error }}">
                    <x-slot name="actions"><flux:button size="sm" wire:click="$refresh">Try again</flux:button></x-slot>
                </flux:callout>
            </div>
        @elseif ($entries->isEmpty())
            <div data-state="empty">
                <flux:callout icon="information-circle" heading="No money has moved yet" text="Top-ups, charges and refunds show up here." />
            </div>
        @else
            <div data-state="ready">
                <flux:table :paginate="$entries">
                    <flux:table.columns>
                        <flux:table.column>When (UTC)</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column>Description</flux:table.column>
                        <flux:table.column align="end">Amount</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($entries as $entry)
                            <flux:table.row :key="$entry->id">
                                <flux:table.cell>{{ $entry->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="match ($entry->type->value) { 'topup' => 'green', 'refund' => 'blue', default => 'zinc' }">{{ ucfirst($entry->type->value) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $entry->description }}</flux:table.cell>
                                <flux:table.cell align="end" class="{{ $entry->amount < 0 ? 'text-red-500' : 'text-green-600' }}">{{ Money::format($entry->amount) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>
</div>
