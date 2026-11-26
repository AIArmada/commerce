<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

final readonly class ReplaceGeneralContactMethods
{
    public function __construct(private CreateContactMethodAction $create) {}

    /** @param array<string, string|null> $changes */
    public function execute(Model $contactable, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        if (array_diff(array_keys($changes), ['email', 'website']) !== []) {
            throw new InvalidArgumentException('Only general email and website replacements are supported.');
        }

        $connection = $contactable->getConnection();

        if ($connection !== (new ContactMethod)->getConnection()) {
            throw new LogicException('Contact replacement requires a shared database connection.');
        }

        $connection->transaction(function () use ($contactable, $changes): void {
            $contactable->newQuery()->whereKey($contactable->getKey())->lockForUpdate()->firstOrFail();

            foreach ($changes as $type => $value) {
                ContactMethod::query()
                    ->where('contactable_type', $contactable->getMorphClass())
                    ->where('contactable_id', $contactable->getKey())
                    ->where('type', $type)
                    ->where('purpose', 'general')
                    ->delete();

                if ($value !== null) {
                    $this->create->execute($contactable, match ($type) {
                        'email' => ContactMethodData::email($value, 'general'),
                        'website' => ContactMethodData::website($value, 'general'),
                        default => throw new InvalidArgumentException('Unsupported contact type.'),
                    });
                }
            }
        }, attempts: 3);
    }
}
