<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class FeedbackFormInvitations extends Page implements HasTable
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

        if (Gate::getPolicyFor(FeedbackInvitation::class) !== null) {
            Gate::authorize('viewAny', FeedbackInvitation::class);
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
            ->query(OwnerUiScope::apply(FeedbackInvitation::query(), includeGlobal: false)
                ->where('feedback_form_id', $this->getRecord()->getKey()))
            ->columns([
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('effective_status')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackInvitationStatus | string $state): string => $state instanceof FeedbackInvitationStatus ? $state->label() : $state)
                    ->color(fn (FeedbackInvitationStatus | string $state): string => match ($state instanceof FeedbackInvitationStatus ? $state->value : $state) {
                        'pending' => 'gray',
                        'sent' => 'info',
                        'opened' => 'warning',
                        'started' => 'warning',
                        'submitted' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sent_at')->dateTime(),
                Tables\Columns\TextColumn::make('opened_at')->dateTime(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackInvitationStatus::options()),
            ]);
    }
}
