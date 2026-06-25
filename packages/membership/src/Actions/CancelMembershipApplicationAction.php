<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationCancelled;
use AIArmada\Membership\Models\MembershipApplication;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class CancelMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application): void
    {
        $cancelledApplication = DB::transaction(function () use ($application): MembershipApplication {
            $lockedApplication = MembershipApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($lockedApplication->status !== ApplicationStatus::Pending) {
                throw new RuntimeException('Only pending membership applications can be cancelled.');
            }

            $lockedApplication->update([
                'status' => ApplicationStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $lockedApplication;
        });

        MembershipApplicationCancelled::dispatch($cancelledApplication);
    }
}
