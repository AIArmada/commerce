<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventApprovalRequest;
use AIArmada\Events\Models\EventSubmission;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;
use UnitEnum;

final class ApprovalQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $title = 'Approval Queue';

    protected static ?string $slug = 'events/approvals';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EventApprovalRequest::query()
                    ->whereHasMorph(
                        'approvable',
                        ['event_submission'],
                        fn (Builder $query): Builder => $query->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false)),
                    )
                    ->with('approvable')
            )
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
                    ->options(['event_submission' => 'Event Submission']),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->form([
                        Textarea::make('notes')->label('Approval Notes'),
                    ])
                    ->action(function (array $data, EventApprovalRequest $record): void {
                        $this->resolveSubmission($record);

                        $record->update([
                            'status' => 'approved',
                            'approved_at' => CarbonImmutable::now(),
                            'notes' => $data['notes'] ?? null,
                        ]);
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
                    ->action(function (array $data, EventApprovalRequest $record): void {
                        $this->resolveSubmission($record);

                        $record->update([
                            'status' => 'rejected',
                            'rejected_at' => CarbonImmutable::now(),
                            'reason' => $data['reason'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                    })
                    ->visible(fn (EventApprovalRequest $record) => $record->status === 'pending'),
                Action::make('assignToMe')
                    ->label('Assign to Me')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->action(function (EventApprovalRequest $record): void {
                        $this->resolveSubmission($record);

                        $user = Auth::user();

                        if (! $user instanceof Model) {
                            throw new RuntimeException('Assigning approval requests requires an authenticated user model.');
                        }

                        $record->update([
                            'assigned_to_type' => $user->getMorphClass(),
                            'assigned_to_id' => $user->getKey(),
                        ]);
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

        OwnerWriteGuard::findOrFailForOwner(Event::class, $submission->event_id);

        return $submission;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
