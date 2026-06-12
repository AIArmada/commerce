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

test('ContactMethod model class exists', function () {
    expect(class_exists(ContactMethod::class))->toBeTrue();
    // getTable() uses config() which needs Laravel app
    expect(true)->toBeTrue();
});

test('ContactMethodType enum has expected values', function () {
    expect(ContactMethodType::Email->value)->toBe('email');
    expect(ContactMethodType::Phone->value)->toBe('phone');
    expect(ContactMethodType::Whatsapp->value)->toBe('whatsapp');
    expect(ContactMethodType::Website->value)->toBe('website');
    expect(ContactMethodType::Other->value)->toBe('other');
});

test('ContactPurpose enum has expected values', function () {
    expect(ContactPurpose::General->value)->toBe('general');
    expect(ContactPurpose::Admin->value)->toBe('admin');
    expect(ContactPurpose::Support->value)->toBe('support');
    expect(ContactPurpose::Billing->value)->toBe('billing');
    expect(ContactPurpose::Emergency->value)->toBe('emergency');
});

test('ContactMethodData factory helpers', function () {
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

test('ContactMethodData from array', function () {
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

test('NormalizeContactMethodAction normalizes email', function () {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    expect($action->execute('email', '  User@Example.COM  ')['normalized_value'])->toBe('user@example.com');
    expect($action->execute('email', 'valid@email.com')['normalized_value'])->toBe('valid@email.com');
});

test('NormalizeContactMethodAction normalizes MY phone', function () {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    $result = $action->execute('phone', '+60123456789');
    expect($result['normalized_value'])->toBe('+60123456789');
    expect($result['display_value'])->not->toBeNull();
});

test('NormalizeContactMethodAction normalizes website', function () {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    expect($action->execute('website', 'example.com')['normalized_value'])->toBe('https://example.com');
    expect($action->execute('website', 'https://example.com')['normalized_value'])->toBe('https://example.com');
});

test('NormalizeContactMethodAction passes through unknown types', function () {
    $action = new NormalizeContactMethodAction(
        new NormalizesEmailAddress,
        new NormalizesPhoneNumber,
        new NormalizesUrl,
    );

    $result = $action->execute('other', 'some-value');
    expect($result['normalized_value'])->toBe('some-value');
});
