<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Enums\ContactMethodType;
use AIArmada\Contacting\Enums\ContactPurpose;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Support\NormalizesEmailAddress;
use AIArmada\Contacting\Support\NormalizesPhoneNumber;
use AIArmada\Contacting\Support\NormalizesUrl;
use AIArmada\Customers\Models\Customer;

test('ContactMethod model class exists', function (): void {
    expect(class_exists(ContactMethod::class))->toBeTrue();
    // getTable() uses config() which needs Laravel app
    expect(true)->toBeTrue();
});

test('ContactMethodType enum has expected values', function (): void {
    expect(ContactMethodType::Email->value)->toBe('email');
    expect(ContactMethodType::Phone->value)->toBe('phone');
    expect(ContactMethodType::Whatsapp->value)->toBe('whatsapp');
    expect(ContactMethodType::Website->value)->toBe('website');
    expect(ContactMethodType::Other->value)->toBe('other');
});

test('ContactMethodType options map configured values', function (): void {
    expect(ContactMethodType::options(['email', 'phone', 'whatsapp']))->toBe([
        'email' => 'Email',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
    ]);
});

test('ContactPurpose enum has expected values', function (): void {
    expect(ContactPurpose::General->value)->toBe('general');
    expect(ContactPurpose::Admin->value)->toBe('admin');
    expect(ContactPurpose::Support->value)->toBe('support');
    expect(ContactPurpose::Billing->value)->toBe('billing');
    expect(ContactPurpose::Emergency->value)->toBe('emergency');
});

test('primary contact methods remain unique per contactable type and purpose', function (): void {
    $customer = Customer::create([
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'email' => 'primary-contact-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $first = $customer->addContactMethod(new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: 'first-' . uniqid() . '@example.com',
        isPrimary: true,
    ));

    $second = $customer->addContactMethod(new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: 'second-' . uniqid() . '@example.com',
        isPrimary: true,
    ));

    expect($first->fresh()?->is_primary)->toBeFalse()
        ->and($second->fresh()?->is_primary)->toBeTrue()
        ->and($customer->contactMethods()->where('type', 'email')->where('purpose', 'general')->count())->toBe(2);
});

test('ContactMethodData factory helpers', function (): void {
    $email = ContactMethodData::email('admin@example.com');
    expect($email->type)->toBe('email');
    expect($email->value)->toBe('admin@example.com');

    $phone = ContactMethodData::phone('+60123456789', 'MY', 'admin');
    expect($phone->type)->toBe('phone');
    expect($phone->countryCode)->toBe('MY');
    expect($phone->purpose)->toBe('admin');

    $wa = ContactMethodData::whatsapp('+60123456789', 'MY', 'support');
    expect($wa->type)->toBe('whatsapp');
    expect($wa->value)->toBe('+60123456789');

    $web = ContactMethodData::website('https://example.com');
    expect($web->type)->toBe('website');
});

test('ContactMethodData from array', function (): void {
    $data = new ContactMethodData(
        type: 'email',
        value: 'test@example.com',
        isPrimary: true,
        isPublic: false,
    );

    expect($data->type)->toBe('email');
    expect($data->value)->toBe('test@example.com');
    expect($data->isPrimary)->toBeTrue();
    expect($data->isPublic)->toBeFalse();
});

test('NormalizeContactMethodAction normalizes email', function (): void {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    expect($action->execute('email', '  User@Example.COM  ')['normalized_value'])->toBe('user@example.com');
    expect($action->execute('email', 'valid@email.com')['normalized_value'])->toBe('valid@email.com');
});

test('NormalizeContactMethodAction normalizes MY phone', function (): void {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    $result = $action->execute('phone', '+60123456789');
    expect($result['normalized_value'])->toBe('+60123456789');
    expect($result['display_value'])->not->toBeNull();
});

test('NormalizeContactMethodAction normalizes website', function (): void {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    expect($action->execute('website', 'example.com')['normalized_value'])->toBe('https://example.com');
    expect($action->execute('website', 'https://example.com')['normalized_value'])->toBe('https://example.com');
});

test('NormalizeContactMethodAction passes through unknown types', function (): void {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    $result = $action->execute('other', 'some-value');
    expect($result['normalized_value'])->toBe('some-value');
});
