<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource\RelationManagers;

use AIArmada\Affiliates\Features\Support\Api\SupportTicketApi;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class MessagesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'messages';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Textarea::make('message')
                ->required()
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull(),
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
                    ->using(function (array $data): AffiliateSupportMessage {
                        $ticket = $this->getOwnerRecord();
                        $staffId = auth()->id();

                        if (! $ticket instanceof AffiliateSupportTicket || $staffId === null) {
                            throw new AuthorizationException;
                        }

                        Gate::authorize('update', $ticket);
                        $messageId = app(SupportTicketApi::class)->replyAsStaff((string) $ticket->getKey(), (string) $staffId, (string) $data['message']);

                        return AffiliateSupportMessage::query()->findOrFail($messageId);
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
