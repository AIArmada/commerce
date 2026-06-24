<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum NotificationTrigger: string
{
    case EventPublished = 'event_published';
    case EventCancelled = 'event_cancelled';
    case EventRescheduled = 'event_rescheduled';
    case EventUpdated = 'event_updated';
    case RegistrationConfirmed = 'registration_confirmed';
    case RegistrationCancelled = 'registration_cancelled';
    case RegistrationChanged = 'registration_changed';
    case CheckInRecorded = 'check_in_recorded';
    case TicketPurchased = 'ticket_purchased';
    case TicketTransferred = 'ticket_transferred';
    case PaymentCompleted = 'payment_completed';
    case PaymentFailed = 'payment_failed';
    case RefundIssued = 'refund_issued';
    case SpeakerAssigned = 'speaker_assigned';
    case SpeakerConfirmed = 'speaker_confirmed';
    case SpeakerDeclined = 'speaker_declined';
    case InstitutionClaimed = 'institution_claimed';
    case MembershipGranted = 'membership_granted';
    case MembershipRevoked = 'membership_revoked';
    case FollowActivity = 'follow_activity';
    case SystemAlert = 'system_alert';
    case SecurityEvent = 'security_event';
    case AccountCreated = 'account_created';
    case EmailVerified = 'email_verified';
    case PasswordReset = 'password_reset';
    case LoginFromNewDevice = 'login_from_new_device';
    case AdminAction = 'admin_action';
    case ScheduledDispatch = 'scheduled_dispatch';
    case WebhookReceived = 'webhook_received';
    case ApiTrigger = 'api_trigger';
    case ManualDispatch = 'manual_dispatch';

    public function label(): string
    {
        return match ($this) {
            self::EventPublished => 'Event Published',
            self::EventCancelled => 'Event Cancelled',
            self::EventRescheduled => 'Event Rescheduled',
            self::EventUpdated => 'Event Updated',
            self::RegistrationConfirmed => 'Registration Confirmed',
            self::RegistrationCancelled => 'Registration Cancelled',
            self::RegistrationChanged => 'Registration Changed',
            self::CheckInRecorded => 'Check-In Recorded',
            self::TicketPurchased => 'Ticket Purchased',
            self::TicketTransferred => 'Ticket Transferred',
            self::PaymentCompleted => 'Payment Completed',
            self::PaymentFailed => 'Payment Failed',
            self::RefundIssued => 'Refund Issued',
            self::SpeakerAssigned => 'Speaker Assigned',
            self::SpeakerConfirmed => 'Speaker Confirmed',
            self::SpeakerDeclined => 'Speaker Declined',
            self::InstitutionClaimed => 'Institution Claimed',
            self::MembershipGranted => 'Membership Granted',
            self::MembershipRevoked => 'Membership Revoked',
            self::FollowActivity => 'Follow Activity',
            self::SystemAlert => 'System Alert',
            self::SecurityEvent => 'Security Event',
            self::AccountCreated => 'Account Created',
            self::EmailVerified => 'Email Verified',
            self::PasswordReset => 'Password Reset',
            self::LoginFromNewDevice => 'Login From New Device',
            self::AdminAction => 'Admin Action',
            self::ScheduledDispatch => 'Scheduled Dispatch',
            self::WebhookReceived => 'Webhook Received',
            self::ApiTrigger => 'API Trigger',
            self::ManualDispatch => 'Manual Dispatch',
        };
    }
}
