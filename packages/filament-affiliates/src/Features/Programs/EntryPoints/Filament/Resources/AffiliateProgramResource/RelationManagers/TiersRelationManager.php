<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Features\Programs\Api\ProgramTierAdministration;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use function Filament\authorize;

final class TiersRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'tiers';

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! FilamentPermission::hasAbility('affiliate.view')) {
            return false;
        }

        try {
            return authorize('viewAny', AffiliateProgramTier::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function getRelationship(): Relation
    {
        return $this->getOwnerRecord()->hasMany(AffiliateProgramTier::class, 'program_id')->orderBy('level');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('level')
                ->numeric()
                ->required(),

            TextInput::make('commission_rate_basis_points')
                ->label('Commission Rate (basis points)')
                ->numeric()
                ->required(),

            TextInput::make('min_conversions')
                ->numeric()
                ->required()
                ->default(0),

            TextInput::make('min_revenue')
                ->numeric()
                ->required()
                ->default(0),

            Select::make('min_revenue_currency')
                ->label('Min Revenue Currency')
                ->options([
                    'USD' => 'USD',
                    'MYR' => 'MYR',
                    'SGD' => 'SGD',
                    'IDR' => 'IDR',
                ])
                ->default((string) config('affiliates.currency.default', 'MYR'))
                ->required(),

            KeyValue::make('benefits')
                ->keyLabel('Benefit')
                ->valueLabel('Description')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('level')
                    ->sortable(),

                TextColumn::make('commission_rate_basis_points')
                    ->label('Commission')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : ($state / 100) . '%')
                    ->sortable(),

                TextColumn::make('min_conversions')
                    ->label('Min Conversions')
                    ->sortable(),

                TextColumn::make('min_revenue')
                    ->label('Min Revenue')
                    ->formatStateUsing(fn ($record): string => MoneyFormatter::formatMinor($record->min_revenue, $record->revenueCurrency()))
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (array $data): AffiliateProgramTier {
                        $id = app(ProgramTierAdministration::class)->create((string) $this->getOwnerRecord()->getKey(), $data);

                        return AffiliateProgramTier::query()->findOrFail($id);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (AffiliateProgramTier $record, array $data): AffiliateProgramTier {
                        app(ProgramTierAdministration::class)->update((string) $record->getKey(), $data);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->action(fn (AffiliateProgramTier $record) => app(ProgramTierAdministration::class)->delete((string) $record->getKey())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                app(ProgramTierAdministration::class)->delete((string) $record->getKey());
                            }
                        }),
                ]),
            ]);
    }
}
