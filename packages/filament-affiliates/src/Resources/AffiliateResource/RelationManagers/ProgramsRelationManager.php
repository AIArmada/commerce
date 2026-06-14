<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'programs';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('tier_id')
                ->options(fn (): array => AffiliateProgramTier::query()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->placeholder('No tiers configured'),

            Select::make('status')
                ->options(MembershipStatus::class)
                ->required()
                ->default(MembershipStatus::Pending),

            DateTimePicker::make('applied_at')
                ->required()
                ->default(now()),

            DateTimePicker::make('approved_at'),

            DateTimePicker::make('expires_at'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Program')
                    ->searchable(),

                TextColumn::make('pivot.tier.name')
                    ->label('Tier')
                    ->placeholder('—'),

                TextColumn::make('pivot.status')
                    ->badge(),

                TextColumn::make('pivot.applied_at')
                    ->label('Applied')
                    ->dateTime(),

                TextColumn::make('pivot.approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('pivot.expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (AffiliateProgram $record): bool => $record->pivot?->status === MembershipStatus::Pending)
                    ->action(function (AffiliateProgram $record): void {
                        $record->pivot->update([
                            'status' => MembershipStatus::Approved,
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Membership approved')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (AffiliateProgram $record): bool => $record->pivot?->status === MembershipStatus::Pending)
                    ->action(function (AffiliateProgram $record): void {
                        $record->pivot->update([
                            'status' => MembershipStatus::Rejected,
                            'rejected_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Membership rejected')
                            ->danger()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
