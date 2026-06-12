<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackTemplateResource\Pages;

use AIArmada\FilamentFeedback\Resources\FeedbackTemplateResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbackTemplates extends ListRecords
{
    protected static string $resource = FeedbackTemplateResource::class;
}
