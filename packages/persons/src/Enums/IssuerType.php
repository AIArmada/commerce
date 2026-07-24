<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum IssuerType: string
{
    use HasLabelOptions;

    case Government = 'government';
    case Royal = 'royal';
    case ReligiousBody = 'religious_body';
    case University = 'university';
    case ProfessionalBoard = 'professional_board';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::Royal => 'Royal',
            self::ReligiousBody => 'Religious Body',
            self::University => 'University',
            self::ProfessionalBoard => 'Professional Board',
            self::Organization => 'Organization',
        };
    }
}
