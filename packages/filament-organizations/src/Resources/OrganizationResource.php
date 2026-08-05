<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages;
use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Models\Organization;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-organizations.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-organizations.navigation.sort', 10);
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user() instanceof Model;
    }

    public static function canCreate(): bool
    {
        return Filament::auth()->user() instanceof Model;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAuthorize($record, 'organization.update');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Model) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereHas('members', fn (Builder $query): Builder => $query->whereKey($user->getKey()))
            ->withCount('members');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->maxLength(255)
                ->disabled(fn (string $operation): bool => $operation === 'create'),
            Textarea::make('description')
                ->rows(5)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            OrganizationResource\RelationManagers\MembersRelationManager::class,
            OrganizationResource\RelationManagers\InvitationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'view' => Pages\ViewOrganization::route('/{record}'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }

    public static function authorizeRecord(Organization $organization, string $ability): void
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof Model) {
            throw new AuthorizationException('Authentication is required.');
        }

        app(OrganizationAuthorization::class)->authorize($actor, $organization, $ability);
    }

    private static function canAuthorize(Model $record, string $ability): bool
    {
        if (! $record instanceof Organization) {
            return false;
        }

        try {
            static::authorizeRecord($record, $ability);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }
}
