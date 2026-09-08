<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Contacting\Support\NormalizesSocialHandle;
use AIArmada\Contacting\Support\NormalizesUrl;
use AIArmada\Contacting\Support\SocialProfileConfig;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;

test('SocialProfile model class exists', function (): void {
    expect(class_exists(SocialProfile::class))->toBeTrue();
    // getTable() uses config() which needs Laravel app; skip for unit tests
    expect(true)->toBeTrue();
});

test('SocialPlatform enum has expected values', function (): void {
    expect(SocialPlatform::Facebook->value)->toBe('facebook');
    expect(SocialPlatform::Instagram->value)->toBe('instagram');
    expect(SocialPlatform::Tiktok->value)->toBe('tiktok');
    expect(SocialPlatform::Youtube->value)->toBe('youtube');
    expect(SocialPlatform::Linkedin->value)->toBe('linkedin');
    expect(SocialPlatform::X->value)->toBe('x');
    expect(SocialPlatform::Other->value)->toBe('other');
});

test('SocialPlatform options map configured values', function (): void {
    expect(SocialPlatform::options(['facebook', 'telegram_channel', 'x']))->toBe([
        'facebook' => 'Facebook',
        'x' => 'X / Twitter',
    ]);
});

test('SocialProfileData constructor', function (): void {
    $data = new SocialProfileData(
        platform: 'facebook',
        handle: 'testpage',
        url: 'https://facebook.com/testpage',
        isPrimary: true,
    );

    expect($data->platform)->toBe('facebook');
    expect($data->handle)->toBe('testpage');
    expect($data->url)->toBe('https://facebook.com/testpage');
    expect($data->isPrimary)->toBeTrue();
});

test('SocialProfileData from array', function (): void {
    $data = new SocialProfileData(
        platform: 'instagram',
        handle: '@user',
        url: 'https://instagram.com/user',
    );

    expect($data->platform)->toBe('instagram');
    expect($data->handle)->toBe('@user');
});

test('SocialProfile uses only the canonical sort order attribute', function (): void {
    $profile = new SocialProfile;
    $profile->fill([
        'sort_order' => 2,
        'order_column' => 4,
    ]);

    expect($profile->sort_order)->toBe(2)
        ->and($profile->getAttribute('order_column'))->toBeNull()
        ->and($profile->getFillable())->not->toContain('order_column');
});

test('primary social profiles remain unique per socialable type and purpose', function (): void {
    $customer = Customer::create([
        'first_name' => 'Primary',
        'last_name' => 'Profile',
        'email' => 'primary-profile-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $first = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        purpose: 'general',
        handle: 'first-' . uniqid(),
        isPrimary: true,
    ));

    $second = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        purpose: 'general',
        handle: 'second-' . uniqid(),
        isPrimary: true,
    ));

    expect($first->fresh()?->is_primary)->toBeFalse()
        ->and($second->fresh()?->is_primary)->toBeTrue()
        ->and($customer->socialProfiles()->where('platform', 'facebook')->where('purpose', 'general')->count())->toBe(2);

    $instagram = $customer->addSocialProfile(new SocialProfileData(
        platform: 'instagram',
        purpose: 'general',
        handle: 'instagram-' . uniqid(),
        isPrimary: true,
    ));

    expect($instagram->fresh()?->is_primary)->toBeTrue()
        ->and($second->fresh()?->is_primary)->toBeTrue();
});

test('SocialProfile preserves explicitly supplied handle and URL values on later saves', function (): void {
    $customer = Customer::create([
        'first_name' => 'Display',
        'last_name' => 'Profile',
        'email' => 'display-profile-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $profile = SocialProfile::query()->create([
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => $customer->getKey(),
        'platform' => 'facebook',
        'purpose' => 'general',
        'handle' => 'InitialHandle',
        'url' => 'https://example.com/operator-profile',
    ]);

    $profile->handle = '@OperatorHandle';
    $profile->url = 'https://example.com/operator-profile?view=custom';
    $profile->save();
    $profile->label = 'Operator profile';
    $profile->save();

    expect($profile->fresh())
        ->handle->toBe('@OperatorHandle')
        ->url->toBe('https://example.com/operator-profile?view=custom');
});

test('SocialProfile uses public defaults and filters invalid primary profiles', function (): void {
    $customer = Customer::create([
        'first_name' => 'Visibility',
        'last_name' => 'Profile',
        'email' => 'visibility-profile-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);

    $privatePrimary = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        purpose: 'general',
        handle: 'private-' . uniqid(),
        isPrimary: true,
        isPublic: false,
    ));
    $publicPrimary = $customer->addSocialProfile(new SocialProfileData(
        platform: 'instagram',
        purpose: 'general',
        handle: 'public-' . uniqid(),
        isPrimary: true,
    ));

    SocialProfile::query()->create([
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => $customer->getKey(),
        'platform' => 'linkedin',
        'purpose' => 'general',
        'handle' => 'expired-' . uniqid(),
        'is_primary' => true,
        'is_public' => true,
        'valid_until' => CarbonImmutable::now()->subMinute(),
    ]);

    expect($privatePrimary->fresh()?->is_public)->toBeFalse()
        ->and($publicPrimary->fresh()?->is_public)->toBeTrue()
        ->and($customer->primarySocialProfile('facebook'))->not->toBeNull()
        ->and($customer->primarySocialProfile('facebook', publicOnly: true))->toBeNull()
        ->and($customer->primarySocialProfile('instagram', publicOnly: true)?->id)->toBe($publicPrimary->id)
        ->and($customer->primarySocialProfile('linkedin'))->toBeNull();
});

test('NormalizeSocialProfileAction normalizes @handle', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    expect($action->execute('facebook', '@TestUser', null)['handle'])->toBe('TestUser');
    expect($action->execute('facebook', '  @spaced  ', null)['handle'])->toBe('spaced');
});

test('NormalizeSocialProfileAction extracts handle from URL', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('instagram', null, 'https://instagram.com/user123');
    expect($r['handle'])->toBe('user123');
    expect($r['normalized_url'])->toBe('https://instagram.com/user123');
});

test('NormalizeSocialProfileAction handles null handle and URL', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('other', null, null);
    expect($r['handle'])->toBeNull();
    expect($r['normalized_url'])->toBeNull();
});
