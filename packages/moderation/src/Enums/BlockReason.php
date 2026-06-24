<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Enums;

enum BlockReason: string
{
    case Spam = 'spam';
    case AbusiveContent = 'abusive_content';
    case Harassment = 'harassment';
    case Impersonation = 'impersonation';
    case CopyrightViolation = 'copyright_violation';
    case PolicyViolation = 'policy_violation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::AbusiveContent => 'Abusive Content',
            self::Harassment => 'Harassment',
            self::Impersonation => 'Impersonation',
            self::CopyrightViolation => 'Copyright Violation',
            self::PolicyViolation => 'Policy Violation',
            self::Other => 'Other',
        };
    }
}
