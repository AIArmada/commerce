<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use AIArmada\Feedback\Features\Forms\Actions\ArchiveFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\CloseFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\PublishFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackFormResource extends Resource
{
    protected static ?string $model = FeedbackForm::class;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-feedback.resources.navigation_sort.feedback_form');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
        $responses = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false)
            ->whereColumn('feedback_form_id', $query->getModel()->qualifyColumn('id'));

        return $query->addSelect(['responses_count' => $responses->selectRaw('count(*)')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackFormPurpose | string $state): string => $state instanceof FeedbackFormPurpose ? $state->label() : (FeedbackFormPurpose::tryFrom($state)?->label() ?? $state)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackFormStatus | string $state): string => $state instanceof FeedbackFormStatus ? $state->label() : $state)
                    ->color(fn (FeedbackFormStatus | string $state): string => match ($state instanceof FeedbackFormStatus ? $state->value : $state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'closed' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackFormVisibility | string $state): string => $state instanceof FeedbackFormVisibility ? $state->label() : $state),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('Responses')
                    ->sortable(),
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
            ->actions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (FeedbackForm $record): void {
                        app(PublishFeedbackFormAction::class)->execute($record);
                    })
                    ->authorize('publish')
                    ->visible(fn (FeedbackForm $record): bool => $record->status === FeedbackFormStatus::Draft)
                    ->requiresConfirmation(),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->action(function (FeedbackForm $record): void {
                        app(CloseFeedbackFormAction::class)->execute($record);
                    })
                    ->authorize('close')
                    ->visible(fn (FeedbackForm $record): bool => $record->status === FeedbackFormStatus::Published)
                    ->requiresConfirmation(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->action(function (FeedbackForm $record): void {
                        app(ArchiveFeedbackFormAction::class)->execute($record);
                    })
                    ->authorize('archive')
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
            'responses' => FeedbackFormResource\Pages\FeedbackFormResponses::route('/{record}/responses'),
            'invitations' => FeedbackFormResource\Pages\FeedbackFormInvitations::route('/{record}/invitations'),
            'testimonials' => FeedbackFormResource\Pages\FeedbackFormTestimonials::route('/{record}/testimonials'),
        ];
    }
}
