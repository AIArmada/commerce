<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocTemplate extends CreateRecord
{
    protected static string $resource = DocTemplateResource::class;
}
