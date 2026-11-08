@use('Modules\Wallet\Support\Money')

<div class="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <div>
        <flux:text size="sm">Balance</flux:text>
        <flux:heading size="xl" data-testid="balance" class="{{ $balance < 0 ? 'text-red-500' : '' }}">{{ Money::format($balance) }}</flux:heading>
    </div>

    @can('manage-money')
        <div class="flex gap-2">
            <flux:modal.trigger name="top-up"><flux:button variant="primary" icon="plus">Top up</flux:button></flux:modal.trigger>
            <flux:modal.trigger name="refund"><flux:button icon="arrow-uturn-left">Refund</flux:button></flux:modal.trigger>
        </div>

        <flux:modal name="top-up" class="md:w-96">
            <form wire:submit="topUp" class="space-y-6">
                <flux:heading size="lg">Top up the wallet</flux:heading>
                <flux:input wire:model="topUpAmount" label="Amount (USD)" placeholder="10.00" inputmode="decimal" />
                <div class="flex"><flux:spacer /><flux:button type="submit" variant="primary">Top up</flux:button></div>
            </form>
        </flux:modal>

        <flux:modal name="refund" class="md:w-96">
            <form wire:submit="refund" class="space-y-6">
                <flux:heading size="lg">Refund</flux:heading>
                <flux:input wire:model="refundAmount" label="Amount (USD)" placeholder="2.00" inputmode="decimal" />
                <div class="flex"><flux:spacer /><flux:button type="submit" variant="primary">Refund</flux:button></div>
            </form>
        </flux:modal>
    @endcan
</div>
