<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as FoundationUser;
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
                        $userModelClass = self::resolveUserModelClass();

                        return $userModelClass::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
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
                        $doc = $this->getOwnerDoc();

                        $data['owner_type'] = $doc->owner_type;
                        $data['owner_id'] = $doc->owner_id;

                        $data['requested_by'] = auth()->id() !== null ? (string) auth()->id() : null;
                        $data['status'] = 'pending';

                        if ($data['requested_by'] === null) {
                            throw ValidationException::withMessages([
                                'requested_by' => __('You must be logged in to request approval.'),
                            ]);
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

                        $record->reject($data['comments']);
                    })
                    ->visible(fn (DocApproval $record): bool => $record->isPending() && self::userCanActOnApproval($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return class-string<Model>
     */
    private static function resolveUserModelClass(): string
    {
        $configured = config('auth.providers.users.model');

        if (is_string($configured) && class_exists($configured)) {
            /** @var class-string<Model> $configured */
            return $configured;
        }

        return FoundationUser::class;
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

    private function getOwnerDoc(): Doc
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord instanceof Doc) {
            throw ValidationException::withMessages([
                'doc' => __('Invalid document context.'),
            ]);
        }

        return $ownerRecord;
    }
}
