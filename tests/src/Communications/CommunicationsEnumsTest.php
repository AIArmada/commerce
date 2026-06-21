<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Enums\SuppressionReason;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Enums\ThreadStatus;

describe('CommunicationDirection', function (): void {
    it('has outbound and inbound cases', function (): void {
        expect(CommunicationDirection::Outbound->value)->toBe('outbound');
        expect(CommunicationDirection::Inbound->value)->toBe('inbound');
    });

    it('provides readable labels', function (): void {
        expect(CommunicationDirection::Outbound->label())->toBeString()->not->toBeEmpty();
        expect(CommunicationDirection::Inbound->label())->toBeString()->not->toBeEmpty();
    });
});

describe('CommunicationCategory', function (): void {
    it('has required cases', function (): void {
        expect(CommunicationCategory::Transactional->value)->toBe('transactional');
        expect(CommunicationCategory::Operational->value)->toBe('operational');
        expect(CommunicationCategory::Marketing->value)->toBe('marketing');
        expect(CommunicationCategory::Security->value)->toBe('security');
        expect(CommunicationCategory::Legal->value)->toBe('legal');
        expect(CommunicationCategory::Support->value)->toBe('support');
        expect(CommunicationCategory::Internal->value)->toBe('internal');
    });

    it('provides readable labels', function (): void {
        foreach (CommunicationCategory::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('CommunicationPriority', function (): void {
    it('has required cases', function (): void {
        expect(CommunicationPriority::Low->value)->toBe('low');
        expect(CommunicationPriority::Normal->value)->toBe('normal');
        expect(CommunicationPriority::High->value)->toBe('high');
        expect(CommunicationPriority::Urgent->value)->toBe('urgent');
    });

    it('provides readable labels', function (): void {
        foreach (CommunicationPriority::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('CommunicationStatus', function (): void {
    it('has full lifecycle cases', function (): void {
        expect(CommunicationStatus::Draft->value)->toBe('draft');
        expect(CommunicationStatus::Scheduled->value)->toBe('scheduled');
        expect(CommunicationStatus::Queued->value)->toBe('queued');
        expect(CommunicationStatus::Processing->value)->toBe('processing');
        expect(CommunicationStatus::PartiallyCompleted->value)->toBe('partially_completed');
        expect(CommunicationStatus::Completed->value)->toBe('completed');
        expect(CommunicationStatus::Failed->value)->toBe('failed');
        expect(CommunicationStatus::Cancelled->value)->toBe('cancelled');
        expect(CommunicationStatus::Expired->value)->toBe('expired');
    });

    it('provides readable labels', function (): void {
        foreach (CommunicationStatus::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('DeliveryStatus', function (): void {
    it('has full lifecycle cases', function (): void {
        expect(DeliveryStatus::Pending->value)->toBe('pending');
        expect(DeliveryStatus::Suppressed->value)->toBe('suppressed');
        expect(DeliveryStatus::Scheduled->value)->toBe('scheduled');
        expect(DeliveryStatus::Queued->value)->toBe('queued');
        expect(DeliveryStatus::Sending->value)->toBe('sending');
        expect(DeliveryStatus::Accepted->value)->toBe('accepted');
        expect(DeliveryStatus::Sent->value)->toBe('sent');
        expect(DeliveryStatus::Received->value)->toBe('received');
        expect(DeliveryStatus::Delivered->value)->toBe('delivered');
        expect(DeliveryStatus::Opened->value)->toBe('opened');
        expect(DeliveryStatus::Read->value)->toBe('read');
        expect(DeliveryStatus::Clicked->value)->toBe('clicked');
        expect(DeliveryStatus::Replied->value)->toBe('replied');
        expect(DeliveryStatus::Bounced->value)->toBe('bounced');
        expect(DeliveryStatus::Complained->value)->toBe('complained');
        expect(DeliveryStatus::Unsubscribed->value)->toBe('unsubscribed');
        expect(DeliveryStatus::Failed->value)->toBe('failed');
        expect(DeliveryStatus::Cancelled->value)->toBe('cancelled');
        expect(DeliveryStatus::Expired->value)->toBe('expired');
    });

    it('provides readable labels', function (): void {
        foreach (DeliveryStatus::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('ThreadStatus', function (): void {
    it('has required cases', function (): void {
        expect(ThreadStatus::Open->value)->toBe('open');
        expect(ThreadStatus::Closed->value)->toBe('closed');
        expect(ThreadStatus::Archived->value)->toBe('archived');
    });

    it('provides readable labels', function (): void {
        foreach (ThreadStatus::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('TemplateStatus', function (): void {
    it('has required cases', function (): void {
        expect(TemplateStatus::Draft->value)->toBe('draft');
        expect(TemplateStatus::Published->value)->toBe('published');
        expect(TemplateStatus::Disabled->value)->toBe('disabled');
    });

    it('provides readable labels', function (): void {
        foreach (TemplateStatus::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('RecipientRole', function (): void {
    it('has required cases', function (): void {
        expect(RecipientRole::To->value)->toBe('to');
        expect(RecipientRole::Cc->value)->toBe('cc');
        expect(RecipientRole::Bcc->value)->toBe('bcc');
        expect(RecipientRole::Sender->value)->toBe('sender');
        expect(RecipientRole::ReplyTo->value)->toBe('reply_to');
    });

    it('provides readable labels', function (): void {
        foreach (RecipientRole::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('CommunicationEventSource', function (): void {
    it('has required cases', function (): void {
        expect(CommunicationEventSource::Application->value)->toBe('application');
        expect(CommunicationEventSource::Queue->value)->toBe('queue');
        expect(CommunicationEventSource::Provider->value)->toBe('provider');
        expect(CommunicationEventSource::Webhook->value)->toBe('webhook');
        expect(CommunicationEventSource::Tracking->value)->toBe('tracking');
        expect(CommunicationEventSource::Administrator->value)->toBe('administrator');
        expect(CommunicationEventSource::System->value)->toBe('system');
    });

    it('provides readable labels', function (): void {
        foreach (CommunicationEventSource::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('SuppressionReason', function (): void {
    it('has required cases', function (): void {
        expect(SuppressionReason::Bounced->value)->toBe('bounced');
        expect(SuppressionReason::Complained->value)->toBe('complained');
        expect(SuppressionReason::Unsubscribed->value)->toBe('unsubscribed');
        expect(SuppressionReason::Manual->value)->toBe('manual');
        expect(SuppressionReason::Legal->value)->toBe('legal');
        expect(SuppressionReason::Expired->value)->toBe('expired');
        expect(SuppressionReason::Policy->value)->toBe('policy');
        expect(SuppressionReason::System->value)->toBe('system');
    });

    it('provides readable labels', function (): void {
        foreach (SuppressionReason::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

test('all enums are string-backed', function (): void {
    $enums = [
        CommunicationDirection::class,
        CommunicationCategory::class,
        CommunicationPriority::class,
        CommunicationStatus::class,
        DeliveryStatus::class,
        ThreadStatus::class,
        TemplateStatus::class,
        RecipientRole::class,
        CommunicationEventSource::class,
        SuppressionReason::class,
    ];

    foreach ($enums as $enum) {
        $cases = $enum::cases();
        expect($cases)->not->toBeEmpty();
        foreach ($cases as $case) {
            expect($case->value)->toBeString();
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    }
});
