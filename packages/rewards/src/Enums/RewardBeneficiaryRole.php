<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardBeneficiaryRole: string
{
    case Recipient = 'recipient';
    case Participant = 'participant';
    case Purchaser = 'purchaser';
    case Staff = 'staff';
    case Sharer = 'sharer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Whether this role needs attributed evidence before it can earn.
     *
     * Actor-bound roles (Participant, Purchaser, Staff) earn from facts the
     * actor performed even before the outcome is attributed to a sharer
     * identity — an organizer acting is still the organizer's act.
     * Sharer-bound roles (Recipient, Sharer) only earn when the fact is
     * attributed: a claim never converts unattributed evidence into
     * attributed credit, so late attribution stays refused for them.
     */
    public function requiresAttribution(): bool
    {
        return match ($this) {
            self::Recipient, self::Sharer => true,
            self::Participant, self::Purchaser, self::Staff => false,
        };
    }

    /**
     * The single sharer/actor binding split every gate uses: sharer-bound
     * roles bind the fact's sharer id, actor-bound roles bind its actor id,
     * and Staff additionally requires the fact's actor role to be Staff.
     */
    public function binds(?string $actorId, self $actorRole, ?string $sharerId, string $recipientId): bool
    {
        return match ($this) {
            self::Recipient, self::Sharer => $sharerId !== null && $sharerId === $recipientId,
            self::Participant, self::Purchaser => $actorId !== null && $actorId === $recipientId,
            self::Staff => $actorRole === self::Staff && $actorId !== null && $actorId === $recipientId,
        };
    }

    /**
     * The role a fact's actor side earns under: the fact's own actor role
     * when it names an actor-bound role (Participant, Purchaser, Staff),
     * otherwise Participant. A sharer-only or unroled actor side never
     * becomes a sharer-bound role.
     */
    public static function actorSide(self $actorRole): self
    {
        return match ($actorRole) {
            self::Participant, self::Purchaser, self::Staff => $actorRole,
            default => self::Participant,
        };
    }
}
