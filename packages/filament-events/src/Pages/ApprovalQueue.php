<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventApprovalWorkflow;
use AIArmada\Events\Features\Governance\Models\EventApprovalRequest;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use UnitEnum;

final class ApprovalQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $title = 'Approval Queue';

    protected static ?string $slug = 'events/approvals';

    public function table(Table $table): Table
    {
        $submissionClass = ModelResolver::submissionClass();

        return $table
            ->query(fn (): Builder => ModelResolver::approvalRequestClass()::query()
                ->whereHasMorph(
                    'approvable',
                    [$submissionClass],
                    fn (Builder $query): Builder => $query->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false)),
                )
                ->with(['approvable' => fn (MorphTo $relation) => $relation->morphWith([$submissionClass => ['event.owner']])]))
            ->columns([
                TextColumn::make('approvable_type')->badge()->label('Type'),
                TextColumn::make('approvable_id')->label('Subject ID'),
                TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending',
                    'success' => 'approved',
                    'danger' => 'rejected',
                ]),
                TextColumn::make('requested_by_type')->badge()->label('Requested By Type'),
                TextColumn::make('assigned_to_type')->badge()->label('Assigned To'),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('approved_at')->dateTime()->placeholder('—'),
                TextColumn::make('rejected_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                SelectFilter::make('approvable_type')
                    ->options([(new $submissionClass)->getMorphClass() => 'Event Submission']),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->form([
                        Textarea::make('notes')->label('Approval Notes'),
                    ])
                    ->authorize(fn (EventApprovalRequest $record): bool => $this->canModerateSubmission($record))
                    ->action(function (array $data, EventApprovalRequest $record): void {
                        app(EventApprovalWorkflow::class)->approve((string) $record->getKey(), Filament::auth()->user(), $data['notes'] ?? null);
                    })
                    ->visible(fn (EventApprovalRequest $record) => $record->status === 'pending'),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->label('Rejection Reason')->required(),
                        Textarea::make('notes')->label('Internal Notes'),
                    ])
                    ->authorize(fn (EventApprovalRequest $record): bool => $this->canModerateSubmission($record))
                    ->action(function (array $data, EventApprovalRequest $record): void {
                        app(EventApprovalWorkflow::class)->reject((string) $record->getKey(), Filament::auth()->user(), $data['reason'], $data['notes'] ?? null);
                    })
                    ->visible(fn (EventApprovalRequest $record) => $record->status === 'pending'),
                Action::make('assignToMe')
                    ->label('Assign to Me')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->authorize(fn (EventApprovalRequest $record): bool => $this->canModerateSubmission($record))
                    ->action(function (EventApprovalRequest $record): void {
                        app(EventApprovalWorkflow::class)->assignToActor((string) $record->getKey(), Filament::auth()->user());
                    })
                    ->visible(fn (EventApprovalRequest $record) => $record->status === 'pending'),
            ])
            ->defaultSort('created_at', 'asc');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    private function resolveSubmission(EventApprovalRequest $record): EventSubmission
    {
        $submission = $record->approvable;

        if (! $submission instanceof EventSubmission) {
            throw new InvalidArgumentException('Approval requests in this queue must point to an event submission.');
        }

        if ($submission->event_id === null) {
            throw new InvalidArgumentException('Event submissions in this queue must be attached to an event.');
        }

        EventWriteGuard::findOrFail($submission->event_id);

        return $submission;
    }

    private function canModerateSubmission(EventApprovalRequest $record): bool
    {
        try {
            $submission = $this->resolveSubmission($record);
        } catch (AuthorizationException | ModelNotFoundException | NoCurrentOwnerException | InvalidArgumentException) {
            return false;
        }

        $user = Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $submission);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
