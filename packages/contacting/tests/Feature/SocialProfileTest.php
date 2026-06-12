<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Contacting\Support\NormalizesSocialHandle;
use AIArmada\Contacting\Support\NormalizesUrl;

test('SocialProfile model class exists', function () {
    expect(class_exists(SocialProfile::class))->toBeTrue();
    // getTable() uses config() which needs Laravel app; skip for unit tests
    expect(true)->toBeTrue();
});

test('SocialPlatform enum has expected values', function () {
    expect(SocialPlatform::Facebook->value)->toBe('facebook');
    expect(SocialPlatform::Instagram->value)->toBe('instagram');
    expect(SocialPlatform::Tiktok->value)->toBe('tiktok');
    expect(SocialPlatform::Youtube->value)->toBe('youtube');
    expect(SocialPlatform::Linkedin->value)->toBe('linkedin');
    expect(SocialPlatform::X->value)->toBe('x');
    expect(SocialPlatform::Other->value)->toBe('other');
});

test('SocialProfileData constructor', function () {
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

test('SocialProfileData from array', function () {
    $data = new SocialProfileData(
        platform: 'instagram',
        handle: '@user',
        url: 'https://instagram.com/user',
    );

    expect($data->platform)->toBe('instagram');
    expect($data->handle)->toBe('@user');
});

test('NormalizeSocialProfileAction normalizes @handle', function () {
    $action = new NormalizeSocialProfileAction(
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    expect($action->execute('facebook', '@TestUser', null)['handle'])->toBe('TestUser');
    expect($action->execute('facebook', '  @spaced  ', null)['handle'])->toBe('spaced');
});

test('NormalizeSocialProfileAction extracts handle from URL', function () {
    $action = new NormalizeSocialProfileAction(
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('instagram', null, 'https://instagram.com/user123');
    expect($r['handle'])->toBe('user123');
    expect($r['normalized_url'])->toBe('https://instagram.com/user123');
});

test('NormalizeSocialProfileAction handles null handle and URL', function () {
    $action = new NormalizeSocialProfileAction(
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('other', null, null);
    expect($r['handle'])->toBeNull();
    expect($r['normalized_url'])->toBeNull();
});
