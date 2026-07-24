<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum CredentialType: string
{
    use HasLabelOptions;

    case AcademicDegree = 'academic_degree';
    case ProfessionalLicense = 'professional_license';
    case Certification = 'certification';

    public function label(): string
    {
        return match ($this) {
            self::AcademicDegree => 'Academic Degree',
            self::ProfessionalLicense => 'Professional License',
            self::Certification => 'Certification',
        };
    }
}
