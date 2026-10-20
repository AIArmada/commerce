<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

/**
 * Resolves the owner-safe billable model behind a payment request.
 *
 * This trait owns only frozen model identity and the owner boundary. It does
 * not require any Cashier contract: each adapter validates the resolved model
 * against its own contract, so a native CashierChip model is never rejected
 * because it lacks the unified Cashier interface.
 *
 * A frozen request is authoritative. A frozen model that cannot be resolved
 * fails closed instead of falling back to the session's current billable, and
 * an explicitly frozen guest identity can never become a billable charge on
 * replay.
 */
trait ResolvesFrozenBillable
{
    /**
     * Whether the request carries a frozen customer identity. An explicit
     * frozen guest (null type and id) counts: that null is the decision.
     */
    protected function hasFrozenBillableIdentity(?PaymentRequest $request): bool
    {
        return $request !== null && (
            $request->billableFrozen
            || $request->billableType !== null
            || $request->billableId !== null
        );
    }

    /**
     * Resolve the exact model frozen into the request. Returns null for an
     * explicit frozen guest and for any frozen identity that cannot be
     * resolved safely; callers must not substitute live state in that case.
     */
    protected function resolveFrozenBillableModel(CheckoutSession $session, PaymentRequest $request): ?Model
    {
        $type = $request->billableType;
        $id = $request->billableId;

        // Explicit frozen guest/no-billable identity. Falling back here would
        // turn a guest attempt into a charge against whoever is associated
        // with the session now.
        if ($type === null && $id === null) {
            return null;
        }

        if (! is_string($type) || ! is_string($id) || mb_trim($id) === '') {
            return null;
        }

        $class = $this->concreteBillableClass($type);

        if ($class === null) {
            return null;
        }

        $owner = $session->hasOwner() ? $session->owner : null;
        $model = OwnerContext::withOwner(
            $owner,
            static fn (): ?Model => (new $class)->newQuery()->whereKey($id)->first(),
        );

        if (! $model instanceof Model) {
            return null;
        }

        return $this->billableBelongsToSessionOwner($session, $model) ? $model : null;
    }

    /**
     * The request frozen into the session's current attempt claim, if the
     * snapshot exists. Null means the session has no frozen identity and the
     * caller may consult live session relations.
     */
    protected function frozenBillableRequestFromSession(CheckoutSession $session): ?PaymentRequest
    {
        $snapshot = data_get($session->payment_data ?? [], 'payment_attempt.request');

        return is_array($snapshot) && $snapshot !== []
            ? PaymentRequest::fromSnapshot($snapshot)
            : null;
    }

    /**
     * Resolve the frozen type to the concrete model class that was authorized
     * at claim time. Aliases are resolved through the morph map, and an
     * enforced map rejects any class it cannot name.
     *
     * @return class-string<Model>|null
     */
    private function concreteBillableClass(string $type): ?string
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        try {
            $morphClass = (new $class)->getMorphClass();
        } catch (Throwable) {
            return null;
        }

        if (Relation::requiresMorphMap() && Relation::getMorphedModel($morphClass) === null) {
            return null;
        }

        return $class;
    }

    private function billableBelongsToSessionOwner(CheckoutSession $session, Model $model): bool
    {
        if (! method_exists($model, 'hasOwner')) {
            return true;
        }

        if (! $session->hasOwner()) {
            return ! $model->hasOwner();
        }

        $sessionOwner = $session->owner;

        if ($sessionOwner instanceof Model && method_exists($model, 'belongsToOwner')) {
            return $model->belongsToOwner($sessionOwner);
        }

        $modelConfig = method_exists($model, 'ownerScopeConfig')
            ? $model->ownerScopeConfig()
            : null;
        $typeColumn = $modelConfig?->ownerTypeColumn ?? 'owner_type';
        $idColumn = $modelConfig?->ownerIdColumn ?? 'owner_id';

        return $this->ownerTuple(
            $session->getAttribute('owner_type'),
            $session->getAttribute('owner_id'),
        ) === $this->ownerTuple(
            $model->getAttribute($typeColumn),
            $model->getAttribute($idColumn),
        );
    }

    private function ownerTuple(mixed $type, mixed $id): ?string
    {
        if (! is_string($type) || $type === '' || $id === null || $id === '') {
            return null;
        }

        return (Relation::getMorphedModel($type) ?? $type) . ':' . (string) $id;
    }
}
