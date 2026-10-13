<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardProgramResource\Schemas;

use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RewardProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('family_key')
                ->label('Family key')
                ->helperText('Stable program family slug, 1–64 lowercase characters. Never changes after creation.')
                ->required()
                ->maxLength(64)
                ->regex('/^[a-z0-9][a-z0-9_\-]{0,63}$/'),
            TextInput::make('title')
                ->required()
                ->maxLength(191),
            Select::make('mode')
                ->options(RewardAdminPresenter::programModeOptions())
                ->required(),
            Select::make('owner_type')
                ->options(self::ownerTypeOptions())
                ->default('platform')
                ->required()
                ->live()
                ->afterStateUpdated(static function (Set $set): void {
                    $set('owner_record_id', null);
                }),
            Select::make('owner_record_id')
                ->label('Owner')
                ->helperText('Search by name. Platform programs need no owner.')
                ->searchable()
                ->required(fn (Get $get): bool => $get('owner_type') !== 'platform')
                ->disabled(fn (Get $get): bool => $get('owner_type') === 'platform' || blank($get('owner_type')))
                ->getSearchResultsUsing(static fn (Get $get, ?string $search): array => self::ownerSearchResults(
                    (string) $get('owner_type'),
                    $search,
                ))
                ->getOptionLabelUsing(static fn (Get $get, mixed $value): ?string => self::ownerOptionLabel(
                    (string) $get('owner_type'),
                    $value,
                )),
            Textarea::make('purpose')
                ->helperText('Explicit non-empty scope, e.g. the community this program serves.')
                ->required()
                ->maxLength(191)
                ->rows(2),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function ownerTypeOptions(): array
    {
        $options = ['platform' => 'Platform'];

        foreach (config('filament-rewards.owners.types', []) as $alias => $type) {
            if (is_string($alias) && is_array($type) && is_string($type['model'] ?? null)) {
                $options[$alias] = (string) ($type['label'] ?? ucfirst($alias));
            }
        }

        return $options;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function ownerModelFor(string $ownerType): ?string
    {
        $types = config('filament-rewards.owners.types', []);
        $model = is_array($types[$ownerType] ?? null) ? ($types[$ownerType]['model'] ?? null) : null;

        return is_string($model) && class_exists($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    private static function ownerLabelColumn(string $ownerType): string
    {
        $types = config('filament-rewards.owners.types', []);
        $column = is_array($types[$ownerType] ?? null) ? ($types[$ownerType]['name_column'] ?? null) : null;

        return is_string($column) && $column !== '' ? $column : 'name';
    }

    /**
     * @return array<string, string>
     */
    private static function ownerSearchResults(string $ownerType, ?string $search): array
    {
        $model = self::ownerModelFor($ownerType);

        if ($model === null) {
            return [];
        }

        $search = mb_trim((string) $search);
        $column = self::ownerLabelColumn($ownerType);

        return $model::query()
            ->when($search !== '', static fn ($query) => $query->where($column, 'like', '%' . $search . '%'))
            ->orderBy($column)
            ->limit(25)
            ->pluck($column, 'id')
            ->all();
    }

    private static function ownerOptionLabel(string $ownerType, mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $model = self::ownerModelFor($ownerType);

        if ($model === null) {
            return null;
        }

        $name = $model::query()->whereKey($value)->value(self::ownerLabelColumn($ownerType));

        return is_string($name) ? $name : null;
    }
}
