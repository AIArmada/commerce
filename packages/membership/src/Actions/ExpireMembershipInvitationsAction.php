<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class ExpireMembershipInvitationsAction
{
    use AsAction;

    public function execute(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $runner = new OwnerBatchRunner(MembershipInvitation::class, [
            'enabled' => 'membership.owner.enabled',
            'include_global' => 'membership.owner.include_global',
        ]);

        return (int) $runner->run(fn (): int => $this->expireForCurrentOwner($now));
    }

    private function expireForCurrentOwner(CarbonImmutable $now): int
    {
        $expired = 0;

        MembershipInvitation::query()
            ->where('status', InvitationStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->chunkById(100, function (Collection $invitations) use (&$expired): void {
                DB::transaction(function () use ($invitations, &$expired): void {
                    foreach ($invitations as $invitation) {
                        if (! $invitation instanceof MembershipInvitation) {
                            continue;
                        }

                        $invitation->transitionStatus(InvitationStatus::Expired);
                        $expired++;
                    }
                });
            });

        return $expired;
    }
}
