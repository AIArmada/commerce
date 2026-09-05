<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationRejected;
use AIArmada\Membership\Models\MembershipApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class RejectMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application, Model $reviewer, ?string $note = null): void
    {
        $rejectedApplication = DB::transaction(function () use ($application, $note, $reviewer): MembershipApplication {
            $guardedApplication = OwnerWriteGuard::findOrFailForOwner(
                MembershipApplication::class,
                (string) $application->getKey(),
            );

            $lockedApplication = MembershipApplication::query()
                ->lockForUpdate()
                ->whereKey($guardedApplication->getKey())
                ->firstOrFail();

            if ($lockedApplication->status !== ApplicationStatus::Pending) {
                throw new RuntimeException('Only pending membership applications can be rejected.');
            }

            $lockedApplication->update([
                'status' => ApplicationStatus::Rejected,
                'reviewer_id' => $reviewer->getKey(),
                'reviewer_note' => $note,
                'reviewed_at' => CarbonImmutable::now(),
            ]);

            return $lockedApplication;
        });

        MembershipApplicationRejected::dispatch($rejectedApplication);

        if (app()->bound(MembershipApplicationNotifier::class)) {
            app(MembershipApplicationNotifier::class)->notifyRejected($rejectedApplication);
        }
    }
}
