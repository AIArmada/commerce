<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipApplicationApproved;
use AIArmada\Membership\Models\MembershipApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ApproveMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application, Model $reviewer, MemberRole $role, ?string $note = null): void
    {
        $note = $this->normalizeNote($note);

        $approvedApplication = DB::transaction(function () use ($application, $note, $reviewer, $role): MembershipApplication {
            $guardedApplication = OwnerWriteGuard::findOrFailForOwner(
                MembershipApplication::class,
                (string) $application->getKey(),
            );

            $lockedApplication = MembershipApplication::query()
                ->lockForUpdate()
                ->whereKey($guardedApplication->getKey())
                ->firstOrFail();

            if ($lockedApplication->status !== ApplicationStatus::Pending) {
                throw new RuntimeException('Only pending membership applications can be approved.');
            }

            $applicant = $lockedApplication->applicant;
            $subject = $lockedApplication->subject;

            if (! $applicant instanceof Model) {
                throw new RuntimeException('Cannot approve a membership application whose applicant no longer exists.');
            }

            if (! $subject instanceof Model) {
                throw new RuntimeException('Cannot approve a membership application whose subject no longer exists.');
            }

            $lockedApplication->forceFill([
                'status' => ApplicationStatus::Approved,
                'granted_role' => $role->spatieRoleName(),
                'reviewer_id' => $reviewer->getKey(),
                'reviewer_note' => $note,
                'reviewed_at' => CarbonImmutable::now(),
            ])->save();

            AddMemberAction::make()->handle(
                $subject,
                $applicant,
                $role,
            );

            return $lockedApplication;
        });

        MembershipApplicationApproved::dispatch($approvedApplication);

        if (app()->bound(MembershipApplicationNotifier::class)) {
            app(MembershipApplicationNotifier::class)->notifyApproved($approvedApplication);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = mb_trim($note);

        if (mb_strlen($note) > 5000) {
            throw new InvalidArgumentException('The reviewer note must not exceed 5000 characters.');
        }

        return $note;
    }
}
