<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Resources\FeedbackTestimonialResource\Pages;

use AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Resources\FeedbackTestimonialResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbackTestimonials extends ListRecords
{
    protected static string $resource = FeedbackTestimonialResource::class;
}
