<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Exports;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackTestimonialsExport extends Exporter
{
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('display_name')
                ->label('Name'),
            ExportColumn::make('quote')
                ->label('Quote'),
            ExportColumn::make('display_title')
                ->label('Title'),
            ExportColumn::make('display_organization')
                ->label('Organization'),
            ExportColumn::make('rating')
                ->label('Rating'),
            ExportColumn::make('status'),
            ExportColumn::make('published_at')
                ->label('Published At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your export has been completed.';
    }

    public function query(): Builder
    {
        $query = FeedbackTestimonial::query();
        $owner = OwnerContext::resolve();

        if ($owner !== null) {
            $query = OwnerQuery::applyToEloquentBuilder($query, $owner, false);
        }

        return $query;
    }
}
