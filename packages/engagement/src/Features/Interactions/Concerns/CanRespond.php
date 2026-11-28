<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Concerns;

use AIArmada\Engagement\Features\Interactions\Actions\ManageResponsesAction;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Traits\InteractsWithEngagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanRespond
{
    use InteractsWithEngagement;

    /**
     * @return MorphMany<Response, $this>
     */
    public function responses(): MorphMany
    {
        return $this->morphMany(Response::class, 'responder');
    }

    public function respond(mixed $subject, string $type, array $options = []): Response
    {
        return app(ManageResponsesAction::class)->respond($this->engagementActor(), $subject, $type, $options);
    }

    public function cancelResponse(mixed $subject): void
    {
        app(ManageResponsesAction::class)->cancelResponse($this->engagementActor(), $subject);
    }
}
