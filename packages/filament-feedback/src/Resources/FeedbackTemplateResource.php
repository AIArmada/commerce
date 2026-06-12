<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackTemplateStatus;
use AIArmada\Feedback\Models\FeedbackTemplate;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackTemplateResource extends Resource
{
    protected static ?string $model = FeedbackTemplate::class;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group', 'Feedback');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-duplicate';
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->badge(),
                Tables\Columns\TextColumn::make('category')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
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
            ->headerActions([
                Action::make('create_form')
                    ->label('Create Form From Template')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->action(function (FeedbackTemplate $record): void {
                        app(CreateFeedbackFormFromTemplateAction::class)->execute($record);
                    })
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
