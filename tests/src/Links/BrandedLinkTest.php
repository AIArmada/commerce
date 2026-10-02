<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreatePublicLink;
use AIArmada\Links\Actions\GenerateLinkUrl;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Validation\ValidationException;

test('branded paths require both saved segments and record clicks', function (): void {
    $link = CreatePublicLink::run([
        'name' => 'Summer',
        'destination_url' => 'https://merchant.example/summer',
    ], 'branded', 'saifreviews', 'summer');

    expect(GenerateLinkUrl::run($link))->toBe(url('/go/saifreviews/' . $link->slug))
        ->and($link->slug)->toStartWith('summer-');
    $this->get('/go/other/' . $link->slug)->assertNotFound();
    $this->get('/go/' . $link->slug)->assertNotFound();
    expect(LinkClick::query()->count())->toBe(0);
    $this->get('/go/saifreviews/' . $link->slug)->assertRedirect('https://merchant.example/summer');
    expect($link->refresh()->total_clicks)->toBe(1);
});

test('short links have no prefix and cannot be accessed with an arbitrary prefix', function (): void {
    $link = CreatePublicLink::run(['name' => 'Short', 'destination_url' => 'https://merchant.example']);
    expect(GenerateLinkUrl::run($link))->toBe(url('/go/' . $link->slug));
    $this->get('/go/saifreviews/' . $link->slug)->assertNotFound();
    $this->get('/go/' . $link->slug)->assertRedirect('https://merchant.example');
});

test('branded links reject malformed handles and unknown styles', function (string $style, ?string $handle): void {
    CreatePublicLink::run(['name' => 'Invalid', 'destination_url' => 'https://merchant.example'], $style, $handle);
})->with([['branded', null], ['branded', '../saif'], ['branded', 'saif/name'], ['branded', 'admin'], ['unknown', 'saif']])->throws(ValidationException::class);
