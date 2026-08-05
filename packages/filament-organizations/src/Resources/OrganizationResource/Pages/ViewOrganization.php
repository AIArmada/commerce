<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Organizations\Actions\ArchiveOrganizationAction;
use AIArmada\Organizations\Actions\MakeOrganizationPrivateAction;
use AIArmada\Organizations\Actions\MakeOrganizationPublicAction;
use AIArmada\Organizations\Actions\RestoreOrganizationAction;
use AIArmada\Organizations\Actions\SuspendOrganizationAction;
use AIArmada\Organizations\Actions\TransferOrganizationOwnershipAction;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class ViewOrganization extends ViewRecord
{
    protected static string $resource = OrganizationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organization')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('visibility')->badge(),
                    TextEntry::make('description')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('makePublic')
                ->label('Make public')
                ->requiresConfirmation()
                ->visible(fn (Organization $record): bool => $record->visibility !== OrganizationVisibility::Public)
                ->action(function (Organization $record): void {
                    $actor = Filament::auth()->user();
                    abort_unless($actor instanceof Model, 403);
                    app(MakeOrganizationPublicAction::class)->handle($record, $actor);
                }),
            Action::make('makePrivate')
                ->label('Make private')
                ->requiresConfirmation()
                ->visible(fn (Organization $record): bool => $record->visibility !== OrganizationVisibility::Private)
                ->action(function (Organization $record): void {
                    $actor = Filament::auth()->user();
                    abort_unless($actor instanceof Model, 403);
                    app(MakeOrganizationPrivateAction::class)->handle($record, $actor);
                }),
            Action::make('suspend')
                ->requiresConfirmation()
                ->visible(fn (Organization $record): bool => $record->status === OrganizationStatus::Active)
                ->action(function (Organization $record): void {
                    $actor = Filament::auth()->user();
                    abort_unless($actor instanceof Model, 403);
                    app(SuspendOrganizationAction::class)->handle($record, $actor);
                }),
            Action::make('archive')
                ->requiresConfirmation()
                ->visible(fn (Organization $record): bool => $record->status !== OrganizationStatus::Archived)
                ->action(function (Organization $record): void {
                    $actor = Filament::auth()->user();
                    abort_unless($actor instanceof Model, 403);
                    app(ArchiveOrganizationAction::class)->handle($record, $actor);
                }),
            Action::make('restore')
                ->visible(fn (Organization $record): bool => $record->status !== OrganizationStatus::Active)
                ->action(function (Organization $record): void {
                    $actor = Filament::auth()->user();
                    abort_unless($actor instanceof Model, 403);
                    app(RestoreOrganizationAction::class)->handle($record, $actor);
                }),
            Action::make('transferOwnership')
                ->label('Transfer ownership')
                ->requiresConfirmation()
                ->form(fn (Organization $record): array => [
                    Select::make('user_id')
                        ->label('New owner')
                        ->options(fn (): array => $record->members()
                            ->whereKeyNot(Filament::auth()->id())
                            ->get()
                            ->mapWithKeys(fn (Model $member): array => [
                                (string) $member->getKey() => (string) ($member->getAttribute('name') ?? $member->getAttribute('email') ?? $member->getKey()),
                            ])
                            ->all())
                        ->required(),
                ])
                ->action(function (Organization $record, array $data): void {
                    $actor = Filament::auth()->user();
                    abort_unless($actor instanceof Model, 403);
                    OrganizationResource::authorizeRecord($record, 'organization.transfer-ownership');
                    $newOwner = $record->members()->whereKey($data['user_id'])->first();
                    abort_unless($newOwner instanceof Model, 404);
                    app(TransferOrganizationOwnershipAction::class)->handle($record, $actor, $newOwner);
                }),
        ];
    }
}
