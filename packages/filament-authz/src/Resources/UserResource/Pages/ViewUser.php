<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\UserResource\Pages;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccountSnapshot;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentAffiliates\Features\Identity\Api\AffiliateAdminLinks;
use AIArmada\FilamentAuthz\Resources\UserResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return list<EditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        $user = $this->getRecord();
        $affiliate = $this->resolveAffiliate($user);
        $roleNames = $this->getUserRoleNames($user);
        $permissionNames = $this->getUserDirectPermissionNames($user);

        $components = [
            Section::make('User Details')
                ->columns(2)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email'),
                    TextEntry::make('created_at')
                        ->label('Member since')
                        ->dateTime(),
                    TextEntry::make('email_verified_at')
                        ->label('Email verified')
                        ->dateTime()
                        ->placeholder('Not verified'),
                ]),
        ];

        if ($affiliate !== null) {
            $components[] = Section::make('Affiliate Account')
                ->columns(2)
                ->schema([
                    TextEntry::make('affiliate_code')
                        ->label('Code')
                        ->state($affiliate->code),
                    TextEntry::make('affiliate_status')
                        ->label('Status')
                        ->state($affiliate->status)
                        ->badge(),
                    TextEntry::make('affiliate_commission')
                        ->label('Commission')
                        ->state(function () use ($affiliate): string {
                            return $affiliate->commissionType === 'percentage'
                                ? number_format($affiliate->commissionRate / 100, 2) . ' %'
                                : MoneyFormatter::formatMinor($affiliate->commissionRate, $affiliate->currency);
                        }),
                    TextEntry::make('affiliate_link')
                        ->label('Affiliate')
                        ->state('View Affiliate Profile')
                        ->url(
                            interface_exists(AffiliateAdminLinks::class) && app()->bound(AffiliateAdminLinks::class)
                                ? app(AffiliateAdminLinks::class)->view($affiliate->id)
                                : null,
                            shouldOpenInNewTab: true,
                        )
                        ->html(),
                ]);
        }

        $components[] = Section::make('Roles & Permissions')
            ->schema([
                TextEntry::make('roles')
                    ->label('Roles')
                    ->state($roleNames)
                    ->badge()
                    ->color('primary'),
                TextEntry::make('permissions')
                    ->label('Direct Permissions')
                    ->state($permissionNames)
                    ->badge()
                    ->color('gray')
                    ->visible($permissionNames !== []),
            ]);

        return $schema->components($components);
    }

    /**
     * @return list<string>
     */
    private function getUserRoleNames(Model $user): array
    {
        if (! method_exists($user, 'roles')) {
            return [];
        }

        /** @var Collection<int, Model> $roles */
        $roles = $user->getRelationValue('roles');

        return $roles->pluck('name')->toArray();
    }

    /**
     * @return list<string>
     */
    private function getUserDirectPermissionNames(Model $user): array
    {
        if (! method_exists($user, 'getDirectPermissions')) {
            return [];
        }

        /** @var Collection<int, Model> $permissions */
        $permissions = $user->getDirectPermissions();

        return $permissions->pluck('name')->toArray();
    }

    private function resolveAffiliate(Model $user): ?AffiliateAccountSnapshot
    {
        if (! interface_exists(AffiliateReferenceReader::class) || ! app()->bound(AffiliateReferenceReader::class)) {
            return null;
        }

        try {
            return app(AffiliateReferenceReader::class)->forOwner($user->getMorphClass(), (string) $user->getKey());
        } catch (NoCurrentOwnerException) {
            return null;
        }
    }
}
