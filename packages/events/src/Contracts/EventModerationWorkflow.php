<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventReview;
use AIArmada\Events\Models\EventSubmission;
use Illuminate\Database\Eloquent\Model;

interface EventModerationWorkflow
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function submit(Event $event, ?Model $actor = null, array $context = []): EventSubmission;

    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(Event $event, EventModerationStatus $decision, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function approve(Event $event, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestChanges(Event $event, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function reject(Event $event, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(Event $event, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function reconsider(Event $event, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function revertToDraft(Event $event, ?Model $actor = null, array $context = []): EventReview;

    /**
     * @param  array<string, mixed>  $context
     */
    public function remoderate(Event $event, ?Model $actor = null, array $context = []): EventReview;
}
