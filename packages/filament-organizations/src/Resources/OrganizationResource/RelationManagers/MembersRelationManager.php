<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\RelationManagers;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Actions\ChangeMemberRoleAction;
use AIArmada\Membership\Actions\RemoveMemberAction;
use AIArmada\Membership\Enums\MemberRole;
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
                        Select::make('role')->options(collect(MemberRole::cases())->mapWithKeys(fn (MemberRole $role): array => [$role->value => $role->label()])->all())->default(MemberRole::Viewer->value)->required(),
                    ])
                    ->action(function (Organization $organization, array $data): void {
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);
                        OrganizationResource::authorizeRecord($organization, 'organization.manage-members');
                        $member = $organization->members()->getModel()->newQuery()->where('email', $data['email'])->first();
                        abort_unless($member instanceof Model, 422, 'User not found.');
                        app(AddMemberAction::class)->handle($organization, $member, MemberRole::from($data['role']));
                    }),
            ])
            ->actions([
                Action::make('changeRole')
                    ->form([
                        Select::make('role')->options(collect(MemberRole::cases())->mapWithKeys(fn (MemberRole $role): array => [$role->value => $role->label()])->all())->required(),
                    ])
                    ->fillForm(fn (Model $record): array => ['role' => (string) data_get($record->getRelationValue('pivot'), 'role')])
                    ->action(function (Organization $organization, Model $record, array $data): void {
                        OrganizationResource::authorizeRecord($organization, 'organization.manage-members');
                        app(ChangeMemberRoleAction::class)->handle($organization, $record, MemberRole::from($data['role']));
                    })
                    ->visible(fn (Model $record): bool => (string) data_get($record->getRelationValue('pivot'), 'role') !== MemberRole::Owner->spatieRoleName()),
                Action::make('remove')
                    ->requiresConfirmation()
                    ->action(function (Organization $organization, Model $record): void {
                        OrganizationResource::authorizeRecord($organization, 'organization.manage-members');
                        app(RemoveMemberAction::class)->handle($organization, $record);
                    })
                    ->visible(fn (Model $record): bool => (string) data_get($record->getRelationValue('pivot'), 'role') !== MemberRole::Owner->spatieRoleName()),
            ]);
    }
}
