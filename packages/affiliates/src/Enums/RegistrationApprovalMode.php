<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Pending;

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

    /**
     * @return class-string<AffiliateStatus>
     */
    public function defaultStatus(): string
    {
        return match ($this) {
            self::Auto => Active::class,
            self::Open => Pending::class,
            self::Admin => Pending::class,
        };
    }
}
