<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;

describe('CampaignStatus Enum', function (): void {
    describe('cases', function (): void {
        it('has Draft case', function (): void {
            expect(CampaignStatus::Draft->value)->toBe('draft');
        });

        it('has Scheduled case', function (): void {
            expect(CampaignStatus::Scheduled->value)->toBe('scheduled');
        });

        it('has Active case', function (): void {
            expect(CampaignStatus::Active->value)->toBe('active');
        });

        it('has Paused case', function (): void {
            expect(CampaignStatus::Paused->value)->toBe('paused');
        });

        it('has Completed case', function (): void {
            expect(CampaignStatus::Completed->value)->toBe('completed');
        });

        it('has Cancelled case', function (): void {
            expect(CampaignStatus::Cancelled->value)->toBe('cancelled');
        });

        it('has exactly 6 cases', function (): void {
            expect(CampaignStatus::cases())->toHaveCount(6);
        });
    });

    describe('options', function (): void {
        it('returns array of options for dropdowns', function (): void {
            $options = CampaignStatus::options();

            expect($options)->toBeArray()
                ->and($options)->toHaveKeys(['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled']);
        });

        it('maps values to labels', function (): void {
            $options = CampaignStatus::options();

            expect($options['draft'])->toBe('Draft')
                ->and($options['scheduled'])->toBe('Scheduled')
                ->and($options['active'])->toBe('Active')
                ->and($options['paused'])->toBe('Paused')
                ->and($options['completed'])->toBe('Completed')
                ->and($options['cancelled'])->toBe('Cancelled');
        });
    });

    describe('label', function (): void {
        it('returns correct label for Draft', function (): void {
            expect(CampaignStatus::Draft->label())->toBe('Draft');
        });

        it('returns correct label for Scheduled', function (): void {
            expect(CampaignStatus::Scheduled->label())->toBe('Scheduled');
        });

        it('returns correct label for Active', function (): void {
            expect(CampaignStatus::Active->label())->toBe('Active');
        });

        it('returns correct label for Paused', function (): void {
            expect(CampaignStatus::Paused->label())->toBe('Paused');
        });

        it('returns correct label for Completed', function (): void {
            expect(CampaignStatus::Completed->label())->toBe('Completed');
        });

        it('returns correct label for Cancelled', function (): void {
            expect(CampaignStatus::Cancelled->label())->toBe('Cancelled');
        });
    });

    describe('color', function (): void {
        it('returns gray for Draft', function (): void {
            expect(CampaignStatus::Draft->color())->toBe('gray');
        });

        it('returns info for Scheduled', function (): void {
            expect(CampaignStatus::Scheduled->color())->toBe('info');
        });

        it('returns success for Active', function (): void {
            expect(CampaignStatus::Active->color())->toBe('success');
        });

        it('returns warning for Paused', function (): void {
            expect(CampaignStatus::Paused->color())->toBe('warning');
        });

        it('returns primary for Completed', function (): void {
            expect(CampaignStatus::Completed->color())->toBe('primary');
        });

        it('returns danger for Cancelled', function (): void {
            expect(CampaignStatus::Cancelled->color())->toBe('danger');
        });
    });

    describe('canReceiveTraffic', function (): void {
        it('returns true only for Active', function (): void {
            expect(CampaignStatus::Active->canReceiveTraffic())->toBeTrue();
        });

        it('returns false for Draft', function (): void {
            expect(CampaignStatus::Draft->canReceiveTraffic())->toBeFalse();
        });

        it('returns false for Scheduled', function (): void {
            expect(CampaignStatus::Scheduled->canReceiveTraffic())->toBeFalse();
        });

        it('returns false for Paused', function (): void {
            expect(CampaignStatus::Paused->canReceiveTraffic())->toBeFalse();
        });

        it('returns false for Completed', function (): void {
            expect(CampaignStatus::Completed->canReceiveTraffic())->toBeFalse();
        });

        it('returns false for Cancelled', function (): void {
            expect(CampaignStatus::Cancelled->canReceiveTraffic())->toBeFalse();
        });
    });

    describe('canBeEdited', function (): void {
        it('returns true for Draft', function (): void {
            expect(CampaignStatus::Draft->canBeEdited())->toBeTrue();
        });

        it('returns true for Scheduled', function (): void {
            expect(CampaignStatus::Scheduled->canBeEdited())->toBeTrue();
        });

        it('returns true for Paused', function (): void {
            expect(CampaignStatus::Paused->canBeEdited())->toBeTrue();
        });

        it('returns false for Active', function (): void {
            expect(CampaignStatus::Active->canBeEdited())->toBeFalse();
        });

        it('returns false for Completed', function (): void {
            expect(CampaignStatus::Completed->canBeEdited())->toBeFalse();
        });

        it('returns false for Cancelled', function (): void {
            expect(CampaignStatus::Cancelled->canBeEdited())->toBeFalse();
        });
    });

    describe('isTerminal', function (): void {
        it('returns true for Completed', function (): void {
            expect(CampaignStatus::Completed->isTerminal())->toBeTrue();
        });

        it('returns true for Cancelled', function (): void {
            expect(CampaignStatus::Cancelled->isTerminal())->toBeTrue();
        });

        it('returns false for Draft', function (): void {
            expect(CampaignStatus::Draft->isTerminal())->toBeFalse();
        });

        it('returns false for Scheduled', function (): void {
            expect(CampaignStatus::Scheduled->isTerminal())->toBeFalse();
        });

        it('returns false for Active', function (): void {
            expect(CampaignStatus::Active->isTerminal())->toBeFalse();
        });

        it('returns false for Paused', function (): void {
            expect(CampaignStatus::Paused->isTerminal())->toBeFalse();
        });
    });

    describe('allowedTransitions', function (): void {
        it('returns correct transitions for Draft', function (): void {
            $transitions = CampaignStatus::Draft->allowedTransitions();

            expect($transitions)->toContain(CampaignStatus::Scheduled)
                ->and($transitions)->toContain(CampaignStatus::Active)
                ->and($transitions)->toContain(CampaignStatus::Cancelled)
                ->and($transitions)->toHaveCount(3);
        });

        it('returns correct transitions for Scheduled', function (): void {
            $transitions = CampaignStatus::Scheduled->allowedTransitions();

            expect($transitions)->toContain(CampaignStatus::Active)
                ->and($transitions)->toContain(CampaignStatus::Paused)
                ->and($transitions)->toContain(CampaignStatus::Cancelled)
                ->and($transitions)->toHaveCount(3);
        });

        it('returns correct transitions for Active', function (): void {
            $transitions = CampaignStatus::Active->allowedTransitions();

            expect($transitions)->toContain(CampaignStatus::Paused)
                ->and($transitions)->toContain(CampaignStatus::Completed)
                ->and($transitions)->toContain(CampaignStatus::Cancelled)
                ->and($transitions)->toHaveCount(3);
        });

        it('returns correct transitions for Paused', function (): void {
            $transitions = CampaignStatus::Paused->allowedTransitions();

            expect($transitions)->toContain(CampaignStatus::Active)
                ->and($transitions)->toContain(CampaignStatus::Completed)
                ->and($transitions)->toContain(CampaignStatus::Cancelled)
                ->and($transitions)->toHaveCount(3);
        });

        it('returns empty array for Completed', function (): void {
            expect(CampaignStatus::Completed->allowedTransitions())->toBe([]);
        });

        it('returns empty array for Cancelled', function (): void {
            expect(CampaignStatus::Cancelled->allowedTransitions())->toBe([]);
        });
    });

    describe('canTransitionTo', function (): void {
        it('allows Draft to transition to Scheduled', function (): void {
            expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Scheduled))->toBeTrue();
        });

        it('allows Draft to transition to Active', function (): void {
            expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Active))->toBeTrue();
        });

        it('allows Draft to transition to Cancelled', function (): void {
            expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Cancelled))->toBeTrue();
        });

        it('does not allow Draft to transition to Completed', function (): void {
            expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Completed))->toBeFalse();
        });

        it('does not allow Draft to transition to Paused', function (): void {
            expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Paused))->toBeFalse();
        });

        it('does not allow Completed to transition anywhere', function (): void {
            foreach (CampaignStatus::cases() as $status) {
                expect(CampaignStatus::Completed->canTransitionTo($status))->toBeFalse();
            }
        });

        it('does not allow Cancelled to transition anywhere', function (): void {
            foreach (CampaignStatus::cases() as $status) {
                expect(CampaignStatus::Cancelled->canTransitionTo($status))->toBeFalse();
            }
        });
    });
});
