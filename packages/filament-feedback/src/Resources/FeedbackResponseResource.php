<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Actions\MarkFeedbackResponseAsSpamAction;
use AIArmada\Feedback\Actions\RejectFeedbackResponseAction;
use AIArmada\Feedback\Actions\ReviewFeedbackResponseAction;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackResponseResource extends Resource
{
    protected static ?string $model = FeedbackResponse::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group', 'Feedback');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.name')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type'),
                Tables\Columns\TextColumn::make('respondent_type')
                    ->label('Respondent')
                    ->visible(fn (): bool => true),
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
                Tables\Columns\IconColumn::make('is_anonymous')
                    ->boolean(),
                Tables\Columns\TextColumn::make('score')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackResponseStatus::options()),
                Tables\Filters\SelectFilter::make('is_anonymous')
                    ->label('Anonymous')
                    ->options([
                        '0' => 'Identified',
                        '1' => 'Anonymous',
                    ]),
                Tables\Filters\Filter::make('submitted_at')
                    ->form([
                        Tables\Filters\Indicator::make('submitted_at'),
                    ]),
            ])
            ->headerActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (FeedbackResponse $record): void {
                        app(ReviewFeedbackResponseAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackResponse $record): bool => $record->status === FeedbackResponseStatus::Submitted)
                    ->requiresConfirmation(),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (FeedbackResponse $record): void {
                        app(RejectFeedbackResponseAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackResponse $record): bool => $record->status === FeedbackResponseStatus::Submitted)
                    ->requiresConfirmation(),
                Action::make('mark_spam')
                    ->label('Mark Spam')
                    ->icon('heroicon-o-flag')
                    ->color('warning')
                    ->action(function (FeedbackResponse $record): void {
                        app(MarkFeedbackResponseAsSpamAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackResponse $record): bool => $record->status !== FeedbackResponseStatus::Spam)
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FeedbackResponseResource\Pages\ListFeedbackResponses::route('/'),
        ];
    }
}
