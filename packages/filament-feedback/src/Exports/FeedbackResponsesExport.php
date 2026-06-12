<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Exports;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackResponsesExport extends Exporter
{
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('form.name')
                ->label('Form'),
            ExportColumn::make('subject_type')
                ->label('Subject Type'),
            ExportColumn::make('subject_id')
                ->label('Subject ID'),
            ExportColumn::make('respondent_type')
                ->label('Respondent Type'),
            ExportColumn::make('respondent_id')
                ->label('Respondent ID'),
            ExportColumn::make('status'),
            ExportColumn::make('is_anonymous')
                ->label('Anonymous'),
            ExportColumn::make('score')
                ->label('Score'),
            ExportColumn::make('submitted_at')
                ->label('Submitted At'),
            ExportColumn::make('ip_address')
                ->label('IP Address'),
        ];
    }

    public static function getColumnsHiddenByDefault(): array
    {
        return [
            'ip_address',
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your export has been completed.';
    }

    public function query(): Builder
    {
        $query = FeedbackResponse::query();
        $owner = OwnerContext::resolve();

        if ($owner !== null) {
            $query = OwnerQuery::applyToEloquentBuilder(
                $query,
                $owner,
                false,
            );
        }

        return $query;
    }
}
