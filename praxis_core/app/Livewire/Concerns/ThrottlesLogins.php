<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login throttling for the Livewire sign-in forms (HQ, client, task force).
 *
 * 5 failed attempts for one email from one IP lock that pair for 15 minutes.
 * The component needs a public $email property.
 */
trait ThrottlesLogins
{
    protected function loginThrottleKey(string $scope): string
    {
        return 'login:' . $scope . ':' . Str::lower((string) $this->email) . '|' . request()->ip();
    }

    protected function tooManyLoginAttempts(string $scope, string $errorField = 'email'): bool
    {
        $key = $this->loginThrottleKey($scope);
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }

        $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
        $this->addError($errorField, "Too many sign-in attempts. Please try again in {$minutes} minute(s).");
        return true;
    }

    protected function recordFailedLogin(string $scope): void
    {
        RateLimiter::hit($this->loginThrottleKey($scope), 900);
    }

    protected function clearLoginAttempts(string $scope): void
    {
        RateLimiter::clear($this->loginThrottleKey($scope));
    }
}
