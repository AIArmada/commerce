<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanRespond
{
    /**
     * @return MorphMany<Response, $this>
     */
    public function responses(): MorphMany
    {
        return $this->morphMany(Response::class, 'responder');
    }

    public function respond(mixed $subject, string $type, array $options = []): Response
    {
        return app(EngagementManager::class)->respond($this, $subject, $type, $options);
    }

    public function cancelResponse(mixed $subject): void
    {
        app(EngagementManager::class)->cancelResponse($this, $subject);
    }
}
