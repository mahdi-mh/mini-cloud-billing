<div class="px-2">
    <flux:text size="sm">App clock (UTC)</flux:text>
    <flux:heading size="sm" data-testid="app-clock">{{ $now->format('Y-m-d H:i') }}</flux:heading>

    @can('manage-clock')
        <div class="mt-2 flex gap-2">
            <flux:button size="xs" wire:click="advance(1)">+1h</flux:button>
            <flux:button size="xs" wire:click="advance(24)">+1d</flux:button>
        </div>
    @endcan
</div>
