<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackTestimonialResource\Pages;

use AIArmada\FilamentFeedback\Resources\FeedbackTestimonialResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbackTestimonials extends ListRecords
{
    protected static string $resource = FeedbackTestimonialResource::class;
}
