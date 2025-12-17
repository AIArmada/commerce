<?php

declare(strict_types=1);

// ============================================
// Filament Shipping Actions Tests
// ============================================
// Note: We test action configuration (name/label/icon/etc.) without executing
// the underlying action closures (carrier calls, DB writes, notifications).

use AIArmada\FilamentShipping\Actions\ApproveReturnAction;
use AIArmada\FilamentShipping\Actions\BulkCancelAction;
use AIArmada\FilamentShipping\Actions\BulkPrintLabelsAction;
use AIArmada\FilamentShipping\Actions\BulkShipAction;
use AIArmada\FilamentShipping\Actions\BulkSyncTrackingAction;
use AIArmada\FilamentShipping\Actions\CancelShipmentAction;
use AIArmada\FilamentShipping\Actions\PrintLabelAction;
use AIArmada\FilamentShipping\Actions\RejectReturnAction;
use AIArmada\FilamentShipping\Actions\ShipAction;
use AIArmada\FilamentShipping\Actions\SyncTrackingAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;

describe('Actions namespace', function (): void {
    it('has action files in the correct location', function (): void {
        $actionsPath = dirname(__DIR__, 4) . '/packages/filament-shipping/src/Actions';

        expect(file_exists($actionsPath . '/ShipAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/PrintLabelAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/CancelShipmentAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/SyncTrackingAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkShipAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkPrintLabelsAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkCancelAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkSyncTrackingAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/ApproveReturnAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/RejectReturnAction.php'))->toBeTrue();
    });

    it('configures per-record shipment actions', function (): void {
        $actions = [
            ShipAction::make(),
            PrintLabelAction::make(),
            CancelShipmentAction::make(),
            SyncTrackingAction::make(),
        ];

        foreach ($actions as $action) {
            expect($action)->toBeInstanceOf(Action::class);
            expect($action->getName())->not()->toBeNull();
            expect($action->getLabel())->not()->toBeEmpty();
        }
    });

    it('configures bulk shipment actions', function (): void {
        $actions = [
            BulkShipAction::make(),
            BulkPrintLabelsAction::make(),
            BulkCancelAction::make(),
            BulkSyncTrackingAction::make(),
        ];

        foreach ($actions as $action) {
            expect($action)->toBeInstanceOf(BulkAction::class);
            expect($action->getName())->not()->toBeNull();
            expect($action->getLabel())->not()->toBeEmpty();
        }
    });

    it('configures return authorization actions', function (): void {
        $approve = ApproveReturnAction::make();
        $reject = RejectReturnAction::make();

        expect($approve)->toBeInstanceOf(Action::class);
        expect($reject)->toBeInstanceOf(Action::class);
        expect($approve->getName())->toBe('approve');
        expect($reject->getName())->toBe('reject');
    });
});
