<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations;

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

final class PayoutHoldsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'payoutHolds';

    /** @return HasMany<AffiliatePayoutHold, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(AffiliatePayoutHold::class, 'affiliate_id');
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Affiliate && Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('reason')->searchable(),
                TextColumn::make('notes')->limit(40)->placeholder('—'),
                TextColumn::make('expires_at')->label('Expires')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('released_at')->label('Released')->dateTime()->placeholder('—')->sortable(),
            ])
            ->headerActions([
                Action::make('placeHold')
                    ->label('Place hold')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->schema([
                        TextInput::make('reason')->required()->maxLength(255),
                        Textarea::make('notes')->rows(3),
                        DateTimePicker::make('expires_at'),
                    ])
                    ->action(function (array $data): void {
                        Gate::authorize('update', $this->getOwnerRecord());

                        $actorId = auth()->id();
                        app(PayoutHoldAdministration::class)->place(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $data['reason'],
                            notes: $data['notes'] ?? null,
                            expiresAt: filled($data['expires_at'] ?? null) ? CarbonImmutable::parse($data['expires_at']) : null,
                            placedBy: $actorId === null ? null : (string) $actorId,
                        );
                    }),
            ])
            ->recordActions([
                Action::make('releaseHold')
                    ->label('Release hold')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->visible(fn (AffiliatePayoutHold $record): bool => $record->released_at === null)
                    ->requiresConfirmation()
                    ->schema([Textarea::make('notes')->rows(3)])
                    ->action(function (AffiliatePayoutHold $record, array $data): void {
                        Gate::authorize('update', $this->getOwnerRecord());

                        app(PayoutHoldAdministration::class)->release(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $record->getKey(),
                            $data['notes'] ?? null,
                        );
                    }),
            ]);
    }
}
