<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Api;

interface DeliveryReferenceValidator
{
    /**
     * @param  list<string>  $recipientIds
     * @param  list<string>  $contentIds
     */
    public function validate(string $communicationId, array $recipientIds, array $contentIds): void;
}
