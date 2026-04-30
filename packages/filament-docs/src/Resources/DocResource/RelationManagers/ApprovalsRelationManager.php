<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

final class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    protected static ?string $recordTitleAttribute = 'requested_by';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('requested_by')
                    ->default(fn (): ?string => auth()->id() !== null ? (string) auth()->id() : null)
                    ->dehydrated(),

                Select::make('assigned_to')
                    ->label('Assign To')
                    ->options(function (): array {
                        return self::resolveAssignableUserOptions();
                    })
                    ->searchable()
                    ->nullable()
                    ->helperText('Leave empty to request from any approver'),

                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->helperText('Optional deadline for approval'),

                Textarea::make('comments')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('requested_by')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->searchable(),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->placeholder('Any approver'),

                TextColumn::make('comments')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->placeholder('-'),

                TextColumn::make('rejected_at')
                    ->label('Rejected')
                    ->dateTime()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Request Approval')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['requested_by'] = auth()->id() !== null ? (string) auth()->id() : null;
                        $data['status'] = 'pending';

                        if ($data['requested_by'] === null) {
                            throw ValidationException::withMessages([
                                'requested_by' => __('You must be logged in to request approval.'),
                            ]);
                        }

                        if (! empty($data['assigned_to'])) {
                            $assignableUserIds = array_keys(self::resolveAssignableUserOptions());

                            if (! in_array((string) $data['assigned_to'], array_map('strval', $assignableUserIds), true)) {
                                throw ValidationException::withMessages([
                                    'assigned_to' => __('Invalid assignee selection.'),
                                ]);
                            }
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Document')
                    ->form([
                        Textarea::make('comments')
                            ->label('Approval Comments')
                            ->maxLength(1000),
                    ])
                    ->action(function (DocApproval $record, array $data): void {
                        self::assertUserCanActOnApproval($record);

                        $doc = $record->doc;

                        if ($doc !== null) {
                            DocsOwnerScope::assertCanMutateDoc($doc);
                        }

                        $record->approve($data['comments'] ?? null);
                    })
                    ->visible(fn (DocApproval $record): bool => $record->isPending() && self::userCanActOnApproval($record)),

                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Document')
                    ->form([
                        Textarea::make('comments')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (DocApproval $record, array $data): void {
                        self::assertUserCanActOnApproval($record);

                        $doc = $record->doc;

                        if ($doc !== null) {
                            DocsOwnerScope::assertCanMutateDoc($doc);
                        }

                        $record->reject($data['comments']);
                    })
                    ->visible(fn (DocApproval $record): bool => $record->isPending() && self::userCanActOnApproval($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete_selected')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int|string, DocApproval> $records */
                            $records->each(function (DocApproval $record): void {
                                $doc = $record->doc;

                                if ($doc !== null) {
                                    DocsOwnerScope::assertCanMutateDoc($doc);
                                }

                                $record->delete();
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private static function resolveAssignableUserOptions(): array
    {
        $owner = OwnerContext::resolve();

        if ($owner !== null && method_exists($owner, 'users')) {
            $usersRelation = $owner->users();

            if ($usersRelation instanceof Relation) {
                /** @var Collection<int|string, Model> $users */
                $users = $usersRelation->get(['id', 'name']);

                $options = [];

                foreach ($users as $user) {
                    $id = $user->getAttribute('id');
                    $name = $user->getAttribute('name');

                    if ($id === null || ! is_string($name)) {
                        continue;
                    }

                    $options[(string) $id] = $name;
                }

                if ($options !== []) {
                    return $options;
                }
            }
        }

        $currentUser = auth()->user();

        if ($currentUser instanceof Model) {
            $name = $currentUser->getAttribute('name');

            if (is_string($name)) {
                return [(string) $currentUser->getKey() => $name];
            }
        }

        return [];
    }

    private static function userCanActOnApproval(DocApproval $approval): bool
    {
        $userId = auth()->id();

        if ($userId === null) {
            return false;
        }

        if ($approval->assigned_to === null) {
            return true;
        }

        return (string) $approval->assigned_to === (string) $userId;
    }

    private static function assertUserCanActOnApproval(DocApproval $approval): void
    {
        abort_unless(self::userCanActOnApproval($approval), 403);
    }
}
