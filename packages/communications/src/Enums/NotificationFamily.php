<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum NotificationFamily: string
{
    case EventReminder = 'event_reminder';
    case EventUpdate = 'event_update';
    case EventCancellation = 'event_cancellation';
    case EventRescheduled = 'event_rescheduled';
    case EventPromotion = 'event_promotion';
    case RegistrationConfirmation = 'registration_confirmation';
    case RegistrationReminder = 'registration_reminder';
    case RegistrationChanged = 'registration_changed';
    case RegistrationCancelled = 'registration_cancelled';
    case CheckInAvailable = 'check_in_available';
    case TicketIssued = 'ticket_issued';
    case PaymentReceived = 'payment_received';
    case PaymentFailed = 'payment_failed';
    case RefundProcessed = 'refund_processed';
    case SpeakerInvitation = 'speaker_invitation';
    case SpeakerConfirmation = 'speaker_confirmation';
    case SpeakerChange = 'speaker_change';
    case InstitutionInvitation = 'institution_invitation';
    case InstitutionClaim = 'institution_claim';
    case MembershipUpdate = 'membership_update';
    case FollowUp = 'follow_up';
    case SystemAnnouncement = 'system_announcement';
    case MaintenanceNotice = 'maintenance_notice';
    case PolicyUpdate = 'policy_update';
    case SecurityAlert = 'security_alert';
    case AccountUpdate = 'account_update';
    case PasswordChanged = 'password_changed';
    case EmailVerified = 'email_verified';
    case TwoFactorDisabled = 'two_factor_disabled';
    case NewDeviceLogin = 'new_device_login';
    case WelcomeMessage = 'welcome_message';
    case OnboardingStep = 'onboarding_step';
    case AchievementUnlocked = 'achievement_unlocked';
    case MilestoneReached = 'milestone_reached';
    case Recommendation = 'recommendation';
    case DigestDaily = 'digest_daily';
    case DigestWeekly = 'digest_weekly';

    public function label(): string
    {
        return match ($this) {
            self::EventReminder => 'Event Reminder',
            self::EventUpdate => 'Event Update',
            self::EventCancellation => 'Event Cancellation',
            self::EventRescheduled => 'Event Rescheduled',
            self::EventPromotion => 'Event Promotion',
            self::RegistrationConfirmation => 'Registration Confirmation',
            self::RegistrationReminder => 'Registration Reminder',
            self::RegistrationChanged => 'Registration Changed',
            self::RegistrationCancelled => 'Registration Cancelled',
            self::CheckInAvailable => 'Check-In Available',
            self::TicketIssued => 'Ticket Issued',
            self::PaymentReceived => 'Payment Received',
            self::PaymentFailed => 'Payment Failed',
            self::RefundProcessed => 'Refund Processed',
            self::SpeakerInvitation => 'Speaker Invitation',
            self::SpeakerConfirmation => 'Speaker Confirmation',
            self::SpeakerChange => 'Speaker Change',
            self::InstitutionInvitation => 'Institution Invitation',
            self::InstitutionClaim => 'Institution Claim',
            self::MembershipUpdate => 'Membership Update',
            self::FollowUp => 'Follow Up',
            self::SystemAnnouncement => 'System Announcement',
            self::MaintenanceNotice => 'Maintenance Notice',
            self::PolicyUpdate => 'Policy Update',
            self::SecurityAlert => 'Security Alert',
            self::AccountUpdate => 'Account Update',
            self::PasswordChanged => 'Password Changed',
            self::EmailVerified => 'Email Verified',
            self::TwoFactorDisabled => 'Two-Factor Disabled',
            self::NewDeviceLogin => 'New Device Login',
            self::WelcomeMessage => 'Welcome Message',
            self::OnboardingStep => 'Onboarding Step',
            self::AchievementUnlocked => 'Achievement Unlocked',
            self::MilestoneReached => 'Milestone Reached',
            self::Recommendation => 'Recommendation',
            self::DigestDaily => 'Daily Digest',
            self::DigestWeekly => 'Weekly Digest',
        };
    }
}
