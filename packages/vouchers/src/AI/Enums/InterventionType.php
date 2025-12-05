<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Enums;

/**
 * Represents intervention types for cart recovery.
 */
enum InterventionType: string
{
    case None = 'none';
    case ExitPopup = 'exit_popup';
    case DiscountOffer = 'discount_offer';
    case RecoveryEmail = 'recovery_email';
    case PushNotification = 'push_notification';
    case Retargeting = 'retargeting';
    case LiveChat = 'live_chat';

    /**
     * Get a human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'No Intervention',
            self::ExitPopup => 'Exit Intent Popup',
            self::DiscountOffer => 'Discount Offer',
            self::RecoveryEmail => 'Recovery Email',
            self::PushNotification => 'Push Notification',
            self::Retargeting => 'Retargeting Ad',
            self::LiveChat => 'Live Chat Prompt',
        };
    }

    /**
     * Get the typical delay before this intervention.
     */
    public function getTypicalDelayMinutes(): int
    {
        return match ($this) {
            self::None => 0,
            self::ExitPopup => 0,
            self::DiscountOffer => 0,
            self::LiveChat => 5,
            self::PushNotification => 30,
            self::RecoveryEmail => 60,
            self::Retargeting => 1440,
        };
    }

    /**
     * Get the effectiveness score (1-5).
     */
    public function getEffectivenessScore(): int
    {
        return match ($this) {
            self::None => 1,
            self::ExitPopup => 4,
            self::DiscountOffer => 5,
            self::RecoveryEmail => 3,
            self::PushNotification => 2,
            self::Retargeting => 2,
            self::LiveChat => 4,
        };
    }

    /**
     * Get the cost score (1-5, higher = more expensive).
     */
    public function getCostScore(): int
    {
        return match ($this) {
            self::None => 0,
            self::ExitPopup => 1,
            self::DiscountOffer => 4,
            self::RecoveryEmail => 1,
            self::PushNotification => 1,
            self::Retargeting => 3,
            self::LiveChat => 5,
        };
    }

    /**
     * Check if this intervention requires a discount.
     */
    public function requiresDiscount(): bool
    {
        return match ($this) {
            self::DiscountOffer, self::ExitPopup, self::RecoveryEmail => true,
            default => false,
        };
    }

    /**
     * Check if this intervention is real-time (synchronous).
     */
    public function isRealTime(): bool
    {
        return match ($this) {
            self::None, self::ExitPopup, self::DiscountOffer, self::LiveChat => true,
            self::RecoveryEmail, self::PushNotification, self::Retargeting => false,
        };
    }
}
