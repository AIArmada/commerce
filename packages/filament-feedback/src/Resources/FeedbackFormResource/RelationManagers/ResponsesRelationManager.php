<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers;

use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('respondent_type'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'reviewed' => 'success',
                        'rejected' => 'danger',
                        'spam' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_anonymous')->boolean(),
                Tables\Columns\TextColumn::make('score')->numeric(2),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackResponseStatus::options()),
            ]);
    }
}
