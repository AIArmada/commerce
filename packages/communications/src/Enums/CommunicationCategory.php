<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum CommunicationCategory: string
{
    case Transactional = 'transactional';
    case Operational = 'operational';
    case Marketing = 'marketing';
    case Security = 'security';
    case Legal = 'legal';
    case Support = 'support';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Transactional => 'Transactional',
            self::Operational => 'Operational',
            self::Marketing => 'Marketing',
            self::Security => 'Security',
            self::Legal => 'Legal',
            self::Support => 'Support',
            self::Internal => 'Internal',
        };
    }
}
