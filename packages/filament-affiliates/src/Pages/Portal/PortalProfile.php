<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;

class PortalProfile extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.profile';

    public string $name = '';

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

        $validated = validator([
            'name' => $this->name,
            'contact_email' => $this->contactEmail,
            'website_url' => $this->websiteUrl,
            'payout_method_type' => $this->payoutMethodType,
            'payout_method_label' => $this->payoutMethodLabel,
            'payout_method_account_ref' => $this->payoutMethodAccountRef,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'payout_method_type' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (PayoutMethodType $type): string => $type->value,
                    PayoutMethodType::cases(),
                )),
            ],
            'payout_method_label' => ['required', 'string', 'max:255'],
            'payout_method_account_ref' => ['required', 'string', 'max:255'],
        ])->validate();

        $affiliate->update([
            'name' => $validated['name'],
            'contact_email' => $validated['contact_email'] !== '' ? $validated['contact_email'] : null,
            'website_url' => $validated['website_url'] !== '' ? $validated['website_url'] : null,
        ]);

        $defaultMethod = $affiliate->payoutMethods()->where('is_default', true)->first();

        if ($defaultMethod === null) {
            AffiliatePayoutMethod::query()->where('affiliate_id', $affiliate->getKey())->update(['is_default' => false]);

            AffiliatePayoutMethod::create([
                'affiliate_id' => $affiliate->getKey(),
                'type' => $validated['payout_method_type'],
                'details' => [
                    'label' => $validated['payout_method_label'],
                    'account_ref' => $validated['payout_method_account_ref'],
                ],
                'is_default' => true,
            ]);
        } else {
            $defaultMethod->update([
                'type' => $validated['payout_method_type'],
                'details' => [
                    'label' => $validated['payout_method_label'],
                    'account_ref' => $validated['payout_method_account_ref'],
                ],
                'is_default' => true,
            ]);
        }

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
