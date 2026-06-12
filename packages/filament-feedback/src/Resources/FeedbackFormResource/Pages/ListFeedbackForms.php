<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbackForms extends ListRecords
{
    protected static string $resource = FeedbackFormResource::class;
}
