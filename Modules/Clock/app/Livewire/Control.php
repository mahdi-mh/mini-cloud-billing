<?php

namespace Modules\Clock\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Clock\Services\Clock;

class Control extends Component
{
    public function advance(int $hours, Clock $clock): void
    {
        Gate::authorize('manage-clock');

        $clock->advance(max(1, min($hours, 24 * 30)) * 3600);
    }

    public function render(Clock $clock)
    {
        return view('clock::livewire.control', ['now' => $clock->now()]);
    }
}
