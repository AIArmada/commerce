<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Contracts\FallbackRedirectGateInterface;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Data\LinkGateContext;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use AIArmada\Links\Support\DefaultLinkGate;

final class StubBlockingGate implements FallbackRedirectGateInterface, LinkGateInterface
{
    public function __construct(
        private readonly ?string $reason,
        private readonly ?string $fallback = null,
    ) {}

    public function blockedReason(LinkGateContext $link): ?string
    {
        return $this->reason;
    }

    public function fallbackUrl(LinkGateContext $link, string $reason): ?string
    {
        return $this->fallback;
    }
}

function linksFallbackLink(string $slug, array $attributes = []): Link
{
    return CreateLink::run(array_merge([
        'name' => 'Fallback ' . $slug,
        'slug' => $slug,
        'destination_url' => 'https://merchant.example/offer',
    ], $attributes));
}

describe('fallback redirect gate', function (): void {
    test('a blocking gate with a fallback returns a 302 redirect', function (): void {
        linksFallbackLink('fallback-302');

        app()->forgetInstance(LinkGateInterface::class);
        app()->instance(LinkGateInterface::class, new DefaultLinkGate);
        app()->instance(StubBlockingGate::class, new StubBlockingGate('offer_inactive', 'https://fallback.example/promo'));
        app()->tag([StubBlockingGate::class], LinkGateInterface::class);

        $this->get('/go/fallback-302')->assertRedirect('https://fallback.example/promo');
    });

    test('the default gate returns null when no gate offers a fallback', function (): void {
        $link = linksFallbackLink('fallback-none');
        $context = new LinkGateContext((string) $link->getKey(), $link->slug, $link->subject_type, $link->subject_id);

        $gate = app(DefaultLinkGate::class);

        expect($gate->blockedReason($context))->toBeNull()
            ->and($gate->fallbackUrl($context, 'expired'))->toBeNull();
    });

    test('a missing fallback produces a 410 for a blocked link', function (): void {
        $link = linksFallbackLink('fallback-410');
        $link->update(['deactivated_at' => now()]);

        $this->get('/go/fallback-410')->assertGone();
    });
});

describe('dynamic sub id forwarding', function (): void {
    test('incoming sub ids pass through to the destination', function (): void {
        linksFallbackLink('subs-passthrough');

        $response = $this->get('/go/subs-passthrough?sub1=creator&sub2=story&sub3=hero');

        $response->assertRedirect();
        $url = (string) $response->headers->get('Location');

        expect($url)->toContain('sub1=creator')
            ->and($url)->toContain('sub2=story')
            ->and($url)->toContain('sub3=hero');
    });

    test('explicit link parameters win over incoming sub ids', function (): void {
        linksFallbackLink('subs-pinned', [
            'parameters' => ['sub1' => 'pinned'],
        ]);

        $response = $this->get('/go/subs-pinned?sub1=spoofed&sub2=story');

        $url = (string) $response->headers->get('Location');

        expect($url)->toContain('sub1=pinned')
            ->and($url)->toContain('sub2=story');
    });

    test('sub ids longer than 255 characters are dropped', function (): void {
        linksFallbackLink('subs-too-long');

        $response = $this->get('/go/subs-too-long?sub1=' . str_repeat('a', 256));

        $url = (string) $response->headers->get('Location');

        expect($url)->not->toContain('sub1=');
    });

    test('recorded clicks capture dynamic sub ids into properties', function (): void {
        linksFallbackLink('subs-recorded');

        $this->get('/go/subs-recorded?sub1=alpha&sub2=beta&sub3=gamma')->assertRedirect();

        $click = LinkClick::query()->sole();
        $subIds = $click->properties['sub_ids'] ?? [];

        expect($subIds)->toBe(['sub1' => 'alpha', 'sub2' => 'beta', 'sub3' => 'gamma']);
    });
});
