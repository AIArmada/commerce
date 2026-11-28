<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackTemplateStatus;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class FeedbackTemplateResource extends Resource
{
    protected static ?string $model = FeedbackTemplate::class;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-feedback.resources.navigation_sort.feedback_template');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-duplicate';
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(
            parent::getEloquentQuery(),
            includeGlobal: (bool) config('feedback.owner.include_global_templates', false),
        );
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
                Tables\Columns\TextColumn::make('category')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackTemplateStatus | string $state): string => $state instanceof FeedbackTemplateStatus ? $state->label() : $state)
                    ->color(fn (FeedbackTemplateStatus | string $state): string => match ($state instanceof FeedbackTemplateStatus ? $state->value : $state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('purpose')
                    ->options(FeedbackFormPurpose::options()),
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackTemplateStatus::options()),
            ])
            ->actions([
                Action::make('create_form')
                    ->label('Create Form From Template')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->action(function (FeedbackTemplate $record): void {
                        app(CreateFeedbackFormFromTemplateAction::class)->execute($record);
                    })
                    ->authorize(fn (): bool => Gate::allows('create', FeedbackForm::class))
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FeedbackTemplateResource\Pages\ListFeedbackTemplates::route('/'),
        ];
    }
}
