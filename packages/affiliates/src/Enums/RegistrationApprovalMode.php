<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum RegistrationApprovalMode: string
{
    case Auto = 'auto';
    case Open = 'open';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto Approve',
            self::Open => 'Open Registration',
            self::Admin => 'Admin Approval Required',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Auto => 'Affiliates are automatically approved and activated upon registration.',
            self::Open => 'Affiliates can register freely but start in pending status.',
            self::Admin => 'Affiliates must be manually approved by an administrator.',
        };
    }

    public function defaultStatus(): AffiliateStatus
    {
        return match ($this) {
            self::Auto => AffiliateStatus::Active,
            self::Open => AffiliateStatus::Pending,
            self::Admin => AffiliateStatus::Pending,
        };
    }
}
