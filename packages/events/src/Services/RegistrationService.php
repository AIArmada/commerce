<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationApproved;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Events\EventRegistrationCompleted;
use AIArmada\Events\Events\EventRegistrationCreated;
use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Events\EventRegistrationRefundPending;
use AIArmada\Events\Events\EventRegistrationRefundRestored;
use AIArmada\Events\Events\EventRegistrationRejected;
use AIArmada\Events\Events\EventRegistrationWaitlisted;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\States\RegistrationStatus\Cancelled;
use AIArmada\Events\States\RegistrationStatus\CheckedIn;
use AIArmada\Events\States\RegistrationStatus\Completed;
use AIArmada\Events\States\RegistrationStatus\Confirmed;
use AIArmada\Events\States\RegistrationStatus\NoShow;
use AIArmada\Events\States\RegistrationStatus\Pending;
use AIArmada\Events\States\RegistrationStatus\Refunded;
use AIArmada\Events\States\RegistrationStatus\RefundPending;
use AIArmada\Events\States\RegistrationStatus\RegistrationStatus as RegistrationStatusState;
use AIArmada\Events\States\RegistrationStatus\Rejected;
use AIArmada\Events\States\RegistrationStatus\Waitlisted;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RegistrationService implements RegistrationServiceInterface
{
    public const int MAX_CHILDREN_PER_REGISTRATION = 1000;

    private const int BULK_INSERT_CHUNK_SIZE = 50;

    public function register(array $data): EventRegistration
    {
        if (($data['event_id'] ?? null) === null || $data['event_id'] === '') {
            throw new InvalidArgumentException('An event_id is required to create a registration.');
        }

        EventWriteGuard::findOrFail($data['event_id']);

        $this->validateScopeLinkage($data);
        $this->validateRegistrationFields($data);
        $this->validateItems($data);
        $this->validateParticipants($data);
        $this->validateAnswers($data['answers'] ?? null, 'answers');

        $registration = DB::transaction(function () use ($data): EventRegistration {
            $registrationClass = ModelResolver::registrationClass();
            $initialStatus = $data['status'] ?? Pending::class;

            if (! is_string($initialStatus) && ! $initialStatus instanceof RegistrationStatusState) {
                throw new InvalidArgumentException('The registration status must be a state name or state class.');
            }

            $registration = new $registrationClass;
            $registration->fill(Arr::except($data, [
                'items',
                'participants',
                'answers',
                'status',
                'registered_at',
                'approved_at',
                'completed_at',
                'cancelled_at',
                'rejected_at',
                'waitlisted_at',
                'refund_pending_at',
                'refunded_at',
                'expired_at',
                'last_state_change_at',
            ]));
            $registration->initializeStatus($initialStatus);
            $registration->save();

            $scopeFields = [
                'event_id' => $registration->event_id,
                'event_occurrence_id' => $registration->event_occurrence_id,
                'event_session_id' => $registration->event_session_id,
            ];

            if (isset($data['participants'])) {
                foreach ($data['participants'] as $participantData) {
                    $participantAnswers = is_array($participantData['answers'] ?? null)
                        ? $participantData['answers']
                        : [];
                    $participantFields = array_merge(
                        Arr::except($participantData, ['email', 'phone', 'company', 'answers']),
                        $scopeFields,
                    );
                    $participant = $registration->participants()->create($participantFields);

                    if ($participant instanceof EventRegistrationParticipant) {
                        $this->syncParticipantContactMethods($participant, $participantData);
                        $this->bulkInsertParticipantAnswers($participant, $registration, $participantAnswers, $scopeFields);
                    }
                }
            }

            if (isset($data['items'])) {
                $itemRows = [];

                foreach ($data['items'] as $itemData) {
                    $itemRows[] = array_merge(
                        Arr::except($itemData, [
                            'id',
                            'event_registration_id',
                            'event_id',
                            'event_occurrence_id',
                            'event_session_id',
                        ]),
                        $scopeFields,
                    );
                }

                $this->bulkInsertChildren($registration->items(), $itemRows);
            }

            if (isset($data['answers'])) {
                $participantIds = array_map(
                    static fn (mixed $id): string => (string) $id,
                    $registration->participants()->pluck('id')->all()
                );
                $answerRows = [];

                foreach ($data['answers'] as $answerData) {
                    $participantId = $answerData['event_registration_participant_id'] ?? null;

                    if ($participantId !== null) {
                        $this->assertParticipantBelongsToRegistration($participantIds, $participantId);
                    }

                    $answerRows[] = array_merge(
                        Arr::except($answerData, [
                            'id',
                            'event_registration_id',
                            'event_id',
                            'event_occurrence_id',
                            'event_session_id',
                        ]),
                        $scopeFields,
                    );
                }

                $this->bulkInsertChildren($registration->answers(), $answerRows);
            }

            return $registration;
        });

        event(new EventRegistrationCreated($registration));

        return $registration;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateScopeLinkage(array $data): void
    {
        $eventId = (string) $data['event_id'];

        if (($data['event_occurrence_id'] ?? null) !== null) {
            $occurrence = EventOccurrence::query()->whereKey($data['event_occurrence_id'])->first();

            if ($occurrence === null || (string) $occurrence->event_id !== $eventId) {
                throw new InvalidArgumentException('The selected occurrence does not belong to the selected event.');
            }
        }

        if (($data['event_session_id'] ?? null) !== null) {
            $session = EventSession::query()->whereKey($data['event_session_id'])->first();

            if ($session === null || (string) $session->event_id !== $eventId) {
                throw new InvalidArgumentException('The selected session does not belong to the selected event.');
            }

            if (($data['event_occurrence_id'] ?? null) !== null
                && (string) $session->event_occurrence_id !== (string) $data['event_occurrence_id']) {
                throw new InvalidArgumentException('The selected session does not belong to the selected event occurrence.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRegistrationFields(array $data): void
    {
        $this->assertNullOrNonEmptyString($data['registration_no'] ?? null, 'registration_no', 64);
        $this->assertNullOrNonEmptyString($data['registration_type'] ?? null, 'registration_type', 64);
        $this->assertNullOrNonEmptyString($data['source'] ?? null, 'source', 64);
        $this->assertNullOrNonEmptyString($data['currency'] ?? null, 'currency', 16);
        $this->assertNullOrNonEmptyString($data['payment_status'] ?? null, 'payment_status', 64);
        $this->assertNullOrNonEmptyString($data['external_order_id'] ?? null, 'external_order_id', 64);
        $this->assertNullOrNonEmptyString($data['idempotency_key'] ?? null, 'idempotency_key', 255);

        if (array_key_exists('total_participants', $data) && $data['total_participants'] !== null) {
            $this->assertPositiveInt($data['total_participants'], 'total_participants');
        }

        if (array_key_exists('total_amount', $data) && $data['total_amount'] !== null) {
            $this->assertNonNegativeInt($data['total_amount'], 'total_amount');
        }

        foreach (['status_reason', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && ! is_string($data[$field])) {
                throw new InvalidArgumentException(sprintf('The registration %s must be a string.', $field));
            }
        }

        if (array_key_exists('is_bundle_root', $data) && ! is_bool($data['is_bundle_root']) && ! in_array($data['is_bundle_root'], [0, 1], true)) {
            throw new InvalidArgumentException('The registration is_bundle_root flag must be a boolean.');
        }

        foreach (['pass_entitlements', 'metadata'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && ! is_array($data[$field])) {
                throw new InvalidArgumentException(sprintf('The registration %s must be an array.', $field));
            }
        }

        if (($data['external_order_type'] ?? null) !== null) {
            $this->resolveModelClass($data['external_order_type'], 'external_order_type');
        }

        $this->validateMorphPair($data['registrant_type'] ?? null, $data['registrant_id'] ?? null, 'registrant', true);

        if (($data['parent_registration_id'] ?? null) !== null) {
            $registrationClass = ModelResolver::registrationClass();
            $parent = $registrationClass::query()->whereKey($data['parent_registration_id'])->first();

            if ($parent === null || (string) $parent->event_id !== (string) $data['event_id']) {
                throw new InvalidArgumentException('The parent registration does not belong to the selected event.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateItems(array $data): void
    {
        if (! array_key_exists('items', $data) || $data['items'] === null) {
            return;
        }

        if (! is_array($data['items'])) {
            throw new InvalidArgumentException('Registration items must be an array.');
        }

        if (count($data['items']) > self::MAX_CHILDREN_PER_REGISTRATION) {
            throw new InvalidArgumentException(sprintf(
                'A registration may not carry more than %d items.',
                self::MAX_CHILDREN_PER_REGISTRATION,
            ));
        }

        $ticketTypeIds = [];

        foreach ($data['items'] as $itemData) {
            if (! is_array($itemData)) {
                throw new InvalidArgumentException('Each registration item must be an array.');
            }

            if (($itemData['ticket_type_id'] ?? null) !== null) {
                $ticketTypeIds[] = $itemData['ticket_type_id'];
            }
        }

        $ticketTypes = TicketType::query()
            ->whereIn('id', array_unique($ticketTypeIds))
            ->get()
            ->keyBy(fn (TicketType $ticketType): string => (string) $ticketType->getKey());

        foreach ($data['items'] as $itemData) {
            $ticketTypeId = $itemData['ticket_type_id'] ?? null;

            if ($ticketTypeId === null || $ticketTypeId === '') {
                throw new InvalidArgumentException('Each registration item must reference a ticket type.');
            }

            $ticketType = $ticketTypes->get((string) $ticketTypeId);

            if (! $ticketType instanceof TicketType) {
                throw new InvalidArgumentException('The selected ticket type does not exist or is not visible in this owner scope.');
            }

            $ticketType->loadMissing('ticketable');
            $ticketEvent = EventTicketScope::event($ticketType);

            if ($ticketEvent === null || (string) $ticketEvent->getKey() !== (string) $data['event_id']) {
                throw new InvalidArgumentException('Registration items must reference a ticket type that belongs to the same event.');
            }

            if (array_key_exists('quantity', $itemData) && $itemData['quantity'] !== null) {
                $this->assertPositiveInt($itemData['quantity'], 'items.quantity');
            }

            if (array_key_exists('unit_price', $itemData) && $itemData['unit_price'] !== null) {
                $this->assertNonNegativeInt($itemData['unit_price'], 'items.unit_price');
            }

            if (array_key_exists('total_price', $itemData) && $itemData['total_price'] !== null) {
                $this->assertNonNegativeInt($itemData['total_price'], 'items.total_price');
            }

            $this->assertNullOrNonEmptyString($itemData['currency'] ?? null, 'items.currency', 16);
            $this->assertNullOrNonEmptyString($itemData['status'] ?? null, 'items.status', 32);
            $this->assertNullOrNonEmptyString($itemData['external_order_item_id'] ?? null, 'items.external_order_item_id', 64);

            if (($itemData['external_order_item_type'] ?? null) !== null) {
                $this->resolveModelClass($itemData['external_order_item_type'], 'items.external_order_item_type');
            }

            if (array_key_exists('metadata', $itemData) && $itemData['metadata'] !== null && ! is_array($itemData['metadata'])) {
                throw new InvalidArgumentException('Registration item metadata must be an array.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateParticipants(array $data): void
    {
        if (! array_key_exists('participants', $data) || $data['participants'] === null) {
            return;
        }

        if (! is_array($data['participants'])) {
            throw new InvalidArgumentException('Registration participants must be an array.');
        }

        if (count($data['participants']) > self::MAX_CHILDREN_PER_REGISTRATION) {
            throw new InvalidArgumentException(sprintf(
                'A registration may not carry more than %d participants.',
                self::MAX_CHILDREN_PER_REGISTRATION,
            ));
        }

        foreach ($data['participants'] as $participantData) {
            if (! is_array($participantData)) {
                throw new InvalidArgumentException('Each registration participant must be an array.');
            }

            $this->assertNullOrNonEmptyString($participantData['name'] ?? null, 'participants.name', 255);
            $this->assertNullOrNonEmptyString($participantData['relationship_to_registrant'] ?? null, 'participants.relationship_to_registrant', 255);
            $this->assertNullOrNonEmptyString($participantData['gender'] ?? null, 'participants.gender', 64);
            $this->assertNullOrNonEmptyString($participantData['status'] ?? null, 'participants.status', 32);
            $this->assertNullOrNonEmptyString($participantData['phone'] ?? null, 'participants.phone', 64);

            $email = $this->cleanString($participantData['email'] ?? null);

            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Each participant email must be a valid email address.');
            }

            if (array_key_exists('age', $participantData) && $participantData['age'] !== null) {
                if (! is_int($participantData['age']) || $participantData['age'] < 0 || $participantData['age'] > 150) {
                    throw new InvalidArgumentException('Each participant age must be an integer between 0 and 150.');
                }
            }

            foreach (['is_primary', 'is_purchaser'] as $flag) {
                if (array_key_exists($flag, $participantData) && ! is_bool($participantData[$flag]) && ! in_array($participantData[$flag], [0, 1], true)) {
                    throw new InvalidArgumentException(sprintf('The participant %s flag must be a boolean.', $flag));
                }
            }

            if (array_key_exists('notes', $participantData) && $participantData['notes'] !== null && ! is_string($participantData['notes'])) {
                throw new InvalidArgumentException('Participant notes must be a string.');
            }

            if (array_key_exists('metadata', $participantData) && $participantData['metadata'] !== null && ! is_array($participantData['metadata'])) {
                throw new InvalidArgumentException('Participant metadata must be an array.');
            }

            $this->validateMorphPair(
                $participantData['participant_type'] ?? null,
                $participantData['participant_id'] ?? null,
                'participant',
                true,
            );

            if (array_key_exists('answers', $participantData) && $participantData['answers'] !== null) {
                $this->validateAnswers($participantData['answers'], 'participants.answers');
            }
        }
    }

    private function validateAnswers(mixed $answers, string $field): void
    {
        if ($answers === null) {
            return;
        }

        if (! is_array($answers)) {
            throw new InvalidArgumentException(sprintf('Registration %s must be an array.', $field));
        }

        if (count($answers) > self::MAX_CHILDREN_PER_REGISTRATION) {
            throw new InvalidArgumentException(sprintf(
                'A registration may not carry more than %d answers.',
                self::MAX_CHILDREN_PER_REGISTRATION,
            ));
        }

        foreach ($answers as $answerData) {
            if (! is_array($answerData)) {
                throw new InvalidArgumentException('Each registration answer must be an array.');
            }

            foreach (['metadata', 'answer_json'] as $jsonField) {
                if (array_key_exists($jsonField, $answerData) && $answerData[$jsonField] !== null && ! is_array($answerData[$jsonField])) {
                    throw new InvalidArgumentException(sprintf('Registration answer %s must be an array.', $jsonField));
                }
            }
        }
    }

    private function validateMorphPair(mixed $type, mixed $id, string $label, bool $requireExists): void
    {
        if ($type === null && $id === null) {
            return;
        }

        if ($type === null || $id === null || $id === '') {
            throw new InvalidArgumentException(sprintf('The %s type and id must both be present or both be null.', $label));
        }

        $class = $this->resolveModelClass($type, $label . '_type');

        if ($requireExists && ! $class::query()->whereKey($id)->exists()) {
            throw new InvalidArgumentException(sprintf('The selected %s does not exist or is not visible in this owner scope.', $label));
        }
    }

    /**
     * @return class-string<Model>
     */
    private function resolveModelClass(mixed $type, string $field): string
    {
        if (! is_string($type) || $type === '') {
            throw new InvalidArgumentException(sprintf('The %s must be a model class name.', $field));
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('The %s must reference an Eloquent model.', $field));
        }

        return $class;
    }

    /**
     * @param  array<int, mixed>  $answersData
     * @param  array<string, mixed>  $scopeFields
     */
    private function bulkInsertParticipantAnswers(
        EventRegistrationParticipant $participant,
        EventRegistration $registration,
        array $answersData,
        array $scopeFields,
    ): void {
        $rows = [];

        foreach ($answersData as $answerData) {
            if (! is_array($answerData)) {
                throw new InvalidArgumentException('Each participant answer must be an array.');
            }

            $rows[] = array_merge(
                Arr::except($answerData, [
                    'id',
                    'event_registration_id',
                    'event_registration_participant_id',
                    'event_id',
                    'event_occurrence_id',
                    'event_session_id',
                ]),
                ['event_registration_id' => $registration->getKey()],
                $scopeFields,
            );
        }

        $this->bulkInsertChildren($participant->answers(), $rows);
    }

    /**
     * Insert child rows in chunks instead of one query per row. Applies the
     * same fill rules, UUIDs, and timestamps a per-row create() would, so the
     * only behavioral difference is the query count. Rows are grouped by
     * column shape so omitted columns keep their database defaults.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function bulkInsertChildren(HasMany $relation, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = CarbonImmutable::now();
        $foreignKey = $relation->getForeignKeyName();
        $parentKey = $relation->getParent()->getKey();
        $grouped = [];

        foreach ($rows as $attributes) {
            $model = $relation->getRelated()->newInstance();
            $model->fill($attributes);
            $model->setAttribute($foreignKey, $parentKey);
            $model->setUniqueIds();

            if ($model->usesTimestamps()) {
                $model->setAttribute($model->getCreatedAtColumn(), $now);
                $model->setAttribute($model->getUpdatedAtColumn(), $now);
            }

            $columns = array_keys($model->getAttributes());
            sort($columns);
            $signature = implode("\0", $columns);
            $grouped[$signature]['columns'] = $columns;
            $grouped[$signature]['rows'][] = $model->getAttributes();
        }

        foreach ($grouped as $group) {
            $ordered = [];

            foreach ($group['rows'] as $row) {
                $normalized = [];

                foreach ($group['columns'] as $column) {
                    $normalized[$column] = $row[$column];
                }

                $ordered[] = $normalized;
            }

            foreach (array_chunk($ordered, self::BULK_INSERT_CHUNK_SIZE) as $chunk) {
                $relation->getRelated()->newQuery()->insert($chunk);
            }
        }
    }

    /**
     * @param  array<int, string>  $participantIds  Preloaded IDs of this registration's participants.
     */
    private function assertParticipantBelongsToRegistration(array $participantIds, mixed $participantId): void
    {
        if (! in_array((string) $participantId, $participantIds, true)) {
            throw new InvalidArgumentException('The selected answer participant does not belong to this registration.');
        }
    }

    private function assertNullOrNonEmptyString(mixed $value, string $field, int $maxLength): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException(sprintf('The %s must be a non-empty string.', $field));
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('The %s may not exceed %d characters.', $field, $maxLength));
        }
    }

    private function assertPositiveInt(mixed $value, string $field): void
    {
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 1) {
            return;
        }

        throw new InvalidArgumentException(sprintf('The %s must be a positive integer.', $field));
    }

    private function assertNonNegativeInt(mixed $value, string $field): void
    {
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0) {
            return;
        }

        throw new InvalidArgumentException(sprintf('The %s must be a non-negative integer amount in minor units.', $field));
    }

    public function approve(EventRegistration $registration, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Confirmed) {
            if ($registration->approved_at === null) {
                $registration->transitionStatus(Confirmed::class);
            }

            return;
        }

        $registration->transitionStatus(Confirmed::class);

        event(new EventRegistrationApproved($registration));
    }

    public function cancel(EventRegistration $registration, ?string $reason = null, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Cancelled) {
            if ($registration->cancelled_at === null) {
                $registration->status_reason = $reason;
                $registration->transitionStatus(Cancelled::class);
            }

            return;
        }

        $registration->status_reason = $reason;
        $registration->transitionStatus(Cancelled::class);

        event(new EventRegistrationCancelled($registration, $reason));
    }

    public function reject(EventRegistration $registration, string $reason, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Rejected) {
            if ($registration->rejected_at === null) {
                $registration->status_reason = $reason;
                $registration->transitionStatus(Rejected::class);
            }

            return;
        }

        $registration->status_reason = $reason;
        $registration->transitionStatus(Rejected::class);

        event(new EventRegistrationRejected($registration, $reason));
    }

    public function waitlist(EventRegistration $registration): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Waitlisted) {
            if ($registration->waitlisted_at === null) {
                $registration->transitionStatus(Waitlisted::class);
            }

            return;
        }

        $registration->transitionStatus(Waitlisted::class);

        event(new EventRegistrationWaitlisted($registration));
    }

    public function complete(EventRegistration $registration): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Completed) {
            if ($registration->completed_at === null) {
                $registration->transitionStatus(Completed::class);
            }

            return;
        }

        $registration->transitionStatus(Completed::class);

        event(new EventRegistrationCompleted($registration));
    }

    public function refund(EventRegistration $registration, ?string $reason = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof Refunded) {
            if ($registration->refunded_at === null) {
                $registration->status_reason = $reason;
                $registration->transitionStatus(Refunded::class);
            }

            return;
        }

        $registration->refund_pending_at = null;
        $registration->status_reason = $reason;
        $registration->transitionStatus(Refunded::class);

        event(new EventRegistrationRefunded($registration, $reason));
    }

    public function markRefundPending(EventRegistration $registration, ?string $reason = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if ($registration->status instanceof RefundPending) {
            if ($registration->refund_pending_at === null) {
                $registration->transitionStatus(RefundPending::class);
            }

            return;
        }

        $metadata = $registration->metadata ?? [];
        $metadata['refund']['original_status'] = $registration->status->getValue();

        $registration->status_reason = $reason;
        $registration->metadata = $metadata;
        $registration->transitionStatus(RefundPending::class);

        event(new EventRegistrationRefundPending($registration, $reason));
    }

    public function restoreFromRefundPending(EventRegistration $registration, ?string $reason = null): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if (! $registration->status instanceof RefundPending) {
            return;
        }

        $originalStatus = data_get($registration->metadata ?? [], 'refund.original_status', 'confirmed');
        $targetState = match ($originalStatus) {
            'checked_in' => CheckedIn::class,
            'no_show' => NoShow::class,
            'completed' => Completed::class,
            default => Confirmed::class,
        };

        $metadata = $registration->metadata ?? [];
        Arr::forget($metadata, 'refund.original_status');

        $registration->status_reason = $reason;
        $registration->metadata = $metadata;
        $registration->refund_pending_at = null;
        $registration->transitionStatus($targetState, overwriteLifecycleTimestamp: false);

        event(new EventRegistrationRefundRestored($registration, $reason));
    }

    public function createFromOrderItem(array $orderItemData): void
    {
        if (($orderItemData['event_id'] ?? null) === null || $orderItemData['event_id'] === '') {
            throw new InvalidArgumentException('An event_id is required to create a registration.');
        }

        EventWriteGuard::findOrFail($orderItemData['event_id']);

        $registrationClass = ModelResolver::registrationClass();
        $registration = new $registrationClass;
        $registration->fill([
            'event_id' => $orderItemData['event_id'],
            'event_occurrence_id' => $orderItemData['event_occurrence_id'] ?? null,
            'event_session_id' => $orderItemData['event_session_id'] ?? null,
            'registrant_type' => $orderItemData['registrant_type'] ?? null,
            'registrant_id' => $orderItemData['registrant_id'] ?? null,
            'registration_type' => $orderItemData['registration_type'] ?? 'standard',
            'source' => 'order',
            'total_participants' => $orderItemData['quantity'] ?? 1,
            'total_amount' => $orderItemData['total_price'] ?? 0,
            'currency' => $orderItemData['currency'] ?? config('events.defaults.currency', 'MYR'),
            'external_order_id' => $orderItemData['order_id'] ?? null,
            'external_order_type' => $orderItemData['order_type'] ?? null,
        ]);
        $registration->initializeStatus(Pending::class);
        $registration->save();

        event(new EventRegistrationCreated($registration));
    }

    /**
     * @param  array<string, mixed>  $participantData
     */
    private function syncParticipantContactMethods(EventRegistrationParticipant $participant, array $participantData): void
    {
        $email = $this->cleanString($participantData['email'] ?? null);

        if ($email !== null) {
            $participant->addContactMethod(new ContactMethodData(
                type: 'email',
                purpose: 'general',
                value: $email,
                isPrimary: true,
            ));
        }

        $phone = $this->cleanString($participantData['phone'] ?? null);

        if ($phone !== null) {
            $participant->addContactMethod(new ContactMethodData(
                type: 'phone',
                purpose: 'general',
                value: $phone,
                countryCode: config('contacting.defaults.country_code', 'MY'),
                isPrimary: true,
            ));
        }
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $cleaned = mb_trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
