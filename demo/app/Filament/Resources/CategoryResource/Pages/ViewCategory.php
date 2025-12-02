<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;
}
