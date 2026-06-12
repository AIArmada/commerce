<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\BuildContactLinksAction;
use AIArmada\Contacting\Data\ContactLinksData;
use AIArmada\Contacting\Data\ContactSnapshotData;
use AIArmada\Contacting\Models\ContactMethod;

test('BuildContactLinksAction builds mailto link', function () {
    $action = new BuildContactLinksAction;

    $cm = new ContactMethod;
    $cm->type = 'email';
    $cm->value = 'admin@example.com';
    $cm->normalized_value = 'admin@example.com';

    // Skip if Laravel app not available (config, DB not set up)
    if (! function_exists('config') || ! app()->bound('config')) {
        $this->markTestSkipped('Requires Laravel app');
    }

    $links = $action->execute([$cm]);
    expect($links->mailtoUrl)->toBe('mailto:admin@example.com');
});

test('BuildContactLinksAction returns null for empty contact methods', function () {
    $action = new BuildContactLinksAction;

    $links = $action->execute([]);
    expect($links->mailtoUrl)->toBeNull();
    expect($links->telUrl)->toBeNull();
    expect($links->whatsappUrl)->toBeNull();
    expect($links->websiteUrl)->toBeNull();
});

test('ContactSnapshotData constructor', function () {
    $data = new ContactSnapshotData(
        snapshotType: 'contact_method',
        reason: 'event_public_contact',
        channel: 'email',
        value: 'admin@example.com',
    );

    expect($data->snapshotType)->toBe('contact_method');
    expect($data->reason)->toBe('event_public_contact');
    expect($data->channel)->toBe('email');
    expect($data->value)->toBe('admin@example.com');
});

test('ContactLinksData constructor', function () {
    $data = new ContactLinksData(
        mailtoUrl: 'mailto:admin@example.com',
        whatsappUrl: 'https://wa.me/60123456789',
    );

    expect($data->mailtoUrl)->toBe('mailto:admin@example.com');
    expect($data->whatsappUrl)->toBe('https://wa.me/60123456789');
});

test('migrations have no forbidden patterns', function () {
    $migrationPath = realpath(__DIR__ . '/../../database/migrations');
    $files = glob("$migrationPath/*.php");

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain('->constrained(');
        expect($content)->not->toContain('->cascadeOnDelete(');
    }
});
