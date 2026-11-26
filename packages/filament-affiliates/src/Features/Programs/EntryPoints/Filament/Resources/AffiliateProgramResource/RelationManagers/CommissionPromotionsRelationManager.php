<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionAdministration;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class CommissionPromotionsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'commissionPromotions';

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof AffiliateProgram && FilamentPermission::hasAbility('affiliate.view');
    }

    #[Unexposed]
    public function getRelationship(): Relation | Builder
    {
        return $this->getOwnerRecord()->hasMany(AffiliateCommissionPromotion::class, 'program_id')->orderByDesc('created_at');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->rows(3),

            Select::make('bonus_type')
                ->options([
                    'percentage' => 'Percentage',
                    'flat' => 'Flat',
                    'multiplier' => 'Multiplier',
                ])
                ->required(),

            TextInput::make('bonus_value')
                ->numeric()
                ->required(),

            DateTimePicker::make('starts_at')
                ->required(),

            DateTimePicker::make('ends_at')
                ->required(),

            TextInput::make('max_uses')
                ->numeric(),

            TagsInput::make('affiliate_ids')
                ->label('Affiliate IDs')
                ->columnSpanFull(),

            KeyValue::make('conditions')
                ->keyLabel('Condition')
                ->valueLabel('Value')
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

                TextColumn::make('bonus_type')
                    ->badge(),

                TextColumn::make('bonus_value')
                    ->sortable(),

                TextColumn::make('current_uses')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('ends_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (array $data): AffiliateCommissionPromotion {
                        $id = app(ProgramCommissionAdministration::class)->createPromotion((string) $this->getOwnerRecord()->getKey(), $data);

                        return AffiliateCommissionPromotion::query()->findOrFail($id);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (AffiliateCommissionPromotion $record, array $data): AffiliateCommissionPromotion {
                        app(ProgramCommissionAdministration::class)->updatePromotion((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey(), $data);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                    ->using(fn (AffiliateCommissionPromotion $record): bool => app(ProgramCommissionAdministration::class)->deletePromotion((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                app(ProgramCommissionAdministration::class)->deletePromotion((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey());
                            }
                        }),
                ]),
            ]);
    }
}
