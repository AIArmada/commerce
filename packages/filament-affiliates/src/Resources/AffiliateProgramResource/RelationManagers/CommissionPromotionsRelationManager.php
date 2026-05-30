<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class CommissionPromotionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionPromotions';

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

            TextInput::make('current_uses')
                ->numeric()
                ->default(0),

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
                    ->mutateDataUsing(fn (array $data): array => $this->sanitizeAffiliateIds($data)),
            ])
            ->actions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => $this->sanitizeAffiliateIds($data)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeAffiliateIds(array $data): array
    {
        $affiliateIds = $data['affiliate_ids'] ?? null;

        if ($affiliateIds === null || $affiliateIds === []) {
            $data['affiliate_ids'] = null;

            return $data;
        }

        if (! is_array($affiliateIds)) {
            throw ValidationException::withMessages([
                'affiliate_ids' => 'Affiliate IDs must be a list of valid affiliates.',
            ]);
        }

        $validatedAffiliateIds = [];

        foreach ($affiliateIds as $affiliateId) {
            if (! is_string($affiliateId) && ! is_int($affiliateId)) {
                throw ValidationException::withMessages([
                    'affiliate_ids' => 'Affiliate IDs must contain only valid affiliate identifiers.',
                ]);
            }

            try {
                $validatedAffiliateIds[] = (string) OwnerWriteGuard::findOrFailForOwner(
                    Affiliate::class,
                    (string) $affiliateId,
                    includeGlobal: (bool) config('affiliates.owner.include_global', false),
                    message: 'One or more selected affiliates are not accessible in the current owner scope.',
                )->getKey();
            } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
                throw ValidationException::withMessages([
                    'affiliate_ids' => 'One or more selected affiliates are not accessible in the current owner scope.',
                ]);
            }
        }

        $data['affiliate_ids'] = array_values(array_unique($validatedAffiliateIds));

        return $data;
    }
}
