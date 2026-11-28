<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Actions\MarkFeedbackResponseAsSpamAction;
use AIArmada\Feedback\Features\Responses\Actions\RejectFeedbackResponseAction;
use AIArmada\Feedback\Features\Responses\Actions\ReviewFeedbackResponseAction;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Exports\FeedbackResponsesExport;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class FeedbackResponseResource extends Resource
{
    protected static ?string $model = FeedbackResponse::class;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-feedback.resources.navigation_sort.feedback_response');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
        $forms = OwnerUiScope::apply(FeedbackForm::query(), includeGlobal: false)
            ->whereColumn('id', $query->getModel()->qualifyColumn('feedback_form_id'));

        return $query->addSelect(['form_name' => $forms->select('name')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form_name')
                    ->label('Form')
                    ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereIn(
                        $query->getModel()->qualifyColumn('feedback_form_id'),
                        OwnerUiScope::apply(FeedbackForm::query(), includeGlobal: false)->select('id')->where('name', 'like', '%' . $search . '%'),
                    )),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type'),
                Tables\Columns\TextColumn::make('respondent_type')
                    ->label('Respondent')
                    ->state(fn (FeedbackResponse $record): ?string => $record->is_anonymous ? null : $record->respondent_type),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackResponseStatus | string $state): string => $state instanceof FeedbackResponseStatus ? $state->label() : $state)
                    ->color(fn (FeedbackResponseStatus | string $state): string => match ($state instanceof FeedbackResponseStatus ? $state->value : $state) {
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
                Filter::make('submitted_at')
                    ->label('Submitted At')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('submitted_at', '>=', (string) $data['from'])
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('submitted_at', '<=', (string) $data['until'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'From ' . CarbonImmutable::parse((string) $data['from'])->toFormattedDateString();
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = 'Until ' . CarbonImmutable::parse((string) $data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (FeedbackResponse $record): void {
                        app(ReviewFeedbackResponseAction::class)->execute($record);
                    })
                    ->authorize('review')
                    ->visible(fn (FeedbackResponse $record): bool => $record->status === FeedbackResponseStatus::Submitted)
                    ->requiresConfirmation(),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (FeedbackResponse $record): void {
                        app(RejectFeedbackResponseAction::class)->execute($record);
                    })
                    ->authorize('reject')
                    ->visible(fn (FeedbackResponse $record): bool => $record->status === FeedbackResponseStatus::Submitted)
                    ->requiresConfirmation(),
                Action::make('mark_spam')
                    ->label('Mark Spam')
                    ->icon('heroicon-o-flag')
                    ->color('warning')
                    ->action(function (FeedbackResponse $record): void {
                        app(MarkFeedbackResponseAsSpamAction::class)->execute($record);
                    })
                    ->authorize('markSpam')
                    ->visible(fn (FeedbackResponse $record): bool => $record->status !== FeedbackResponseStatus::Spam)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(FeedbackResponsesExport::class)
                    ->authorize(fn (): bool => Gate::allows('export', FeedbackResponse::class)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FeedbackResponseResource\Pages\ListFeedbackResponses::route('/'),
        ];
    }
}
