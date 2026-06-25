<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers;

use AIArmada\Feedback\Actions\CreateFeedbackQuestionAction;
use AIArmada\Feedback\Actions\DeleteFeedbackQuestionAction;
use AIArmada\Feedback\Actions\UpdateFeedbackQuestionAction;
use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Models\FeedbackQuestion;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('label')->limit(40),
                Tables\Columns\IconColumn::make('is_required')->boolean(),
                Tables\Columns\TextColumn::make('order_column')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('key')->required(),
                        Select::make('type')->options(FeedbackQuestionType::options())->required(),
                        TextInput::make('label')->required(),
                        TextInput::make('description'),
                        TextInput::make('help_text'),
                        TextInput::make('placeholder'),
                        Toggle::make('is_required'),
                        Toggle::make('is_scored'),
                        TextInput::make('order_column')->numeric()->default(0),
                        Select::make('feedback_section_id')
                            ->label('Section')
                            ->relationship('section', 'title'),
                    ])
                    ->action(function (array $data): void {
                        app(CreateFeedbackQuestionAction::class)->execute(
                            formId: $this->getOwnerRecord()->getKey(),
                            key: $data['key'],
                            type: $data['type'],
                            label: $data['label'],
                            isRequired: (bool) ($data['is_required'] ?? false),
                            isScored: (bool) ($data['is_scored'] ?? false),
                            orderColumn: (int) ($data['order_column'] ?? 0),
                            sectionId: $data['feedback_section_id'] ?? null,
                        );
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('key')->required(),
                        Select::make('type')->options(FeedbackQuestionType::options())->required(),
                        TextInput::make('label')->required(),
                        TextInput::make('description'),
                        TextInput::make('help_text'),
                        TextInput::make('placeholder'),
                        Toggle::make('is_required'),
                        Toggle::make('is_scored'),
                        TextInput::make('order_column')->numeric(),
                        Select::make('feedback_section_id')
                            ->label('Section')
                            ->relationship('section', 'title'),
                    ])
                    ->action(function (FeedbackQuestion $record, array $data): void {
                        app(UpdateFeedbackQuestionAction::class)->execute($record, $data);
                    }),
                DeleteAction::make()
                    ->action(function (FeedbackQuestion $record): void {
                        app(DeleteFeedbackQuestionAction::class)->execute($record);
                    }),
            ]);
    }
}
