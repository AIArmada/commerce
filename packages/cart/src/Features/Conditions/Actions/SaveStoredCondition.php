<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Actions;

use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use InvalidArgumentException;

final readonly class SaveStoredCondition
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Condition
    {
        $condition = new Condition;
        $condition->fill($this->normalize($data));

        if (Condition::ownerScopingEnabled()) {
            $owner = Condition::resolveCurrentOwner();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);

            if ($owner !== null) {
                $condition->assignOwner($owner);
            }
        }

        $condition->save();

        return $condition;
    }

    /** @param array<string, mixed> $data */
    public function update(Condition $condition, array $data): Condition
    {
        if (Condition::ownerScopingEnabled()) {
            $condition = OwnerWriteGuard::findOrFailForOwner(
                Condition::class,
                (string) $condition->getKey(),
                includeGlobal: false,
                message: 'Condition is not accessible in the current owner scope.',
            );
        }

        $condition->fill($this->normalize($data));
        $condition->save();

        return $condition;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (array_key_exists('attributes', $data)) {
            if (! is_array($data['attributes'])) {
                throw new InvalidArgumentException('Condition attributes must be an array.');
            }

            foreach (Condition::RESERVED_ATTRIBUTE_KEYS as $reserved) {
                if (array_key_exists($reserved, $data['attributes'])) {
                    throw new InvalidArgumentException("Condition attribute [{$reserved}] is reserved.");
                }
            }
        }

        if (array_key_exists('rules', $data)) {
            $data['rules'] = Condition::normalizeRulesDefinition(
                $data['rules'],
                ! empty($data['rules']['factory_keys'] ?? []),
            );
        }

        return $data;
    }
}
