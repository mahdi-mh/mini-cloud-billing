<div>
    <div class="flex items-center justify-between">
        <flux:heading size="lg" level="2">IP addresses</flux:heading>

        @can('manage-infrastructure')
            @if ($canAdd)
                <flux:button size="sm" icon="plus" wire:click="addIp">Add an address</flux:button>
            @endif
        @endcan
    </div>

    <div class="mt-3">
        @if ($current->isEmpty())
            <flux:callout icon="information-circle" heading="This server holds no address" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Address</flux:table.column>
                    <flux:table.column>Held since (UTC)</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($current as $assignment)
                        <flux:table.row :key="$assignment->id">
                            <flux:table.cell class="font-mono">{{ $assignment->ipAddress->address }}</flux:table.cell>
                            <flux:table.cell>{{ $assignment->started_at->format('Y-m-d H:i') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('manage-infrastructure')
                                    <flux:button size="xs" wire:click="startMove({{ $assignment->ip_address_id }})">Move</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    @if ($history->isNotEmpty())
        <flux:heading size="sm" class="mt-6">Held before</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Address</flux:table.column>
                <flux:table.column>From (UTC)</flux:table.column>
                <flux:table.column>Until (UTC)</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($history as $past)
                    <flux:table.row :key="'h'.$past->id">
                        <flux:table.cell class="font-mono">{{ $past->ipAddress->address }}</flux:table.cell>
                        <flux:table.cell>{{ $past->started_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $past->ended_at->format('Y-m-d H:i') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    @can('manage-infrastructure')
        <flux:modal name="move-ip" class="md:w-96">
            <form wire:submit="move" class="space-y-6">
                <div>
                    <flux:heading size="lg">Move address</flux:heading>
                    <flux:text class="mt-2">It can go to a server of any customer. Billing for the address follows it from the next hour.</flux:text>
                </div>

                <flux:select wire:model="targetServer" label="Move to" placeholder="Choose a server">
                    @foreach ($targets as $target)
                        <flux:select.option value="{{ $target->id }}">{{ $target->name }} ({{ $target->customer->name }})</flux:select.option>
                    @endforeach
                </flux:select>
                @error('targetServer') <flux:text class="text-red-500">{{ $message }}</flux:text> @enderror

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Move</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
