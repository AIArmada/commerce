<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Events\Models\Event;
use AIArmada\FilamentEvents\Pages\EventPublicPreview;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.owner.include_global', false);
    config()->set('events.features.owner.auto_assign_on_create', true);
});

function previewOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name) . '@example.com',
        'password' => 'secret',
    ]);
}

function previewEvent(User $owner, string $suffix): Event
{
    return OwnerContext::withOwner(
        $owner,
        fn () => Event::factory()->create([
            'title' => 'Preview Event ' . $suffix,
            'slug' => 'preview-event-' . $suffix . '-' . Str::random(6),
        ]),
    );
}

it('stamps a visible event at mount', function (): void {
    $owner = previewOwner('Preview Mount Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $page = app(EventPublicPreview::class);
    $page->mount(previewEvent($owner, 'mount')->id);
    $page->mountVerifiesRecordOwnerContext();

    expect($page->event)->not->toBeNull()
        ->and($page->recordOwnerStamp)->toBeString();
});

it('clears the event after an owner switch', function (): void {
    $ownerA = previewOwner('Preview Switch A');
    $ownerB = previewOwner('Preview Switch B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $page = app(EventPublicPreview::class);
    $page->mount(previewEvent($ownerA, 'switch')->id);
    $page->mountVerifiesRecordOwnerContext();

    expect($page->event)->not->toBeNull();

    OwnerContext::withOwner($ownerB, function () use ($page): void {
        $page->hydrateVerifiesRecordOwnerContext();
    });

    expect($page->event)->toBeNull()
        ->and($page->recordOwnerStamp)->toBeNull();
});

it('keeps the event when the owner context is unchanged', function (): void {
    $owner = previewOwner('Preview Stable Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $page = app(EventPublicPreview::class);
    $page->mount(previewEvent($owner, 'stable')->id);
    $page->mountVerifiesRecordOwnerContext();
    $page->hydrateVerifiesRecordOwnerContext();

    expect($page->event)->not->toBeNull();
});

it('aborts with 404 when mounted without an event id', function (): void {
    $page = app(EventPublicPreview::class);

    expect(fn () => $page->mount(null))->toThrow(NotFoundHttpException::class);
});

it('aborts with 404 when mounted with an unknown event id', function (): void {
    $owner = previewOwner('Preview Unknown Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $page = app(EventPublicPreview::class);

    expect(fn () => $page->mount((string) Str::uuid()))->toThrow(NotFoundHttpException::class);
});

it('aborts with 404 when mounted with a cross-owner event id', function (): void {
    $ownerA = previewOwner('Preview XOwner A');
    $ownerB = previewOwner('Preview XOwner B');
    $event = previewEvent($ownerA, 'xowner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $page = app(EventPublicPreview::class);

    expect(fn () => $page->mount($event->getKey()))->toThrow(NotFoundHttpException::class);
});

it('aborts with 404 when mounted with an empty event id', function (): void {
    $page = app(EventPublicPreview::class);

    expect(fn () => $page->mount(''))->toThrow(NotFoundHttpException::class);
});

it('aborts with 404 when mounted with a malformed event id', function (): void {
    $page = app(EventPublicPreview::class);

    expect(fn () => $page->mount('not-a-uuid'))->toThrow(NotFoundHttpException::class);
});

it('exposes the event id route placeholder in its slug', function (): void {
    expect(EventPublicPreview::getSlug())->toContain('{eventId}');
});

it('fills the event id into the route path via getUrl', function (): void {
    $owner = previewOwner('Preview Route Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    $event = previewEvent($owner, 'route');

    Route::name('filament.admin.')->prefix('admin')->group(
        fn () => EventPublicPreview::registerRoutes(Filament::getPanel('admin')),
    );

    $url = EventPublicPreview::getUrl(['eventId' => $event->getKey()], isAbsolute: false);

    expect($url)->toBe('/admin/events/public-preview/' . $event->getKey());

    $matched = Route::getRoutes()->match(Request::create($url, 'GET'));

    expect($matched->parameter('eventId'))->toBe((string) $event->getKey());
});

it('clears the event on refresh after an owner switch through a real request cycle', function (): void {
    $ownerA = previewOwner('Preview Live Cycle A');
    $ownerB = previewOwner('Preview Live Cycle B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $test = Livewire::test(EventPublicPreview::class, ['eventId' => previewEvent($ownerA, 'live')->getKey()]);

    expect($test->get('event'))->not->toBeNull()
        ->and($test->get('recordOwnerStamp'))->toBeString();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $test->call('$refresh');

    expect($test->get('event'))->toBeNull()
        ->and($test->get('recordOwnerStamp'))->toBeNull();
});

it('exposes the owner guard hooks and locked event props', function (): void {
    $page = app(EventPublicPreview::class);

    expect(method_exists($page, 'mountVerifiesRecordOwnerContext'))->toBeTrue()
        ->and(method_exists($page, 'hydrateVerifiesRecordOwnerContext'))->toBeTrue()
        ->and((new ReflectionProperty($page, 'event'))->getAttributes(Locked::class))->not->toBeEmpty()
        ->and((new ReflectionProperty($page, 'eventId'))->getAttributes(Locked::class))->not->toBeEmpty()
        ->and((new ReflectionProperty($page, 'recordOwnerStamp'))->getAttributes(Locked::class))->not->toBeEmpty();
});
