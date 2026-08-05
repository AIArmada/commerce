<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
