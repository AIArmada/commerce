<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Persons\Support\ModelResolver;
use AIArmada\Persons\Support\PersonsModelReferenceGuard;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AffiliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliations';

    protected static ?string $title = 'Affiliations';

    protected static ?string $recordTitleAttribute = 'institution_id';

    /**
     * @return array<string, string>
     */
    public static function getInstitutionOptions(): array
    {
        $class = ModelResolver::institutionClass();

        if ($class === null) {
            return [];
        }

        $query = $class::query();

        if (method_exists($class, 'ownerScopeConfig') && $class::ownerScopeConfig()->enabled) {
            $config = $class::ownerScopeConfig();

            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                OwnerContext::resolve(),
                $config->includeGlobal,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            );
        }

        /** @var array<string, string> $options */
        $options = $query->limit(500)->get()->mapWithKeys(
            static fn (Model $model): array => [(string) $model->getKey() => self::institutionLabel($model)]
        )->all();

        return $options;
    }

    public static function getInstitutionLabel(string $id): ?string
    {
        return static::getInstitutionOptions()[$id] ?? null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('affiliation_type')
                    ->options([
                        'member' => 'Member',
                        'employee' => 'Employee',
                        'advisor' => 'Advisor',
                        'partner' => 'Partner',
                    ])
                    ->required(),
                Select::make('institution_id')
                    ->label('Institution')
                    ->options(static::getInstitutionOptions())
                    ->searchable()
                    ->preload(),
                DatePicker::make('joined_at'),
                DatePicker::make('left_at'),
                Checkbox::make('is_primary'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('affiliation_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('institution_id')
                    ->label('Institution')
                    ->formatStateUsing(fn (string $state): string => static::getInstitutionLabel($state) ?? $state)
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean(),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('left_at')
                    ->date()
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $this->assertInstitutionReference($data);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $this->assertInstitutionReference($data);

                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertInstitutionReference(array $data): void
    {
        $institutionId = $data['institution_id'] ?? null;

        if ($institutionId === null || $institutionId === '') {
            return;
        }

        try {
            app(PersonsModelReferenceGuard::class)->resolve(
                ModelResolver::institutionClass(),
                $institutionId,
                'affiliation institution',
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'institution_id' => [$exception->getMessage()],
            ]);
        }
    }

    private static function institutionLabel(Model $model): string
    {
        foreach (['name', 'title', 'label'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return (string) $model->getKey();
    }
}
