<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class FeedbackFormTestimonials extends Page implements HasTable
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

        if (Gate::getPolicyFor(FeedbackTestimonial::class) !== null) {
            Gate::authorize('viewAny', FeedbackTestimonial::class);
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
        $responseIds = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false)
            ->where('feedback_form_id', $this->getRecord()->getKey())->select('id');
        $answerIds = OwnerUiScope::apply(FeedbackAnswer::query(), includeGlobal: false)
            ->whereIn('feedback_response_id', clone $responseIds)->select('id');

        return $table
            ->query(OwnerUiScope::apply(FeedbackTestimonial::query(), includeGlobal: false)
                ->where(fn (Builder $query): Builder => $query->whereIn('feedback_response_id', $responseIds)
                    ->orWhereIn('feedback_answer_id', $answerIds)))
            ->columns([
                Tables\Columns\TextColumn::make('display_name'),
                Tables\Columns\TextColumn::make('quote')->limit(60),
                Tables\Columns\TextColumn::make('rating')->numeric(2),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (FeedbackTestimonialStatus $state): string => match ($state->value) {
                        'pending' => 'gray',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackTestimonialStatus::options()),
            ]);
    }
}
