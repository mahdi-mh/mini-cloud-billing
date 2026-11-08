<form wire:submit="login" class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Mini Cloud billing desk</flux:heading>
        <flux:text class="mt-1">Sign in with your staff account.</flux:text>
    </div>

    <flux:input wire:model="email" label="Email" type="email" autofocus autocomplete="email" />
    <flux:input wire:model="password" label="Password" type="password" autocomplete="current-password" />

    <flux:button type="submit" variant="primary" class="w-full">Sign in</flux:button>
</form>
