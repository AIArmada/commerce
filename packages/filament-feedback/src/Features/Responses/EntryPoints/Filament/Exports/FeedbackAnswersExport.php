<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Exports;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackAnswersExport extends Exporter
{
    protected static ?string $model = FeedbackAnswer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('form_name')
                ->label('Form'),
            ExportColumn::make('question_key')
                ->label('Question Key'),
            ExportColumn::make('question_label')
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

    public static function modifyQuery(Builder $query): Builder
    {
        $query = OwnerUiScope::apply($query, includeGlobal: false);
        $responseForm = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false)
            ->whereColumn('id', $query->getModel()->qualifyColumn('feedback_response_id'))
            ->select('feedback_form_id');
        $forms = OwnerUiScope::apply(FeedbackForm::query(), includeGlobal: false)
            ->whereIn('id', clone $responseForm);
        $questions = OwnerUiScope::apply(FeedbackQuestion::query(), includeGlobal: false)
            ->whereColumn('id', $query->getModel()->qualifyColumn('feedback_question_id'))
            ->whereIn('feedback_form_id', clone $responseForm);

        return $query->addSelect([
            'form_name' => $forms->select('name'),
            'question_key' => (clone $questions)->select('key'),
            'question_label' => (clone $questions)->select('label'),
        ]);
    }
}
