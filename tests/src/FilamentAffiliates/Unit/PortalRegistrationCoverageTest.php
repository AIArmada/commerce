<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Affiliate::query()->delete();
});

it('renders the correct subheading for each approval mode', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');

    $approvalMode->setValue($page, 'auto');
    expect($page->getSubheading())->toBe('Your affiliate account will be automatically activated.');

    $approvalMode->setValue($page, 'open');
    expect($page->getSubheading())->toBe('Your account will be created with pending status.');

    $approvalMode->setValue($page, 'admin');
    expect($page->getSubheading())->toBe('Your application will be reviewed by an administrator.');

    $approvalMode->setValue($page, 'unknown');
    expect($page->getSubheading())->toBeNull();
});

it('returns a closed subheading when registration is disabled', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, false);

    expect($page->getSubheading())->toBe('Registration is currently closed.');
});

it('creates an affiliate through the registration service during registration handling', function (): void {
    $page = new PortalRegistration;

    $captured = ['payload' => null, 'user' => null];

    app()->instance(AffiliateRegistrationService::class, new class($captured)
    {
        /** @var array{payload:mixed, user:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        /**
         * @param  array<string, mixed>  $data
         */
        public function register(array $data, $user): Affiliate
        {
            $this->captured['payload'] = $data;
            $this->captured['user'] = $user;

            return Affiliate::create([
                'code' => 'REG-' . Str::uuid(),
                'name' => $data['name'],
                'status' => Active::class,
                'commission_type' => 'percentage',
                'commission_rate' => 500,
                'currency' => 'USD',
                'owner_type' => $user->getMorphClass(),
                'owner_id' => (string) $user->getKey(),
            ]);
        }
    });

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');
    $approvalMode->setValue($page, 'admin');

    $method = $reflection->getMethod('handleRegistration');

    $user = $method->invoke($page, [
        'name' => 'Portal Register User',
        'email' => 'portal-register-user@example.com',
        'password' => 'secret',
        'affiliate_name' => 'My Affiliate',
        'website_url' => 'https://example.com',
    ]);

    expect($user)->toBeInstanceOf(Model::class)
        ->and($user->email)->toBe('portal-register-user@example.com');

    $affiliate = Affiliate::query()->first();
    expect($affiliate)->not->toBeNull()
        ->and($affiliate->name)->toBe('My Affiliate');
});

it('uses resolved owner context for registration owner when owner mode is enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);

    $tenantOwner = User::create([
        'name' => 'Tenant Owner',
        'email' => 'tenant-owner-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($tenantOwner) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $page = new PortalRegistration;

    $captured = ['owner' => null, 'created_user' => null];

    app()->instance(AffiliateRegistrationService::class, new class($captured)
    {
        /** @var array{owner:mixed, created_user:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        /**
         * @param  array<string, mixed>  $data
         */
        public function register(array $data, $owner): Affiliate
        {
            $this->captured['owner'] = $owner;

            return Affiliate::create([
                'code' => 'REG-' . Str::uuid(),
                'name' => $data['name'],
                'status' => Active::class,
                'commission_type' => 'percentage',
                'commission_rate' => 500,
                'currency' => 'USD',
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
            ]);
        }
    });

    $reflection = new ReflectionClass($page);
    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');
    $approvalMode->setValue($page, 'admin');

    $method = $reflection->getMethod('handleRegistration');
    $createdUser = $method->invoke($page, [
        'name' => 'Owner Scoped User',
        'email' => 'owner-scoped-user-' . Str::uuid() . '@example.com',
        'password' => 'secret',
        'affiliate_name' => 'Owner Scoped Affiliate',
        'website_url' => 'https://example.com',
    ]);

    expect($createdUser)->toBeInstanceOf(Model::class)
        ->and($captured['owner'])->not->toBeNull()
        ->and($captured['owner']->getKey())->toBe($tenantOwner->getKey())
        ->and($captured['owner']->getKey())->not->toBe($createdUser->getKey());
});

it('blocks register() and sends a danger notification when disabled', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);
    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, false);

    session()->forget('filament.notifications');

    expect($page->register())->toBeNull();

    $notifications = session('filament.notifications');
    expect($notifications)->toBeArray()->not->toBeEmpty();

    $first = $notifications[0] ?? null;
    expect($first)
        ->toBeArray()
        ->and($first['title'] ?? null)->toBe((string) __('Registration Disabled'))
        ->and($first['status'] ?? null)->toBe('danger');
});

it('afterRegister() sends a success notification with mode-specific message', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');
    $approvalMode->setValue($page, 'open');

    session()->forget('filament.notifications');

    $method = $reflection->getMethod('afterRegister');
    $method->invoke($page);

    $notifications = session('filament.notifications');
    expect($notifications)->toBeArray()->not->toBeEmpty();

    $first = $notifications[0] ?? null;
    expect($first)
        ->toBeArray()
        ->and($first['title'] ?? null)->toBe((string) __('Registration Successful'))
        ->and($first['status'] ?? null)->toBe('success')
        ->and($first['body'] ?? null)->toBe((string) __('Your affiliate account has been created. It is currently pending activation.'));
});

it('exposes a register action and custom affiliate form components', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, true);

    expect($page->getHeading())->toBe('Register as an Affiliate')
        ->and($page->isRegistrationEnabled())->toBeTrue();

    $action = $page->getRegisterFormAction();
    expect(method_exists($action, 'getName') ? $action->getName() : null)->toBe('register');

    $affiliateName = $reflection->getMethod('getAffiliateNameFormComponent');

    $websiteUrl = $reflection->getMethod('getWebsiteUrlFormComponent');

    expect($affiliateName->invoke($page))->toBeInstanceOf(TextInput::class)
        ->and($websiteUrl->invoke($page))->toBeInstanceOf(TextInput::class);
});
