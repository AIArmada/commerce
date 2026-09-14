<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface HasSubscriptionMatchContext
{
    /**
     * Extra key/value pairs the subject opts into subscription criteria
     * matching. The match command only sees subject_type/subject_id plus
     * these pairs; raw model attributes are never exposed.
     *
     * @return array<string, mixed>
     */
    public function subscriptionMatchContext(): array;
}
