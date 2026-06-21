<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum CommunicationEventSource: string
{
    case Application = 'application';
    case Queue = 'queue';
    case Provider = 'provider';
    case Webhook = 'webhook';
    case Tracking = 'tracking';
    case Administrator = 'administrator';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Application => 'Application',
            self::Queue => 'Queue',
            self::Provider => 'Provider',
            self::Webhook => 'Webhook',
            self::Tracking => 'Tracking',
            self::Administrator => 'Administrator',
            self::System => 'System',
        };
    }
}
