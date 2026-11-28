<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Resources\FeedbackResponseResource\Pages;

use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Resources\FeedbackResponseResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbackResponses extends ListRecords
{
    protected static string $resource = FeedbackResponseResource::class;
}
