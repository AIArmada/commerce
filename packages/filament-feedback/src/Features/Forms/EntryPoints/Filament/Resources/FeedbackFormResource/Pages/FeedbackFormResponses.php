<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class FeedbackFormResponses extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;
    use VerifiesRecordOwnerContext;

    protected static string $resource = FeedbackFormResource::class;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);

        if (Gate::getPolicyFor(FeedbackResponse::class) !== null) {
            Gate::authorize('viewAny', FeedbackResponse::class);
        }
    }

    protected function failOwnerGuard(): void
    {
        abort(403);
    }

    public function getView(): string
    {
        return 'filament-feedback::pages.feedback-form-records';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false)
                ->where('feedback_form_id', $this->getRecord()->getKey()))
            ->columns([
                Tables\Columns\TextColumn::make('respondent_type')
                    ->state(fn (FeedbackResponse $record): ?string => $record->is_anonymous ? null : $record->respondent_type),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (FeedbackResponseStatus $state): string => match ($state->value) {
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
