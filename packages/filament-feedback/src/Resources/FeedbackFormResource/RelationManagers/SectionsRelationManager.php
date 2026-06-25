<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers;

use AIArmada\Feedback\Actions\CreateFeedbackSectionAction;
use AIArmada\Feedback\Actions\DeleteFeedbackSectionAction;
use AIArmada\Feedback\Actions\UpdateFeedbackSectionAction;
use AIArmada\Feedback\Models\FeedbackSection;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('key'),
                Tables\Columns\TextColumn::make('order_column')->sortable(),
                Tables\Columns\TextColumn::make('questions_count')->counts('questions'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('title')->required(),
                        TextInput::make('key'),
                        TextInput::make('order_column')->numeric()->default(0),
                    ])
                    ->action(function (array $data): void {
                        app(CreateFeedbackSectionAction::class)->execute(
                            formId: $this->getOwnerRecord()->getKey(),
                            title: $data['title'],
                            key: $data['key'] ?? null,
                            orderColumn: (int) ($data['order_column'] ?? 0),
                        );
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('title')->required(),
                        TextInput::make('key'),
                        TextInput::make('order_column')->numeric(),
                    ])
                    ->action(function (FeedbackSection $record, array $data): void {
                        app(UpdateFeedbackSectionAction::class)->execute($record, $data);
                    }),
                DeleteAction::make()
                    ->action(function (FeedbackSection $record): void {
                        app(DeleteFeedbackSectionAction::class)->execute($record);
                    }),
            ]);
    }
}
