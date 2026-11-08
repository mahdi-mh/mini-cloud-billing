@use('Modules\Wallet\Support\Money')

<div>
    <flux:heading size="lg" level="2">Billing history</flux:heading>

    <div class="mt-3">
        @if ($error)
            <div data-state="error">
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $error }}">
                    <x-slot name="actions"><flux:button size="sm" wire:click="$refresh">Try again</flux:button></x-slot>
                </flux:callout>
            </div>
        @elseif ($charges->isEmpty())
            <div data-state="empty">
                <flux:callout icon="information-circle" heading="Nothing charged yet" text="Hours are charged once the server is running." />
            </div>
        @else
            <div data-state="ready">
                <flux:table :paginate="$charges">
                    <flux:table.columns>
                        <flux:table.column>Hour (UTC)</flux:table.column>
                        <flux:table.column>For</flux:table.column>
                        <flux:table.column>Charged to</flux:table.column>
                        <flux:table.column align="end">Amount</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($charges as $charge)
                            <flux:table.row :key="$charge->id">
                                <flux:table.cell>{{ $charge->hour_start->format('Y-m-d H:00') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($charge->billable_type->value === 'ip')
                                        IP <span class="font-mono">{{ $charge->ipAddress->address }}</span>
                                    @else
                                        Server
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:link href="{{ route('customers.show', $charge->customer_id) }}" wire:navigate>{{ $charge->customer->name }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ Money::format($charge->amount) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>
</div>
