<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\CreateTrackingLink;
use AIArmada\Affiliates\Actions\Affiliates\UpdateTrackingLink;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function handleAffiliate(array $attributes = []): Affiliate
{
    return Affiliate::query()->create(array_merge(['code' => 'CODE-' . Str::random(8), 'name' => 'Saif Reviews', 'status' => Active::class], $attributes));
}

test('affiliates receive a handle and chosen handles are normalized and globally unique', function (): void {
    expect(handleAffiliate()->handle)->toMatch('/^saif-reviews-[a-z0-9]+$/');
    $affiliate = handleAffiliate(['handle' => ' SaifReviews ']);
    expect($affiliate->handle)->toBe('saifreviews');
    expect(fn () => handleAffiliate(['handle' => 'SAIFREVIEWS']))->toThrow(ValidationException::class);
});

test('handle changes preserve saved branded URLs and attribution', function (): void {
    config(['affiliates.links.allowed_hosts' => ['merchant.example']]);
    $affiliate = handleAffiliate(['handle' => 'saifreviews']);
    $link = CreateTrackingLink::run($affiliate, 'https://merchant.example/summer', ['link_style' => 'branded', 'link_label' => 'summer']);
    $issued = $link->tracking_url;
    expect($issued)->toContain('/go/saifreviews/summer-');
    $affiliate->update(['handle' => 'saifnew']);
    expect($link->fresh()->tracking_url)->toBe($issued);
    $this->get($issued)->assertRedirect();
    expect($link->refresh()->clicks)->toBe(1);
    $new = CreateTrackingLink::run($affiliate, 'https://merchant.example/summer', ['link_style' => 'branded', 'link_label' => 'summer']);
    expect($new->tracking_url)->toContain('/go/saifnew/summer-');
    $link->deactivate();
    $this->get($issued)->assertStatus(410);
});

test('custom handle policy is enforced on writes', function (): void {
    config(['affiliates.handles.allow_custom' => false]);
    $affiliate = handleAffiliate();
    expect(fn () => $affiliate->update(['handle' => 'chosen']))->toThrow(ValidationException::class);
    expect(fn () => handleAffiliate(['handle' => 'chosen']))->toThrow(ValidationException::class);
});

test('handle uniqueness spans tenants and tracking link writes remain owner isolated', function (): void {
    config(['affiliates.owner.enabled' => true, 'affiliates.links.allowed_hosts' => ['merchant.example']]);
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $affiliate = OwnerContext::withOwner($owner, fn () => handleAffiliate(['handle' => 'reservedcreator']));
    OwnerContext::withOwner($other, function () use ($affiliate): void {
        expect(Affiliate::query()->find($affiliate->id))->toBeNull();
        expect(fn () => handleAffiliate(['handle' => 'reservedcreator']))->toThrow(ValidationException::class);
        expect(fn () => CreateTrackingLink::run($affiliate, 'https://merchant.example'))->toThrow(ModelNotFoundException::class);
    });
});

test('saved link ID drives destination attribution after handle and code change', function (): void {
    config(['affiliates.links.allowed_hosts' => ['localhost'], 'affiliates.tracking.block_self_referral' => false]);
    $affiliate = handleAffiliate(['handle' => 'saifreviews']);
    $link = CreateTrackingLink::run($affiliate, 'http://localhost/landing', ['link_style' => 'branded', 'subject_type' => 'product', 'subject_title_snapshot' => 'Summer', 'subject_metadata' => ['category' => 'campaign']]);
    $affiliate->update(['handle' => 'saifnew', 'code' => 'RENAMED']);
    Route::get('/landing', fn () => 'Welcome');
    $this->get($link->tracking_url)->assertRedirect('http://localhost/landing?aff_link=' . $link->id);
    $this->get('/landing?aff_link=' . $link->id)->assertOk();
    $attribution = AffiliateAttribution::query()->where('affiliate_link_id', $link->id)->first();
    expect($attribution->affiliate_id)->toBe($affiliate->id)->and($attribution->affiliate_code)->toBe('RENAMED')
        ->and($attribution->subject_title_snapshot)->toBe('Summer')
        ->and($attribution->metadata['category'])->toBe('campaign');
});

test('affiliate deletion removes tracked campaign links and their clicks', function (): void {
    config(['affiliates.links.allowed_hosts' => ['merchant.example']]);
    $affiliate = handleAffiliate();
    $link = CreateTrackingLink::run($affiliate, 'https://merchant.example');
    $trackedId = $link->trackedLink->id;
    $this->get($link->tracking_url)->assertRedirect();
    $affiliate->delete();
    expect(Link::query()->whereKey($trackedId)->exists())->toBeFalse()
        ->and(LinkClick::query()->where('link_id', $trackedId)->exists())->toBeFalse();
});

test('updating a destination preserves its URL and enforces the same allowlist', function (): void {
    config(['affiliates.links.allowed_hosts' => ['merchant.example']]);
    $affiliate = handleAffiliate(['handle' => 'saifreviews']);
    $link = CreateTrackingLink::run($affiliate, 'https://merchant.example/summer', ['link_style' => 'branded']);
    $issued = $link->tracking_url;
    UpdateTrackingLink::run($link, ['destination_url' => 'https://merchant.example/autumn']);
    expect($link->fresh()->tracking_url)->toBe($issued);
    $this->get($issued)->assertRedirect('https://merchant.example/autumn?aff_link=' . $link->id);
    expect(fn () => UpdateTrackingLink::run($link, ['destination_url' => 'https://evil.example']))->toThrow(ValidationException::class);
    expect($link->fresh()->destination_url)->toBe('https://merchant.example/autumn');
});

test('hosts can default to branded links without requiring an option on each creation', function (): void {
    config(['affiliates.links.allowed_hosts' => ['merchant.example'], 'affiliates.links.default_style' => 'branded']);
    $affiliate = handleAffiliate(['handle' => 'saifreviews']);
    $link = CreateTrackingLink::run($affiliate, 'https://merchant.example');
    expect($link->tracking_url)->toContain('/go/saifreviews/link-');
});
