<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource;

use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\CreateReferralProgram;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\EditReferralProgram;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\ListReferralPrograms;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\ViewReferralProgram;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\RelationManagers\InvitesRelationManager;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Schemas\ReferralProgramForm;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Schemas\ReferralProgramInfolist;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Tables\ReferralProgramsTable;
use AIArmada\Referrals\Models\ReferralProgram;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class ReferralProgramResource extends Resource
{
    protected static ?string $model = ReferralProgram::class;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-referrals.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-referrals.navigation.sort.programs');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ReferralProgramForm::configure($schema);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ReferralProgramInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ReferralProgramsTable::configure($table);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['invites', 'rewards']);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            InvitesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListReferralPrograms::route('/'),
            'create' => CreateReferralProgram::route('/create'),
            'view' => ViewReferralProgram::route('/{record}'),
            'edit' => EditReferralProgram::route('/{record}/edit'),
        ];
    }
}
