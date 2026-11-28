<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Docs\Features\Numbering\Models\DocSequence;
use AIArmada\Docs\Features\Numbering\Models\SequenceNumber;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\Models\DocEInvoiceSubmission;
use AIArmada\Docs\Features\Delivery\Models\DocEmail;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Features\Delivery\Models\DocShareLink;
use AIArmada\Docs\Models\DocStatusHistory;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Features\Versioning\Models\DocVersion;
use AIArmada\Docs\Models\DocWorkflow;
use AIArmada\Docs\Models\DocWorkflowStep;
use OwenIt\Auditing\Contracts\Auditable;

it('docs models are auditable and activity loggable', function (): void {
    $models = [
        Doc::class,
        DocVersion::class,
        DocStatusHistory::class,
        DocPayment::class,
        DocEmail::class,
        DocEmailTemplate::class,
        DocTemplate::class,
        DocApproval::class,
        DocShareLink::class,
        DocEInvoiceSubmission::class,
        DocWorkflow::class,
        DocWorkflowStep::class,
        DocSequence::class,
        SequenceNumber::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});
