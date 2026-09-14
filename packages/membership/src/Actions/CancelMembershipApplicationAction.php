<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationCancelled;
use AIArmada\Membership\Models\MembershipApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class CancelMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application, ?Model $actor = null): void
    {
        $cancelledApplication = DB::transaction(function () use ($actor, $application): MembershipApplication {
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

            $attributes = [
                'status' => ApplicationStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now(),
            ];

            if ($actor instanceof Model) {
                $attributes['cancelled_by'] = $actor->getKey();
            }

            $lockedApplication->forceFill($attributes)->save();

            return $lockedApplication;
        });

        MembershipApplicationCancelled::dispatch($cancelledApplication);
    }
}
