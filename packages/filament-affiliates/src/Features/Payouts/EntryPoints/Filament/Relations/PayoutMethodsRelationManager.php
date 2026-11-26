<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations;

use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

final class PayoutMethodsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'payoutMethods';

    /** @return HasMany<AffiliatePayoutMethod, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(AffiliatePayoutMethod::class, 'affiliate_id');
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Affiliate && Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('label')
                    ->state(fn (AffiliatePayoutMethod $record): string => $record->type === PayoutMethodType::PayPal
                        ? (string) ($record->getMaskedDetails()['email'] ?? 'PayPal')
                        : $record->label),
                IconColumn::make('verified_at')->label('Verified')->boolean(),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('createMethod')
                    ->label('Add method')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->schema([...$this->methodFields(), Toggle::make('is_default')->default(false)])
                    ->action(function (array $data): void {
                        Gate::authorize('update', $this->getOwnerRecord());
                        app(PayoutMethodAdministration::class)->create((string) $this->getOwnerRecord()->getKey(), (string) $data['type'], $data['details'], (bool) ($data['is_default'] ?? false));
                    }),
            ])
            ->recordActions([
                Action::make('replaceMethod')
                    ->label('Edit')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->fillForm(function (AffiliatePayoutMethod $record): array {
                        Gate::authorize('update', $this->getOwnerRecord());

                        return ['type' => $record->type->value, 'details' => $record->details];
                    })
                    ->schema($this->methodFields())
                    ->action(function (AffiliatePayoutMethod $record, array $data): void {
                        Gate::authorize('update', $this->getOwnerRecord());
                        app(PayoutMethodAdministration::class)->replace((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey(), (string) $data['type'], $data['details']);
                    }),
                Action::make('verifyMethod')
                    ->label('Verify')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->visible(fn (AffiliatePayoutMethod $record): bool => ! $record->isVerified())
                    ->requiresConfirmation()
                    ->action(function (AffiliatePayoutMethod $record): void {
                        Gate::authorize('update', $this->getOwnerRecord());
                        app(PayoutMethodAdministration::class)->verify((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey());
                    }),
                Action::make('selectDefault')
                    ->label('Set default')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->visible(fn (AffiliatePayoutMethod $record): bool => ! $record->is_default)
                    ->requiresConfirmation()
                    ->action(function (AffiliatePayoutMethod $record): void {
                        Gate::authorize('update', $this->getOwnerRecord());
                        app(PayoutMethodAdministration::class)->selectDefault((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey());
                    }),
                Action::make('removeMethod')
                    ->label('Remove')
                    ->color('danger')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->action(function (AffiliatePayoutMethod $record): void {
                        Gate::authorize('update', $this->getOwnerRecord());
                        app(PayoutMethodAdministration::class)->remove((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey());
                    }),
            ]);
    }

    /** @return array<int, Select|KeyValue> */
    private function methodFields(): array
    {
        return [
            Select::make('type')
                ->options(collect(PayoutMethodType::cases())
                    ->filter(fn (PayoutMethodType $type): bool => app(PayoutProcessorRegistry::class)->hasProcessor($type->value))
                    ->mapWithKeys(fn (PayoutMethodType $type): array => [$type->value => $type->label()])
                    ->all())
                ->required(),
            KeyValue::make('details')->required(),
        ];
    }
}
