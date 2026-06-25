<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationRejected;
use AIArmada\Membership\Models\MembershipApplication;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class RejectMembershipApplicationAction
{
    use AsAction;

    public function handle(MembershipApplication $application, Model $reviewer, ?string $note = null): void
    {
        if ($application->status !== ApplicationStatus::Pending) {
            throw new RuntimeException('Only pending membership applications can be rejected.');
        }

        $application->update([
            'status' => ApplicationStatus::Rejected,
            'reviewer_id' => $reviewer->getKey(),
            'reviewer_note' => $note,
            'reviewed_at' => now(),
        ]);

        MembershipApplicationRejected::dispatch($application);

        if (app()->bound(MembershipApplicationNotifier::class)) {
            app(MembershipApplicationNotifier::class)->notifyRejected($application);
        }
    }
}
