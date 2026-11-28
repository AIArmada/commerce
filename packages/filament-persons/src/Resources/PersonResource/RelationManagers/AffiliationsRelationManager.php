<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Persons\Features\Affiliations\Actions\SaveAffiliation;
use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use AIArmada\Persons\Support\ModelResolver;
use AIArmada\Persons\Support\PersonsModelReferenceGuard;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AffiliationsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'affiliations';

    protected static ?string $title = 'Affiliations';

    protected static ?string $recordTitleAttribute = 'institution_id';

    /**
     * @return array<string, string>
     */
    public static function getInstitutionOptions(?string $search = null, int $limit = 50): array
    {
        $class = ModelResolver::institutionClass();

        if ($class === null) {
            return [];
        }

        $query = self::scopedInstitutionQuery($class);
        $model = $query->getModel();
        $labelColumn = self::institutionLabelColumn();
        $searchColumns = self::institutionSearchColumns($labelColumn);
        $trimmedSearch = $search !== null ? mb_trim($search) : '';

        if ($trimmedSearch !== '') {
            $grammar = $query->getQuery()->getGrammar();
            $operator = ConnectionDriver::name($query->getConnection()) === 'pgsql' ? 'ILIKE' : 'LIKE';
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $trimmedSearch) . '%';

            $query->where(static function (Builder $builder) use ($grammar, $model, $operator, $pattern, $searchColumns): void {
                foreach ($searchColumns as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $wrapped = $grammar->wrap($model->qualifyColumn($column));
                    $builder->{$method}($wrapped . ' ' . $operator . " ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        $query->orderBy($model->qualifyColumn($labelColumn))
            ->orderBy($model->qualifyColumn($model->getKeyName()));

        /** @var array<string, string> $options */
        $options = $query->limit(max(1, min($limit, 500)))->get()->mapWithKeys(
            static fn (Model $model): array => [(string) $model->getKey() => self::institutionLabel($model)]
        )->all();

        return $options;
    }

    public static function getInstitutionLabel(string $id): ?string
    {
        if ($id === '') {
            return null;
        }

        $class = ModelResolver::institutionClass();

        if ($class === null) {
            return null;
        }

        $model = self::scopedInstitutionQuery($class)->find($id);

        return $model instanceof Model ? self::institutionLabel($model) : null;
    }

    /**
     * @param  class-string<Model>  $class
     * @return Builder<Model>
     */
    private static function scopedInstitutionQuery(string $class): Builder
    {
        $query = $class::query();

        if (! method_exists($class, 'ownerScopeConfig')) {
            return $query;
        }

        $config = $class::ownerScopeConfig();

        if (! $config->enabled) {
            return $query;
        }

        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            $config->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal(),
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    private static function institutionLabelColumn(): string
    {
        return self::columnIdentifier(
            config('filament-persons.resources.institution.label_column', 'name'),
            'resources.institution.label_column',
        );
    }

    /**
     * @return list<string>
     */
    private static function institutionSearchColumns(string $labelColumn): array
    {
        $configured = config('filament-persons.resources.institution.search_columns');

        if ($configured === null) {
            return [$labelColumn];
        }

        if (! is_array($configured)) {
            throw new InvalidArgumentException('filament-persons.resources.institution.search_columns must be a list of plain column identifiers.');
        }

        if ($configured === []) {
            return [$labelColumn];
        }

        $columns = [];

        foreach ($configured as $column) {
            $columns[] = self::columnIdentifier($column, 'resources.institution.search_columns');
        }

        return array_values(array_unique($columns));
    }

    private static function columnIdentifier(mixed $column, string $key): string
    {
        if (! is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'filament-persons.%s must be a plain column identifier.',
                $key,
            ));
        }

        return $column;
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
                TextInput::make('position')
                    ->maxLength(50)
                    ->nullable(),
                Select::make('institution_id')
                    ->label('Institution')
                    ->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => static::getInstitutionOptions($search, 50))
                    ->getOptionLabelUsing(static fn (?string $value): ?string => filled($value) ? static::getInstitutionLabel((string) $value) : null),
                DatePicker::make('joined_at'),
                DatePicker::make('left_at'),
                Checkbox::make('is_primary'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static function (Builder $query): Builder {
                if (ModelResolver::institutionClass() !== null) {
                    $query->with('institution');
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('affiliation_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('institution_id')
                    ->label('Institution')
                    ->formatStateUsing(static function (string $state, ?Model $record): string {
                        if ($record instanceof Affiliation && $record->relationLoaded('institution')) {
                            $institution = $record->getRelationValue('institution');

                            if ($institution instanceof Model) {
                                return self::institutionLabel($institution);
                            }
                        }

                        return static::getInstitutionLabel($state) ?? $state;
                    })
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
                    ->using(fn (array $data): Affiliation => app(SaveAffiliation::class)->create($this->getOwnerRecord(), $data))
                    ->mutateFormDataUsing(function (array $data): array {
                        $this->assertInstitutionReference($data);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(fn (Affiliation $record, array $data): Affiliation => app(SaveAffiliation::class)->update($this->getOwnerRecord(), $record, $data))
                    ->mutateFormDataUsing(function (array $data): array {
                        $this->assertInstitutionReference($data);

                        return $data;
                    }),
                DeleteAction::make()
                    ->using(fn (Affiliation $record): bool => app(SaveAffiliation::class)->delete($this->getOwnerRecord(), $record)),
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
        $value = $model->getAttribute(self::institutionLabelColumn());

        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        return (string) $model->getKey();
    }
}
