<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Exports;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackResponsesExport extends Exporter
{
    protected static ?string $model = FeedbackResponse::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('form_name')
                ->label('Form'),
            ExportColumn::make('subject_type')
                ->label('Subject Type'),
            ExportColumn::make('subject_id')
                ->label('Subject ID'),
            ExportColumn::make('respondent_type')
                ->label('Respondent Type')
                ->state(static fn (FeedbackResponse $record): ?string => $record->is_anonymous ? null : $record->respondent_type),
            ExportColumn::make('respondent_id')
                ->label('Respondent ID')
                ->state(static fn (FeedbackResponse $record): ?string => $record->is_anonymous ? null : $record->respondent_id),
            ExportColumn::make('status'),
            ExportColumn::make('is_anonymous')
                ->label('Anonymous'),
            ExportColumn::make('score')
                ->label('Score'),
            ExportColumn::make('submitted_at')
                ->label('Submitted At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your export has been completed.';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $query = OwnerUiScope::apply($query, includeGlobal: false);
        $forms = OwnerUiScope::apply(FeedbackForm::query(), includeGlobal: false)
            ->whereColumn('id', $query->getModel()->qualifyColumn('feedback_form_id'));

        return $query->addSelect(['form_name' => $forms->select('name')]);
    }
}
