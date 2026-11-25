<?php

declare(strict_types=1);

namespace AIArmada\Authz\EntryPoints\Http;

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
        if (! $manager->leave()) {
            return redirect('/')->withErrors(['impersonation' => 'Unable to end impersonation.']);
        }

        $redirectTo = BackToUrlSanitizer::sanitize($backTo);

        return redirect($redirectTo)->with('status', 'Impersonation ended.');
    }
}
