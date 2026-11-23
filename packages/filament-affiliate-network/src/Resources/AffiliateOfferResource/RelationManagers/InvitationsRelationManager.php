<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\InviteAffiliateToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class))
            ->recordTitleAttribute('token')
            ->columns([
                TextColumn::make('affiliate_id')
                    ->label('Affiliate ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('token')
                    ->label('Token')
                    ->copyable()
                    ->limit(20),

                IconColumn::make('auto_approve')
                    ->label('Auto approve')
                    ->boolean(),

                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('accepted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('revoked_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('invite')
                    ->label('Invite affiliate')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->form([
                        TextInput::make('affiliate_id')
                            ->label('Affiliate ID')
                            ->maxLength(36),
                        Toggle::make('auto_approve')
                            ->label('Auto approve')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        app(InviteAffiliateToOffer::class)->execute(
                            (string) $this->getOwnerRecord()->getKey(),
                            is_string($data['affiliate_id'] ?? null) && $data['affiliate_id'] !== '' ? $data['affiliate_id'] : null,
                            null,
                            null,
                            (bool) ($data['auto_approve'] ?? true),
                        );
                    }),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOfferInvitation $record): bool => $record->revoked_at === null)
                    ->action(function (AffiliateOfferInvitation $record): void {
                        app(RevokeOfferInvitation::class)->execute($record);
                    }),
            ])
            ->bulkActions([]);
    }
}
