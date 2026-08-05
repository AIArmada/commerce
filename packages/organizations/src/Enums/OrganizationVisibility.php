<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Enums;

enum OrganizationVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
