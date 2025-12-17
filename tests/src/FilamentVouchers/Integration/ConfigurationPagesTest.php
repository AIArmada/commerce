<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Pages\FraudConfigurationPage;
use AIArmada\FilamentVouchers\Pages\StackingConfigurationPage;
use AIArmada\FilamentVouchers\Pages\TargetingConfigurationPage;
use Filament\Schemas\Schema;

uses(TestCase::class);

it('builds configuration pages forms and actions', function (): void {
    foreach ([
        FraudConfigurationPage::class,
        StackingConfigurationPage::class,
        TargetingConfigurationPage::class,
    ] as $pageClass) {
        $page = app($pageClass);

        expect($page->form(Schema::make($page)))->toBeInstanceOf(Schema::class);

        $actions = new ReflectionMethod($pageClass, 'getFormActions');
        $actions->setAccessible(true);

        expect($actions->invoke($page))->toBeArray();

        // Mount and save cover the dynamic `$this->form` schema accessor.
        $page->mount();

        if (method_exists($page, 'testDetection')) {
            $page->testDetection();
        }

        if (method_exists($page, 'save')) {
            $page->save();
        }
    }

    $operators = new ReflectionMethod(TargetingConfigurationPage::class, 'getOperatorsForType');
    $operators->setAccessible(true);

    expect($operators->invoke(null, 'cart_value'))->toHaveKey('between');
    expect($operators->invoke(null, 'user_segment'))->toHaveKey('any');
    expect($operators->invoke(null, 'first_purchase'))->toHaveKey('=');
    expect($operators->invoke(null, null))->toHaveKey('not_in');
});
