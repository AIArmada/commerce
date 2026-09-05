<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationCancelled;
use AIArmada\Membership\Models\MembershipApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class CancelMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application): void
    {
        $cancelledApplication = DB::transaction(function () use ($application): MembershipApplication {
            $guardedApplication = OwnerWriteGuard::findOrFailForOwner(
                MembershipApplication::class,
                (string) $application->getKey(),
            );

            $lockedApplication = MembershipApplication::query()
                ->lockForUpdate()
                ->whereKey($guardedApplication->getKey())
                ->firstOrFail();

            if ($lockedApplication->status !== ApplicationStatus::Pending) {
                throw new RuntimeException('Only pending membership applications can be cancelled.');
            }

            $lockedApplication->update([
                'status' => ApplicationStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now(),
            ]);

            return $lockedApplication;
        });

        MembershipApplicationCancelled::dispatch($cancelledApplication);
    }
}
