<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Concerns\InteractsWithAffiliate;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as FilamentRegister;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class PortalRegistration extends FilamentRegister
{
    use InteractsWithAffiliate;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.registration';

    protected bool $registrationEnabled;

    protected string $approvalMode;

    public function mount(): void
    {
        $this->registrationEnabled = (bool) config('affiliates.registration.enabled', true);
        $this->approvalMode = (string) config('affiliates.registration.approval_mode', 'admin');

        if (! $this->registrationEnabled) {
            $this->redirect(filament()->getLoginUrl());

            return;
        }

        parent::mount();
    }

    public function register(): ?RegistrationResponse
    {
        if (! $this->registrationEnabled) {
            Notification::make()
                ->title(__('Registration Disabled'))
                ->body(__('Affiliate registration is currently not available.'))
                ->danger()
                ->send();

            return null;
        }

        return parent::register();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        $userData = $data;
        unset($userData['affiliate_name'], $userData['website_url']);

        $user = $this->getUserModel()::create($userData);

        $this->createAffiliateForUser($user, $data);

        return $user;
    }

    public function getHeading(): string
    {
        return __('Register as an Affiliate');
    }

    public function getSubheading(): ?string
    {
        if (! $this->registrationEnabled) {
            return __('Registration is currently closed.');
        }

        return match ($this->approvalMode) {
            'auto' => __('Your affiliate account will be automatically activated.'),
            'open' => __('Your account will be created with pending status.'),
            'admin' => __('Your application will be reviewed by an administrator.'),
            default => null,
        };
    }

    public function isRegistrationEnabled(): bool
    {
        return $this->registrationEnabled;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getAffiliateNameFormComponent(),
                $this->getWebsiteUrlFormComponent(),
                $this->getReferralCodeFormComponent(),
            ]);
    }

    protected function getReferralCodeFormComponent(): Component
    {
        return TextInput::make('referral_code')
            ->label(__('Referral Code (optional)'))
            ->helperText(__('If you were referred by an affiliate, enter their code here.'))
            ->maxLength(255)
            ->exists(
                table: config('affiliates.database.tables.affiliates', 'affiliate_affiliates'),
                column: 'code',
            );
    }

    protected function getAffiliateNameFormComponent(): Component
    {
        return TextInput::make('affiliate_name')
            ->label(__('Affiliate/Business Name'))
            ->required()
            ->maxLength(255);
    }

    protected function getWebsiteUrlFormComponent(): Component
    {
        return TextInput::make('website_url')
            ->label(__('Website URL'))
            ->url()
            ->maxLength(255);
    }

    protected function createAffiliateForUser(Model $user, array $data): Affiliate
    {
        $registrationService = app(AffiliateRegistrationService::class);
        $owner = $user;

        if ((bool) config('affiliates.owner.enabled', false)) {
            $resolvedOwner = OwnerContext::resolve();

            if ($resolvedOwner instanceof Model) {
                $owner = $resolvedOwner;
            }
        }

        $affiliateData = [
            'name' => $data['affiliate_name'],
            'contact_email' => $data['email'],
            'website_url' => $data['website_url'] ?? null,
        ];

        $referrer = null;

        if (! empty($data['referral_code'])) {
            $referrer = Affiliate::query()
                ->where('code', $data['referral_code'])
                ->first();

            if ($referrer) {
                $affiliateData['parent_affiliate_id'] = $referrer->id;
            }
        }

        $affiliate = $registrationService->register($affiliateData, $owner);

        if ($referrer) {
            $networkService = app(NetworkService::class);
            $networkService->addToNetwork($affiliate, $referrer);
        }

        return $affiliate;
    }

    public function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label(__('Register as Affiliate'))
            ->submit('register');
    }

    protected function afterRegister(): void
    {
        $message = match ($this->approvalMode) {
            'auto' => __('Your affiliate account has been activated. You can start sharing links!'),
            'open' => __('Your affiliate account has been created. It is currently pending activation.'),
            'admin' => __('Your affiliate application has been submitted. We will notify you once it is reviewed.'),
            default => __('Your affiliate account has been created.'),
        };

        Notification::make()
            ->title(__('Registration Successful'))
            ->body($message)
            ->success()
            ->send();
    }
}
