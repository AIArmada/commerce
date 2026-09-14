<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;

final class HandleUserLoginAttempt
{
    /**
     * Session key holding the pre-login (guest) session id for cart migration.
     *
     * The id is stashed in the guest's OWN session on login attempt and consumed
     * from that same session on login. It is never keyed by a remotely-supplied
     * login identifier, so one session cannot plant a migration into another
     * account's login.
     */
    public const PRE_LOGIN_SESSION_KEY = 'cart.pre_login_session_id';

    public function handle(Attempting $event): void
    {
        if (Auth::check()) {
            return;
        }

        if (! app()->bound('session')) {
            return;
        }

        $currentSessionId = session()->getId();

        if (is_string($currentSessionId) && $currentSessionId !== '') {
            session()->put(self::PRE_LOGIN_SESSION_KEY, $currentSessionId);
        }
    }
}
