<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Exports;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use BackedEnum;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackTestimonialsExport extends Exporter
{
    protected static ?string $model = FeedbackTestimonial::class;

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
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state): string => $state instanceof BackedEnum ? (string) $state->value : (string) $state),
            ExportColumn::make('published_at')
                ->label('Published At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your export has been completed.';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return OwnerUiScope::apply($query, includeGlobal: false);
    }
}
