<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ExpireMembershipInvitationAction
{
    use AsAction;

    public function handle(MembershipInvitation $invitation): bool
    {
        return DB::transaction(static function () use ($invitation): bool {
            OwnerWriteGuard::findOrFailForOwner(MembershipInvitation::class, (string) $invitation->getKey());

            $locked = MembershipInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== InvitationStatus::Pending || ! $locked->isExpired()) {
                return false;
            }

            $locked->transitionTo(InvitationStatus::Expired)->save();

            return true;
        });
    }
}
