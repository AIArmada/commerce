<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Identity\Api\PortalProfileAdministration;
use AIArmada\Affiliates\Features\Identity\Api\PortalProfileInput;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;

class PortalProfile extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.profile';

    public string $name = '';

    public string $handle = '';

    public string $contactEmail = '';

    public string $websiteUrl = '';

    public string $payoutMethodType = '';

    public string $payoutMethodLabel = '';

    public string $payoutMethodAccountRef = '';

    public function mount(): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return;
        }

        $this->name = (string) ($affiliate->name ?? '');
        $this->handle = $affiliate->handle;
        $this->contactEmail = (string) ($affiliate->contact_email ?? '');
        $this->websiteUrl = (string) ($affiliate->website_url ?? '');

        $defaultMethod = $affiliate->payoutMethods()->where('is_default', true)->first();

        if (! $defaultMethod) {
            return;
        }

        $details = is_array($defaultMethod->details) ? $defaultMethod->details : [];

        $this->payoutMethodType = (string) $defaultMethod->type->value;
        $this->payoutMethodLabel = (string) ($details['label'] ?? '');
        $this->payoutMethodAccountRef = (string) ($details['account_ref'] ?? '');
    }

    public static function getNavigationLabel(): string
    {
        return __('Profile');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Profile & Payout Setup');
    }

    public function saveProfile(): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No Affiliate Account'))
                ->body(__('You do not have an affiliate account yet.'))
                ->danger()
                ->send();

            return;
        }

        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException('An authenticated affiliate account is required.');
        }

        app(PortalProfileAdministration::class)->save(
            (string) $affiliate->getKey(),
            $user->getMorphClass(),
            (string) $user->getKey(),
            is_string($user->email ?? null) ? $user->email : null,
            new PortalProfileInput(
                $this->name,
                $this->handle,
                $this->contactEmail,
                $this->websiteUrl,
                $this->payoutMethodType,
                $this->payoutMethodLabel,
                $this->payoutMethodAccountRef,
            ),
        );

        Notification::make()
            ->title(__('Profile updated'))
            ->body(__('Your profile and payout setup have been saved.'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'payoutMethodOptions' => collect(PayoutMethodType::cases())
                ->mapWithKeys(fn (PayoutMethodType $type): array => [$type->value => $type->label()])
                ->all(),
        ];
    }
}
