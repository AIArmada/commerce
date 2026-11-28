<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Features\Testimonials\Actions\ApproveFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Actions\HideFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Actions\PublishFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Actions\RejectFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Exports\FeedbackTestimonialsExport;
use Filament\Actions\Action;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class FeedbackTestimonialResource extends Resource
{
    protected static ?string $model = FeedbackTestimonial::class;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-feedback.resources.navigation_sort.feedback_testimonial');

        return is_numeric($sort) ? (int) $sort : null;
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
                    ->formatStateUsing(fn (FeedbackTestimonialStatus | string $state): string => $state instanceof FeedbackTestimonialStatus ? $state->label() : $state)
                    ->color(fn (FeedbackTestimonialStatus | string $state): string => match ($state instanceof FeedbackTestimonialStatus ? $state->value : $state) {
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
                    ->authorize('approve')
                    ->visible(fn (FeedbackTestimonial $record): bool => in_array($record->status, [FeedbackTestimonialStatus::Pending, FeedbackTestimonialStatus::Hidden], true))
                    ->requiresConfirmation(),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(RejectFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->authorize('reject')
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Pending)
                    ->requiresConfirmation(),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(PublishFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->authorize('publish')
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Approved && $record->permission_given_at !== null)
                    ->requiresConfirmation(),
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->action(function (FeedbackTestimonial $record): void {
                        app(HideFeedbackTestimonialAction::class)->execute($record);
                    })
                    ->authorize('hide')
                    ->visible(fn (FeedbackTestimonial $record): bool => $record->status === FeedbackTestimonialStatus::Published)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(FeedbackTestimonialsExport::class)
                    ->authorize(fn (): bool => Gate::allows('export', FeedbackTestimonial::class)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FeedbackTestimonialResource\Pages\ListFeedbackTestimonials::route('/'),
        ];
    }
}
