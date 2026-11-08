<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Sign in')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public function login()
    {
        $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', 'Too many attempts, try again in '.RateLimiter::availableIn($throttleKey).' seconds.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($throttleKey);
            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        return $this->redirectIntended(route('servers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
