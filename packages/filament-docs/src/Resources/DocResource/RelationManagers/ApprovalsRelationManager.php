<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    protected static ?string $recordTitleAttribute = 'requested_by';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('requested_by')
                    ->required()
                    ->maxLength(255)
                    ->default(fn () => auth()->user()?->name ?? auth()->id()),

                TextInput::make('assigned_to')
                    ->maxLength(255)
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

                TextColumn::make('requested_by')
                    ->label('Requested By')
                    ->searchable(),

                TextColumn::make('assigned_to')
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
                        $data['status'] = 'pending';

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
                    ->action(function ($record, array $data): void {
                        $record->approve($data['comments'] ?? null);
                    })
                    ->visible(fn ($record) => $record->isPending()),

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
                    ->action(function ($record, array $data): void {
                        $record->reject($data['comments']);
                    })
                    ->visible(fn ($record) => $record->isPending()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
