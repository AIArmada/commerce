<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

enum BundleInclusionMode: string
{
    case Required = 'required';
    case Optional = 'optional';
}
