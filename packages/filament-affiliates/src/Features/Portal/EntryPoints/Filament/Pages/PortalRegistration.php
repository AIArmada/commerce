<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Features\Identity\Api\RegistrationApi;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord;
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\PublicHandle;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\InteractsWithAffiliate;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as FilamentRegister;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
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

        if (RegistrationApprovalMode::tryFrom($this->approvalMode) === null) {
            throw new InvalidArgumentException("Unknown affiliates registration approval mode [{$this->approvalMode}].");
        }

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
        // Pick only the user attributes explicitly; the password arrives
        // already hashed via the registration form's dehydrateStateUsing.
        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $this->createAffiliateForUser($user, $data);

        $guard = (string) config('authz.guards.0', 'web');

        $role = app(RoleAdministrationApi::class)->ensureRole('Affiliate', $guard, getPermissionsTeamId(), ['panel.affiliate']);
        $reference = EloquentRecordReference::fromModel($user);

        if ($reference === null) {
            throw new InvalidArgumentException('A persisted user is required.');
        }

        app(RoleAdministrationApi::class)->assignRole($reference, $role->id);

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
            'open' => __('Your account will be created with pending status and activated automatically after your first qualifying conversion.'),
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
                TextInput::make('handle')
                    ->label(__('Public Handle'))
                    ->helperText(__('Choose a public handle, or leave blank to have one assigned.'))
                    ->rules(['nullable', ...array_slice(PublicHandle::rules(), 1)])
                    ->visible(config('affiliates.handles.allow_custom', true))
                    ->dehydrated(config('affiliates.handles.allow_custom', true)),
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

        $exists = app(AffiliateDirectory::class)->findByCode($value) !== null;

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

        $exists = app(AffiliateDirectory::class)->findByCode($value)?->canBeAttributed === true;

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
        // ysfkaya/filament-phone-input is an optional integration: fall back
        // to a plain tel input when the package is not installed so portal
        // registration keeps working in lean merchant apps.
        if (! class_exists(PhoneInput::class)) {
            return TextInput::make('phone')
                ->label(__('Phone Number'))
                ->tel()
                ->required()
                ->maxLength(32);
        }

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

    protected function createAffiliateForUser(Model $user, array $data): AffiliateRecord
    {
        $owner = $user;

        if ((bool) config('affiliates.owner.enabled', false)) {
            $resolvedOwner = OwnerContext::resolve();

            if ($resolvedOwner instanceof Model) {
                $owner = $resolvedOwner;
            }
        }

        $affiliateData = [
            'name' => $data['affiliate_name'],
            'handle' => $data['handle'] ?? null,
            'contact_email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if (! empty($data['affiliate_code'])) {
            $affiliateData['code'] = $data['affiliate_code'];
        }

        $reference = EloquentRecordReference::fromModel($owner);

        if ($reference === null) {
            throw new InvalidArgumentException('A persisted owner is required.');
        }

        $cookie = request()->cookie(config('affiliates.cookies.name', 'affiliate_session'));

        return app(RegistrationApi::class)->register(
            $affiliateData,
            $reference,
            is_string($data['referral_code'] ?? null) ? $data['referral_code'] : null,
            is_string($cookie) ? $cookie : null,
        );
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
