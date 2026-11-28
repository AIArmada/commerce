<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\AddOrganizationMemberAction;
use AIArmada\Organizations\Actions\ChangeOrganizationMemberRoleAction;
use AIArmada\Organizations\Actions\RemoveOrganizationMemberAction;
use AIArmada\Organizations\Models\Organization;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class MembersRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('pivot.role')->badge()->label('Role'),
                TextColumn::make('pivot.joined_at')->dateTime()->label('Joined'),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->form([
                        TextInput::make('email')->email()->required(),
                        Select::make('role')->options(collect(MemberRole::cases())->reject(fn (MemberRole $role): bool => $role === MemberRole::Owner)->mapWithKeys(fn (MemberRole $role): array => [$role->value => $role->label()])->all())->default(MemberRole::Viewer->value)->required(),
                    ])
                    ->action(function (MembersRelationManager $livewire, array $data): void {
                        $organization = self::ownerOrganization($livewire);
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);
                        app(AddOrganizationMemberAction::class)->handle(
                            $organization,
                            $actor,
                            (string) $data['email'],
                            MemberRole::from($data['role']),
                        );
                    }),
            ])
            ->actions([
                Action::make('changeRole')
                    ->form([
                        Select::make('role')->options(collect(MemberRole::cases())->reject(fn (MemberRole $role): bool => $role === MemberRole::Owner)->mapWithKeys(fn (MemberRole $role): array => [$role->value => $role->label()])->all())->required(),
                    ])
                    ->fillForm(fn (Model $record): array => ['role' => MemberRole::fromSpatieRoleName((string) data_get($record->getRelationValue('pivot'), 'role'))?->value])
                    ->action(function (MembersRelationManager $livewire, Model $record, array $data): void {
                        $organization = self::ownerOrganization($livewire);
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);
                        app(ChangeOrganizationMemberRoleAction::class)->handle($organization, $actor, $record, MemberRole::from($data['role']));
                    })
                    ->visible(fn (Model $record): bool => (string) data_get($record->getRelationValue('pivot'), 'role') !== MemberRole::Owner->spatieRoleName()),
                Action::make('remove')
                    ->requiresConfirmation()
                    ->action(function (MembersRelationManager $livewire, Model $record): void {
                        $organization = self::ownerOrganization($livewire);
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);
                        app(RemoveOrganizationMemberAction::class)->handle($organization, $actor, $record);
                    })
                    ->visible(fn (Model $record): bool => (string) data_get($record->getRelationValue('pivot'), 'role') !== MemberRole::Owner->spatieRoleName()),
            ]);
    }

    private static function ownerOrganization(MembersRelationManager $livewire): Organization
    {
        $owner = $livewire->getOwnerRecord();

        abort_unless($owner instanceof Organization, 404);

        return $owner;
    }
}
