<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Channels\InvitationChannelManager;
use AIArmada\Membership\Data\InvitationDeliveryData;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class InviteMemberAction
{
    use AsAction;

    public function __construct(
        private readonly InvitationChannelManager $channels,
        private readonly MembershipSubjectGuard $subjectGuard,
    ) {}

    public function handle(
        Model $subject,
        string $recipient,
        MemberRole $role,
        Model $inviter,
        ?CarbonInterface $expiresAt = null,
        ?string $channel = null,
    ): MembershipInvitation {
        $this->subjectGuard->validate($subject);

        $channelName = $channel ?? $this->channels->defaultChannelName();
        $channelDriver = $this->channels->channel($channelName);
        $recipient = $channelDriver->normalize($recipient);

        if (mb_trim($recipient) === '' || mb_strlen($recipient) > 255) {
            throw new InvalidArgumentException('The normalized invitation recipient must contain 1 to 255 characters.');
        }

        $tokenLength = max(32, (int) config('membership.invitations.token_length', 64));

        if ($expiresAt !== null && CarbonImmutable::createFromInterface($expiresAt)->isPast()) {
            throw new InvalidArgumentException('The invitation expiry must be in the future.');
        }

        $token = null;

        $invitation = DB::transaction(function () use ($channelName, $expiresAt, $inviter, $recipient, $role, $subject, $tokenLength, &$token): MembershipInvitation {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $existing = $this->pendingInvitationQuery($subject, $channelName, $recipient, $role)
                ->lockForUpdate()
                ->get()
                ->first(fn (MembershipInvitation $invitation): bool => $invitation->channel === $channelName
                    && $invitation->recipient === $recipient);

            if ($existing instanceof MembershipInvitation && ! $existing->expireIfDue()) {
                return $existing;
            }

            $token = Str::random($tokenLength);
            $invitation = new MembershipInvitation;
            $invitation->fill([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'channel' => $channelName,
                'recipient' => $recipient,
                'role' => $role->spatieRoleName(),
                'invited_by' => $inviter->getKey(),
            ]);
            $invitation->issue(
                $token,
                $expiresAt ?? CarbonImmutable::now()->addDays(
                    (int) config('membership.invitations.default_expiry_days', 14)
                ),
            );
            $invitation->save();

            return $invitation;
        });

        if ($token === null) {
            return $invitation;
        }

        $delivery = new InvitationDeliveryData(
            invitationId: (string) $invitation->getKey(),
            channel: $channelName,
            recipient: $recipient,
            subjectType: (string) $invitation->subject_type,
            subjectId: (string) $invitation->subject_id,
            role: (string) $invitation->role,
            token: $token,
            expiresAt: $invitation->expires_at,
        );

        DB::afterCommit(static function () use ($channelDriver, $delivery): void {
            $channelDriver->deliver($delivery);
            MembershipInvitationSent::dispatch($delivery);
        });

        return $invitation;
    }

    /**
     * @return Builder<MembershipInvitation>
     */
    private function pendingInvitationQuery(Model $subject, string $channel, string $recipient, MemberRole $role): Builder
    {
        return MembershipInvitation::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->where('role', $role->spatieRoleName())
            ->where('status', InvitationStatus::Pending);
    }
}
