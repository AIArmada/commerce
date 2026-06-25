<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Actions\ApproveFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\HideFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\PublishFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\RejectFeedbackTestimonialAction;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackTestimonialResource extends Resource
{
    protected static ?string $model = FeedbackTestimonial::class;

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-star';
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quote')
                    ->limit(80),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('permission_given_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackTestimonialStatus::options()),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(ApproveFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Pending)
                    ->requiresConfirmation(),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(RejectFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Pending)
                    ->requiresConfirmation(),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(PublishFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Approved)
                    ->requiresConfirmation(),
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(HideFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Published)
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FeedbackTestimonialResource\Pages\ListFeedbackTestimonials::route('/'),
        ];
    }
}
