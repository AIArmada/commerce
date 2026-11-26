<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Identity\Api\RegistrationApi;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalRegistration;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
    expect($page->getSubheading())->toBeNull();

    $approvalMode->setValue($page, 'open');
    expect($page->getSubheading())->toBe('Your account will be created with pending status and activated automatically after your first qualifying conversion.');

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

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $page = new PortalRegistration;

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

    $affiliate = Affiliate::query()->sole();

    expect($createdUser)->toBeInstanceOf(Model::class)
        ->and($affiliate->owner_id)->toBe($tenantOwner->getKey())
        ->and($affiliate->owner_id)->not->toBe($createdUser->getKey());
});

it('blocks register() and sends a danger notification when disabled', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);
    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setValue($page, false);

    forget_filament_notifications();

    expect($page->register())->toBeNull();

    $notifications = sent_filament_notifications();
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

    forget_filament_notifications();

    $method = $reflection->getMethod('afterRegister');
    $method->invoke($page);

    $notifications = sent_filament_notifications();
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

    expect($affiliateName->invoke($page))->toBeInstanceOf(TextInput::class);
});

it('uses configured commission terms even when forged terms are passed to the registration API', function (): void {
    config()->set('affiliates.owner.enabled', false);
    config()->set('affiliates.registration.enabled', true);
    config()->set('affiliates.registration.default_commission_type', 'percentage');
    config()->set('affiliates.registration.default_commission_rate', 750);
    config()->set('affiliates.currency.default', 'MYR');

    $user = User::factory()->create();
    $reference = EloquentRecordReference::fromModel($user);
    $record = app(RegistrationApi::class)->register([
        'name' => 'Public Applicant',
        'commission_type' => 'fixed',
        'commission_rate' => 999999,
        'currency' => 'USD',
        'parent_affiliate_id' => (string) Str::uuid(),
        'metadata' => ['forged' => true],
    ], $reference, null, null);

    $affiliate = Affiliate::query()->findOrFail($record->id);

    expect($affiliate->commission_type->value)->toBe('percentage')
        ->and($affiliate->commission_rate)->toBe(750)
        ->and($affiliate->currency)->toBe('MYR')
        ->and($affiliate->parent_affiliate_id)->toBeNull()
        ->and($affiliate->metadata)->toBe([]);
});

it('enforces registration availability and schema limits at the API boundary', function (): void {
    $user = User::factory()->create();
    $reference = EloquentRecordReference::fromModel($user);
    $registration = app(RegistrationApi::class);

    config()->set('affiliates.registration.enabled', false);

    expect(fn () => $registration->register(['name' => 'Applicant'], $reference, null, null))
        ->toThrow(InvalidArgumentException::class, 'Affiliate registration is disabled.');

    config()->set('affiliates.registration.enabled', true);

    expect(fn () => $registration->register(['name' => 'Applicant', 'code' => str_repeat('A', 65)], $reference, null, null))
        ->toThrow(ValidationException::class)
        ->and(Affiliate::query()->count())->toBe(0);
});

it('rejects a differently cased existing code with a validation error', function (): void {
    config()->set('affiliates.owner.enabled', false);
    config()->set('affiliates.registration.enabled', true);

    $user = User::factory()->create();
    $reference = EloquentRecordReference::fromModel($user);
    $registration = app(RegistrationApi::class);

    $registration->register(['name' => 'Original Applicant', 'code' => 'Public-Code'], $reference, null, null);

    expect(fn () => $registration->register(['name' => 'Other Applicant', 'code' => 'PUBLIC-CODE'], $reference, null, null))
        ->toThrow(ValidationException::class, 'The affiliate code has already been taken.')
        ->and(Affiliate::query()->count())->toBe(1);
});
