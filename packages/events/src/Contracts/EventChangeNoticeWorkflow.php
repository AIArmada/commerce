<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChange;
use AIArmada\Events\Models\Occurrence;

interface EventChangeNoticeWorkflow
{
    /**
     * @param  array<string, mixed>  $changedSections
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function create(
        Event $event,
        string $changeKey,
        array $changedSections = [],
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>  $changedSections
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function bundle(
        Event $event,
        array $changedSections,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        string $changeKey = 'content_changed',
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function peopleChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function titleChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function topicChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function contentChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function scheduleChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function cancelled(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
        ?string $severity = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function postponed(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
        ?string $severity = null,
    ): EventChange;

    /**
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>  $metadata
     */
    public function replacementLinked(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
        ?string $severity = null,
    ): EventChange;

    public function publish(EventChange $notice): EventChange;

    public function retract(EventChange $notice): EventChange;
}
