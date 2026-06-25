<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipApplicationApproved;
use AIArmada\Membership\Models\MembershipApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ApproveMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application, Model $reviewer, MemberRole $role, ?string $note = null): void
    {
        if ($application->status !== ApplicationStatus::Pending) {
            throw new RuntimeException('Only pending membership applications can be approved.');
        }

        DB::transaction(function () use ($application, $note, $reviewer, $role): void {
            $application->update([
                'status' => ApplicationStatus::Approved,
                'granted_role' => $role->spatieRoleName(),
                'reviewer_id' => $reviewer->getKey(),
                'reviewer_note' => $note,
                'reviewed_at' => now(),
            ]);

            AddMemberAction::make()->handle(
                $application->subject,
                $application->applicant,
                $role,
            );
        });

        $application->refresh();

        MembershipApplicationApproved::dispatch($application);

        if (app()->bound(MembershipApplicationNotifier::class)) {
            app(MembershipApplicationNotifier::class)->notifyApproved($application);
        }

        if (app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberAdded(
                $application->subject,
                $application->applicant,
                $role,
            );
        }
    }
}
