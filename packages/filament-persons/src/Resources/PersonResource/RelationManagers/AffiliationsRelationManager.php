<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Persons\Models\Affiliation;
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
        $trimmedSearch = $search !== null ? mb_trim($search) : '';

        if ($trimmedSearch !== '') {
            /** @var Model $modelInstance */
            $modelInstance = new $class;
            $connection = $modelInstance->getConnection();
            $columns = $connection->getSchemaBuilder()->getColumnListing($modelInstance->getTable());
            $searchableColumns = array_values(array_intersect(['name', 'title', 'label'], $columns));

            if ($searchableColumns === []) {
                return [];
            }

            $grammar = $connection->getQueryGrammar();
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($trimmedSearch));
            $like = '%' . $escaped . '%';

            $query->where(static function (Builder $builder) use ($searchableColumns, $grammar, $like): void {
                foreach ($searchableColumns as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $builder->{$method}('LOWER(' . $grammar->wrap($column) . ') LIKE ?', [$like]);
                }
            });
        }

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

        if (method_exists($class, 'ownerScopeConfig') && $class::ownerScopeConfig()->enabled) {
            $config = $class::ownerScopeConfig();

            return OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                OwnerContext::resolve(),
                $config->includeGlobal,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            );
        }

        return $query;
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
