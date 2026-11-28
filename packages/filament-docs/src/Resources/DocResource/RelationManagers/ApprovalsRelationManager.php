<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentApprovalRequests;
use AIArmada\Docs\Contracts\DocumentApprovals;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\FilamentDocs\Support\DocPermissions;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

final class ApprovalsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'approvals';

    protected static ?string $recordTitleAttribute = 'requested_by';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with(['requestedBy', 'assignedTo']))
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
                    ->authorize(fn (): bool => DocPermissions::allows(DocPermissions::DOCUMENT, 'update'))
                    ->using(function (array $data): DocApproval {
                        abort_unless(DocPermissions::allows(DocPermissions::DOCUMENT, 'update'), 403);

                        $doc = $this->getOwnerRecord();
                        abort_unless($doc instanceof Doc, 404);
                        DocsOwnerScope::assertCanMutateRecord($doc, 'Document not found.');

                        $actorId = auth()->id();
                        abort_if($actorId === null, 403);

                        $assigneeId = $data['assigned_to'] ?? null;
                        if ($assigneeId !== null && $assigneeId !== '') {
                            $assignableUserIds = array_keys(self::resolveAssignableUserOptions());

                            if (! is_scalar($assigneeId) || ! in_array((string) $assigneeId, array_map('strval', $assignableUserIds), true)) {
                                throw ValidationException::withMessages([
                                    'assigned_to' => __('Invalid assignee selection.'),
                                ]);
                            }
                        }

                        $id = app(DocumentApprovalRequests::class)->request(
                            (string) $doc->getKey(),
                            (string) $actorId,
                            $assigneeId === null || $assigneeId === '' ? null : (string) $assigneeId,
                            $data['expires_at'] ?? null,
                            $data['comments'] ?? null,
                        );

                        return $doc->approvals()->findOrFail($id);
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
                        DocsOwnerScope::assertCanMutateRecord($record, 'Approval not found.');

                        app(DocumentApprovals::class)->decide(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $record->getKey(),
                            (string) auth()->id(),
                            true,
                            $data['comments'] ?? null,
                            DocPermissions::allows(DocPermissions::DOCUMENT_APPROVAL, 'approve'),
                        );
                    })
                    ->visible(fn (DocApproval $record): bool => $record->isPending() && ! $record->isExpired() && self::userCanActOnApproval($record)),

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
                        DocsOwnerScope::assertCanMutateRecord($record, 'Approval not found.');

                        app(DocumentApprovals::class)->decide(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $record->getKey(),
                            (string) auth()->id(),
                            false,
                            $data['comments'],
                            DocPermissions::allows(DocPermissions::DOCUMENT_APPROVAL, 'approve'),
                        );
                    })
                    ->visible(fn (DocApproval $record): bool => $record->isPending() && ! $record->isExpired() && self::userCanActOnApproval($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete_selected')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => DocPermissions::allows(DocPermissions::DOCUMENT, 'update'))
                        ->action(function (Collection $records): void {
                            abort_unless(DocPermissions::allows(DocPermissions::DOCUMENT, 'update'), 403);

                            $doc = $this->getOwnerRecord();
                            abort_unless($doc instanceof Doc, 404);
                            DocsOwnerScope::assertCanMutateRecord($doc, 'Document not found.');

                            app(DocumentApprovalRequests::class)->remove(
                                (string) $doc->getKey(),
                                array_map('strval', $records->modelKeys()),
                            );
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
            return DocPermissions::allows(DocPermissions::DOCUMENT_APPROVAL, 'approve');
        }

        return (string) $approval->assigned_to === (string) $userId;
    }

    private static function assertUserCanActOnApproval(DocApproval $approval): void
    {
        abort_unless(self::userCanActOnApproval($approval), 403);
    }
}
