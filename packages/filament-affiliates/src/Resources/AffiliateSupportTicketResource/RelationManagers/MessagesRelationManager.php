<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\RelationManagers;

use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Textarea::make('message')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Toggle::make('is_staff_reply')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('message')
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('is_staff_reply')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Staff' : 'Affiliate'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        if (! $ownerRecord instanceof AffiliateSupportTicket) {
                            return $data;
                        }

                        $isStaffReply = (bool) ($data['is_staff_reply'] ?? true);

                        $staffId = auth()->id();

                        $data['affiliate_id'] = $isStaffReply
                            ? null
                            : (string) $ownerRecord->affiliate_id;

                        $data['staff_id'] = $isStaffReply && $staffId !== null
                            ? (string) $staffId
                            : null;

                        return $data;
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
