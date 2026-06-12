<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewFeedbackForm extends ViewRecord
{
    protected static string $resource = FeedbackFormResource::class;
}
