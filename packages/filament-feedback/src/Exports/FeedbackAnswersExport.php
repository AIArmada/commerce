<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Exports;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackAnswer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackAnswersExport extends Exporter
{
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('response.form.name')
                ->label('Form'),
            ExportColumn::make('question.key')
                ->label('Question Key'),
            ExportColumn::make('question.label')
                ->label('Question'),
            ExportColumn::make('text_value')
                ->label('Text Answer'),
            ExportColumn::make('number_value')
                ->label('Number Answer'),
            ExportColumn::make('boolean_value')
                ->label('Boolean Answer'),
            ExportColumn::make('score')
                ->label('Score'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your export has been completed.';
    }

    public function query(): Builder
    {
        return OwnerUiScope::apply(FeedbackAnswer::query(), includeGlobal: false);
    }
}
