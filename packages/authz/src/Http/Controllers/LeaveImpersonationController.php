<?php

declare(strict_types=1);

namespace AIArmada\Authz\Http\Controllers;

use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Support\BackToUrlSanitizer;
use Illuminate\Http\RedirectResponse;

class LeaveImpersonationController
{
    public function __invoke(ImpersonateManager $manager): RedirectResponse
    {
        if (! $manager->isImpersonating()) {
            return redirect('/');
        }

        $backTo = $manager->getBackTo();
        $manager->leave();

        // Always redirect back to origin panel where impersonation began
        $redirectTo = self::sanitizeBackToUrl($backTo);

        return redirect($redirectTo)->with('status', 'Impersonation ended.');
    }

    private static function sanitizeBackToUrl(?string $url): string
    {
        return BackToUrlSanitizer::sanitize($url);
    }
}
