<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Actions\BulkFraudReviewAction;

it('bulk fraud review action has correct default name', function (): void {
    expect(BulkFraudReviewAction::getDefaultName())->toBe('bulk_fraud_review');
});
