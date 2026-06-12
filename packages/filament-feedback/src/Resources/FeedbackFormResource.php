<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Actions\ArchiveFeedbackFormAction;
use AIArmada\Feedback\Actions\CloseFeedbackFormAction;
use AIArmada\Feedback\Actions\PublishFeedbackFormAction;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use AIArmada\Feedback\Models\FeedbackForm;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackFormResource extends Resource
{
    protected static ?string $model = FeedbackForm::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group', 'Feedback');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)
            ->withCount('responses');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'closed' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge(),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses'),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackFormStatus::options()),
                Tables\Filters\SelectFilter::make('purpose')
                    ->options(FeedbackFormPurpose::options()),
                Tables\Filters\SelectFilter::make('visibility')
                    ->options(FeedbackFormVisibility::options()),
            ])
            ->headerActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (FeedbackForm $record): void {
                        app(PublishFeedbackFormAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackForm $record): bool => $record->status === FeedbackFormStatus::Draft)
                    ->requiresConfirmation(),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->action(function (FeedbackForm $record): void {
                        app(CloseFeedbackFormAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackForm $record): bool => $record->status === FeedbackFormStatus::Published)
                    ->requiresConfirmation(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->action(function (FeedbackForm $record): void {
                        app(ArchiveFeedbackFormAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackForm $record): bool => $record->status !== FeedbackFormStatus::Archived)
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FeedbackFormResource\Pages\ListFeedbackForms::route('/'),
            'create' => FeedbackFormResource\Pages\CreateFeedbackForm::route('/create'),
            'edit' => FeedbackFormResource\Pages\EditFeedbackForm::route('/{record}/edit'),
            'view' => FeedbackFormResource\Pages\ViewFeedbackForm::route('/{record}'),
            'builder' => FeedbackFormResource\Pages\ManageFeedbackFormBuilder::route('/{record}/builder'),
            'analytics' => FeedbackFormResource\Pages\FeedbackFormAnalytics::route('/{record}/analytics'),
        ];
    }
}
