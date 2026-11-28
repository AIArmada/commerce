<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class CancelPendingMembershipRequestsAction
{
    public function handle(Model $subject): void
    {
        DB::transaction(function () use ($subject): void {
            $now = CarbonImmutable::now();

            MembershipApplication::query()->whereMorphedTo('subject', $subject)
                ->withoutGlobalScope(OwnerScope::class)
                ->where('status', ApplicationStatus::Pending->value)
                ->update([
                    'status' => ApplicationStatus::Cancelled->value,
                    'cancelled_at' => $now,
                ]);

            MembershipInvitation::query()->whereMorphedTo('subject', $subject)
                ->withoutGlobalScope(OwnerScope::class)
                ->where('status', InvitationStatus::Pending->value)
                ->update([
                    'status' => InvitationStatus::Revoked->value,
                    'revoked_at' => $now,
                    'last_state_change_at' => $now,
                ]);
        });
    }
}
