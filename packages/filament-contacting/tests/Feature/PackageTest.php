<?php

declare(strict_types=1);

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\FilamentContacting\Exports\ContactMethodExporter;
use AIArmada\FilamentContacting\Exports\SocialProfileExporter;
use AIArmada\FilamentContacting\FilamentContactingPlugin;
use AIArmada\FilamentContacting\Imports\ContactMethodImporter;
use AIArmada\FilamentContacting\Imports\SocialProfileImporter;
use AIArmada\FilamentContacting\RelationManagers\ContactMethodsRelationManager;
use AIArmada\FilamentContacting\RelationManagers\SocialProfilesRelationManager;
use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use AIArmada\FilamentContacting\Resources\ContactSnapshotResource;
use AIArmada\FilamentContacting\Resources\SocialProfileResource;
use AIArmada\FilamentContacting\Schemas\ContactMethodFormSchema;
use AIArmada\FilamentContacting\Schemas\ContactMethodInfolistSchema;
use AIArmada\FilamentContacting\Schemas\ContactSnapshotInfolistSchema;
use AIArmada\FilamentContacting\Schemas\SocialProfileFormSchema;
use AIArmada\FilamentContacting\Schemas\SocialProfileInfolistSchema;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\FilamentContacting\Support\ResolvesContactingModels;
use AIArmada\FilamentContacting\Tables\ContactMethodTable;
use AIArmada\FilamentContacting\Tables\ContactSnapshotTable;
use AIArmada\FilamentContacting\Tables\SocialProfileTable;

test('config default values are correct', function () {
    $config = require __DIR__ . '/../../config/filament-contacting.php';

    expect($config['features']['standalone_resources'])->toBeFalse();
    expect($config['features']['relation_managers'])->toBeTrue();
    expect($config['features']['imports'])->toBeFalse();
    expect($config['features']['exports'])->toBeTrue();

    expect($config['resources']['contact_methods']['enabled'])->toBeFalse();
    expect($config['resources']['contact_methods']['read_only'])->toBeFalse();
    expect($config['resources']['social_profiles']['enabled'])->toBeFalse();
    expect($config['resources']['social_profiles']['read_only'])->toBeFalse();
    expect($config['resources']['contact_snapshots']['enabled'])->toBeFalse();
    expect($config['resources']['contact_snapshots']['read_only'])->toBeTrue();
});

test('navigation config has required keys', function () {
    $config = require __DIR__ . '/../../config/filament-contacting.php';
    expect($config['navigation']['group'])->toBe('Contacting');
    expect($config['navigation']['icons']['contact_methods'])->toBe('heroicon-o-phone');
});

test('table config has required keys', function () {
    $config = require __DIR__ . '/../../config/filament-contacting.php';
    expect($config['tables']['default_pagination'])->toBe(25);
    expect($config['tables']['show_verification_columns'])->toBeTrue();
});

test('schema classes exist and have static make method', function () {
    expect(method_exists(ContactMethodFormSchema::class, 'make'))->toBeTrue();
    expect(method_exists(SocialProfileFormSchema::class, 'make'))->toBeTrue();
    expect(method_exists(ContactMethodInfolistSchema::class, 'make'))->toBeTrue();
    expect(method_exists(SocialProfileInfolistSchema::class, 'make'))->toBeTrue();
    expect(method_exists(ContactSnapshotInfolistSchema::class, 'make'))->toBeTrue();
});

test('GuardContactingUi class can be constructed', function () {
    $guard = new GuardsContactingUi;
    expect(method_exists($guard, 'contactMethodsReadOnly'))->toBeTrue();
    expect(method_exists($guard, 'socialProfilesReadOnly'))->toBeTrue();
    expect(method_exists($guard, 'contactSnapshotsReadOnly'))->toBeTrue();
});

test('ContactingFilamentConfig class can be constructed', function () {
    $config = new ContactingFilamentConfig;
    expect(method_exists($config, 'standaloneResources'))->toBeTrue();
    expect(method_exists($config, 'relationManagersEnabled'))->toBeTrue();
    expect(method_exists($config, 'exportsEnabled'))->toBeTrue();
    expect(method_exists($config, 'verificationBadges'))->toBeTrue();
    expect(method_exists($config, 'showVerificationColumns'))->toBeTrue();
});

test('ResolvesContactingModels resolves correct model classes', function () {
    $resolver = new ResolvesContactingModels;
    expect($resolver->contactMethod())->toBe(ContactMethod::class);
    expect($resolver->socialProfile())->toBe(SocialProfile::class);
    expect($resolver->contactSnapshot())->toBe(ContactSnapshot::class);
});

test('FilamentContactingPlugin can be instantiated', function () {
    $plugin = FilamentContactingPlugin::make();
    expect($plugin)->toBeInstanceOf(FilamentContactingPlugin::class);
    expect($plugin->getId())->toBe('filament-contacting');
});

test('no migration files exist in package', function () {
    expect(glob(__DIR__ . '/../../database/migrations/*.php'))->toBeEmpty();
});

test('table classes exist', function () {
    expect(class_exists(ContactMethodTable::class))->toBeTrue();
    expect(class_exists(SocialProfileTable::class))->toBeTrue();
    expect(class_exists(ContactSnapshotTable::class))->toBeTrue();
})->skip('Requires Filament Table class which needs Laravel app');

test('resource classes exist', function () {
    expect(class_exists(ContactMethodResource::class))->toBeTrue();
    expect(class_exists(SocialProfileResource::class))->toBeTrue();
    expect(class_exists(ContactSnapshotResource::class))->toBeTrue();
})->skip('Requires Filament Resource class which needs Laravel app');

test('relation manager classes exist', function () {
    expect(class_exists(ContactMethodsRelationManager::class))->toBeTrue();
    expect(class_exists(SocialProfilesRelationManager::class))->toBeTrue();
})->skip('Requires Filament RelationManager class which needs Laravel app');

test('export and import classes exist', function () {
    expect(class_exists(ContactMethodExporter::class))->toBeTrue();
    expect(class_exists(SocialProfileExporter::class))->toBeTrue();
    expect(class_exists(ContactMethodImporter::class))->toBeTrue();
    expect(class_exists(SocialProfileImporter::class))->toBeTrue();
})->skip('Requires Filament importer/exporter classes which need Laravel app');
