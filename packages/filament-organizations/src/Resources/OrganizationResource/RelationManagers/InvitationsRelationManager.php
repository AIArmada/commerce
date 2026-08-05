<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\RelationManagers;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Membership\Actions\InviteMemberAction;
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

final class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('expires_at')->dateTime(),
                TextColumn::make('accepted_at')->dateTime()->placeholder('-'),
                TextColumn::make('revoked_at')->dateTime()->placeholder('-'),
            ])
            ->headerActions([
                Action::make('invite')
                    ->form([
                        TextInput::make('email')->email()->required(),
                        Select::make('role')->options(collect(MemberRole::cases())->mapWithKeys(fn (MemberRole $role): array => [$role->value => $role->label()])->all())->default(MemberRole::Viewer->value)->required(),
                    ])
                    ->action(function (Organization $organization, array $data): void {
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);
                        OrganizationResource::authorizeRecord($organization, 'organization.manage-members');
                        app(InviteMemberAction::class)->handle($organization, $data['email'], MemberRole::from($data['role']), $actor);
                    }),
            ]);
    }
}
