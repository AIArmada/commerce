<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
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
use Spatie\Permission\PermissionRegistrar;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;

class PortalRegistration extends FilamentRegister
{
    use InteractsWithAffiliate;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.registration';

    protected bool $registrationEnabled = true;

    protected string $approvalMode = 'auto';

    public bool $isCodeChecking = false;

    public bool $isCodeAvailable = false;

    public string $codeAvailabilityMessage = '';

    public bool $isReferralChecking = false;

    public bool $isReferralValid = false;

    public string $referralMessage = '';

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
        unset($userData['affiliate_name'], $userData['phone'], $userData['referral_code'], $userData['affiliate_code']);

        $user = $this->getUserModel()::create($userData);

        $this->createAffiliateForUser($user, $data);

        $guard = (string) config('filament-authz.guards.0', 'web');

        $permission = Permission::findOrCreate('panel.affiliate', $guard);
        $role = Role::findOrCreate('Affiliate', $guard);
        $role->givePermissionTo($permission);

        // @phpstan-ignore-next-line — assignRole comes from Spatie's HasRoles trait on the user model
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
            'auto' => null,
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
                $this->getPhoneFormComponent(),
                $this->getAffiliateCodeFormComponent(),
                $this->getReferralCodeFormComponent(),
            ]);
    }

    protected function getAffiliateCodeFormComponent(): Component
    {
        return TextInput::make('affiliate_code')
            ->label(__('Affiliate Code'))
            ->helperText(function (): string {
                if ($this->isCodeChecking) {
                    return __('Checking availability...');
                }

                if ($this->codeAvailabilityMessage !== '') {
                    return $this->codeAvailabilityMessage;
                }

                return __('Choose your own affiliate code, or leave blank for auto-generated.');
            })
            ->maxLength(255)
            ->alphaDash()
            ->live(debounce: 500)
            ->afterStateUpdated(function (?string $state): void {
                $this->checkCodeAvailability($state);
            })
            ->suffixIcon(function (): ?string {
                if ($this->isCodeChecking) {
                    return 'heroicon-m-arrow-path';
                }

                if ($this->codeAvailabilityMessage !== '') {
                    return $this->isCodeAvailable
                        ? 'heroicon-m-check-circle'
                        : 'heroicon-m-x-circle';
                }

                return null;
            })
            ->suffixIconColor(function (): ?string {
                if ($this->isCodeChecking) {
                    return 'gray';
                }

                if ($this->codeAvailabilityMessage !== '') {
                    return $this->isCodeAvailable ? 'success' : 'danger';
                }

                return null;
            })
            ->extraAttributes(
                fn (): array => $this->isCodeChecking
                ? ['style' => '--affiliate-code-checking: 1;']
                : [],
            )
            ->unique(
                table: config('affiliates.database.tables.affiliates', 'affiliate_affiliates'),
                column: 'code',
            );
    }

    public function checkCodeAvailability(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->isCodeChecking = false;
            $this->codeAvailabilityMessage = '';
            $this->isCodeAvailable = false;

            return;
        }

        $this->isCodeChecking = true;
        $this->codeAvailabilityMessage = '';

        $exists = Affiliate::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($value)])
            ->exists();

        $this->isCodeChecking = false;
        $this->isCodeAvailable = ! $exists;
        $this->codeAvailabilityMessage = $exists
            ? __('This affiliate code is not available.')
            : __('This affiliate code is available.');
    }

    protected function getReferralCodeFormComponent(): Component
    {
        return TextInput::make('referral_code')
            ->label(__('Referral Code'))
            ->helperText(function (): string {
                if ($this->isReferralChecking) {
                    return __('Checking referrer...');
                }

                if ($this->referralMessage !== '') {
                    return $this->referralMessage;
                }

                return __('If you were referred by an affiliate, enter their code here.');
            })
            ->maxLength(255)
            ->live(debounce: 500)
            ->afterStateUpdated(function (?string $state): void {
                $this->checkReferralCode($state);
            })
            ->suffixIcon(function (): ?string {
                if ($this->isReferralChecking) {
                    return 'heroicon-m-arrow-path';
                }

                if ($this->referralMessage !== '') {
                    return $this->isReferralValid
                        ? 'heroicon-m-check-circle'
                        : 'heroicon-m-x-circle';
                }

                return null;
            })
            ->suffixIconColor(function (): ?string {
                if ($this->isReferralChecking) {
                    return 'gray';
                }

                if ($this->referralMessage !== '') {
                    return $this->isReferralValid ? 'success' : 'danger';
                }

                return null;
            })
            ->extraAttributes(
                fn (): array => $this->isReferralChecking
                ? ['style' => '--affiliate-code-checking: 1;']
                : [],
            );
    }

    public function checkReferralCode(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->isReferralChecking = false;
            $this->referralMessage = '';
            $this->isReferralValid = false;

            return;
        }

        $this->isReferralChecking = true;
        $this->referralMessage = '';

        $exists = Affiliate::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($value)])
            ->exists();

        $this->isReferralChecking = false;
        $this->isReferralValid = $exists;
        $this->referralMessage = $exists
            ? __('Valid referrer found.')
            : __('No affiliate found with this code.');
    }

    protected function getAffiliateNameFormComponent(): Component
    {
        return TextInput::make('affiliate_name')
            ->label(__('Affiliate / Business Name'))
            ->required()
            ->maxLength(255);
    }

    protected function getPhoneFormComponent(): Component
    {
        return PhoneInput::make('phone')
            ->label(__('Phone Number'))
            ->required()
            ->initialCountry('MY')
            ->defaultCountry('MY')
            ->displayNumberFormat(PhoneInputNumberType::NATIONAL)
            ->inputNumberFormat(PhoneInputNumberType::E164)
            ->validateFor('MY')
            ->disableLookup()
            ->nationalMode();
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
            'phone' => $data['phone'] ?? null,
        ];

        if (! empty($data['affiliate_code'])) {
            $affiliateData['code'] = $data['affiliate_code'];
        }

        $referrer = null;

        if (! empty($data['referral_code'])) {
            $referrer = Affiliate::query()
                ->where('code', $data['referral_code'])
                ->first();
        }

        if (! $referrer) {
            $cookieName = config('affiliates.cookies.name', 'affiliate_session');
            $cookieValue = request()->cookie($cookieName) ?? ($_COOKIE[$cookieName] ?? null);

            if ($cookieValue) {
                $referrer = app('affiliates')->findAffiliateByCookie($cookieValue);
            }
        }

        if ($referrer) {
            $affiliateData['parent_affiliate_id'] = $referrer->id;
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
