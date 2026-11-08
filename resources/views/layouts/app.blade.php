<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }} · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.header>
                <flux:sidebar.brand href="{{ route('servers.index') }}" name="{{ config('app.name') }}" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="server" href="{{ route('servers.index') }}" :current="request()->routeIs('servers.*')" wire:navigate>Servers</flux:sidebar.item>
                <flux:sidebar.item icon="users" href="{{ route('customers.index') }}" :current="request()->routeIs('customers.*')" wire:navigate>Customers</flux:sidebar.item>
                <flux:sidebar.item icon="globe-alt" href="{{ route('ips.index') }}" :current="request()->routeIs('ips.*')" wire:navigate>IP addresses</flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <livewire:clock.control />

            <flux:separator class="my-3" />

            <div class="px-2 pb-2">
                <flux:text class="font-medium">{{ auth()->user()->name }}</flux:text>
                <flux:text size="sm">{{ auth()->user()->role->label() }}</flux:text>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <flux:button type="submit" size="sm" variant="ghost" icon="arrow-right-start-on-rectangle">Sign out</flux:button>
                </form>
            </div>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>

        @fluxScripts
    </body>
</html>
