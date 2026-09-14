<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Support;

/**
 * Shared channel/provider filter options for the communications tables.
 *
 * The communications domain stores channel/provider as plain strings (no
 * enum exists yet), so the adapter keeps a single copy of the display
 * lists here until the domain publishes a canonical source.
 */
final class CommunicationFilterOptions
{
    /**
     * @return array<string, string>
     */
    public static function channels(): array
    {
        return [
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push',
            'in_app' => 'In-App',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function providers(): array
    {
        return [
            'ses' => 'SES',
            'sendgrid' => 'SendGrid',
            'twilio' => 'Twilio',
            'slack' => 'Slack',
            'fcm' => 'FCM',
            'apns' => 'APNS',
        ];
    }
}
