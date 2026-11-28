<?php

declare(strict_types=1);

namespace AIArmada\References\Persistence;

use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ReferenceContributorLinks
{
    /**
     * @param  list<string>  $ids
     */
    public function deleteIds(Reference $reference, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $model = $this->model($reference);
        $deleted = $model->newQueryWithoutScopes()
            ->where('reference_id', $reference->getKey())
            ->whereKey($ids)
            ->delete();

        if ($deleted !== count($ids)) {
            throw new RuntimeException('Contributor deletion did not remove the expected rows.');
        }
    }

    /**
     * @param  list<string>  $ids
     */
    public function insertIds(Reference $reference, ReferenceContributorRole $role, string $type, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $model = $this->model($reference);
        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($ids as $id) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'reference_id' => (string) $reference->getKey(),
                'contributor_type' => $type,
                'contributor_id' => $id,
                'role' => $role->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = $model->newQueryWithoutScopes()->toBase()->insert($rows);

        if ($inserted !== true) {
            throw new RuntimeException('Contributor creation did not insert the expected rows.');
        }
    }

    private function model(Reference $reference): ReferenceContributor
    {
        $model = new ReferenceContributor;
        $model->setConnection($reference->getConnectionName());

        return $model;
    }
}
