<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Api\EventCreationData;
use AIArmada\Events\Features\Catalog\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class CreateEventFromSubmissionAction
{
    public function handle(EventCreationData $data): string
    {
        $owner = $this->resolveOwnerModel($data->ownerType, $data->ownerId);

        return OwnerContext::withOwner($owner, function () use ($data): string {
            $event = Event::query()->create([
                'title' => $data->title,
                'summary' => $data->summary,
                'description' => $data->description,
                'type' => $data->type,
                'status' => $data->status,
                'visibility' => $data->visibility,
                'delivery_mode' => $data->deliveryMode,
                'timezone' => $data->timezone,
            ]);

            if ($data->startsAt !== null) {
                $startsAt = CarbonImmutable::parse($data->startsAt);

                $event->occurrences()->create([
                    'title' => $data->title,
                    'starts_at' => $startsAt,
                    'ends_at' => $data->endsAt !== null
                        ? CarbonImmutable::parse($data->endsAt)
                        : $startsAt->addHours(2),
                    'timezone' => $data->timezone,
                    'status' => 'scheduled',
                    'visibility' => $event->visibility,
                ]);
            }

            foreach ($data->involvements as $involvement) {
                $event->involvements()->create($involvement);
            }

            foreach ($data->classifications as $classification) {
                $event->classifications()->create($classification);
            }

            return (string) $event->getKey();
        });
    }

    private function resolveOwnerModel(string $type, string $id): Model
    {
        $ownerClass = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($ownerClass) || ! is_a($ownerClass, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('The submission target type [%s] is not an Eloquent model.', $type));
        }

        $owner = $ownerClass::query()->find($id);

        if (! $owner instanceof Model) {
            throw new InvalidArgumentException(sprintf('The submission target [%s:%s] could not be resolved.', $type, $id));
        }

        return $owner;
    }
}
