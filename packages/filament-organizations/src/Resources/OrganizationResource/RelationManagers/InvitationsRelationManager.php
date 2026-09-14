<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\RelationManagers;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Actions\RevokeInvitationAction;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
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
                    ->action(function (InvitationsRelationManager $livewire, array $data): void {
                        $organization = self::ownerOrganization($livewire);
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);
                        OrganizationResource::authorizeRecord($organization, 'organization.manage-members');
                        app(InviteMemberAction::class)->handle($organization, $data['email'], MemberRole::from($data['role']), $actor);
                    }),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MembershipInvitation $record): bool => $record->status === InvitationStatus::Pending)
                    ->action(function (InvitationsRelationManager $livewire, MembershipInvitation $record): void {
                        $organization = self::ownerOrganization($livewire);

                        // Defense in depth: the row must belong to this
                        // organization, not just to the current table view.
                        abort_unless(
                            $record->subject_type === $organization->getMorphClass()
                            && (string) $record->subject_id === (string) $organization->getKey(),
                            404,
                        );

                        OrganizationResource::authorizeRecord($organization, 'organization.manage-members');

                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof Model, 403);

                        app(RevokeInvitationAction::class)->handle($record, $actor);
                    }),
            ]);
    }

    private static function ownerOrganization(InvitationsRelationManager $livewire): Organization
    {
        $owner = $livewire->getOwnerRecord();

        abort_unless($owner instanceof Organization, 404);

        return $owner;
    }
}
