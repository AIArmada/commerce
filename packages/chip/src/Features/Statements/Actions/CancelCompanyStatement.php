<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Statements\Actions;

use AIArmada\Chip\Features\Statements\Models\CompanyStatement;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CancelCompanyStatement
{
    public function __construct(private ChipCollectService $collect) {}

    public function handle(string $id): void
    {
        $record = CompanyStatement::query()->forOwner(OwnerContext::CURRENT, false)->findOrFail($id);
        $snapshot = $this->collect->cancelCompanyStatement($id);

        if ($snapshot->id !== $id) {
            throw new AuthorizationException('CHIP response does not match the requested statement.');
        }

        $record->fill($snapshot->toArray())->save();
    }
}
