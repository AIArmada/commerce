<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationCancelled;
use AIArmada\Membership\Models\MembershipApplication;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class CancelMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application): void
    {
        if ($application->status !== ApplicationStatus::Pending) {
            throw new RuntimeException('Only pending membership applications can be cancelled.');
        }

        $application->update([
            'status' => ApplicationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        MembershipApplicationCancelled::dispatch($application);
    }
}
