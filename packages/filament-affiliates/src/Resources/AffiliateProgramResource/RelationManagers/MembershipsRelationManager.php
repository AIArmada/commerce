<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
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

final class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('affiliate_id')
                ->relationship('affiliate', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('tier_id')
                ->relationship('tier', 'name')
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
            ->recordTitleAttribute('affiliate_id')
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable(),

                TextColumn::make('tier.name')
                    ->label('Tier')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->dateTime(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('expires_at')
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
                    ->visible(fn (AffiliateProgramMembership $record): bool => $record->status === MembershipStatus::Pending)
                    ->action(function (AffiliateProgramMembership $record): void {
                        $record->update([
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
                    ->visible(fn (AffiliateProgramMembership $record): bool => $record->status === MembershipStatus::Pending)
                    ->action(function (AffiliateProgramMembership $record): void {
                        $record->update([
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
