<?php

declare(strict_types=1);

use AIArmada\FilamentEvents\Contracts\EventFormExtension;
use AIArmada\FilamentEvents\Extensions\ConfiguredFormExtensions;
use Filament\Forms\Components\TextInput;

final class TestConfiguredEventExtension implements EventFormExtension
{
    public function components(): array
    {
        return [TextInput::make('custom_field')];
    }
}

it('resolves distinct configured extensions and builds fresh UI components', function (): void {
    config(['filament-events.resources.event_form_extensions' => [
        TestConfiguredEventExtension::class,
        stdClass::class,
        TestConfiguredEventExtension::class,
    ]]);

    $first = ConfiguredFormExtensions::components('filament-events.resources.event_form_extensions', EventFormExtension::class);
    $second = ConfiguredFormExtensions::components('filament-events.resources.event_form_extensions', EventFormExtension::class);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and($first[0])->not->toBe($second[0]);
});
