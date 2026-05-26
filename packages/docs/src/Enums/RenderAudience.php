<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

enum RenderAudience: string
{
    case AdminPreview = 'admin_preview';
    case CustomerView = 'customer_view';
    case Pdf = 'pdf';
}
