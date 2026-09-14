<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleIssuerResource;
use AIArmada\Persons\Support\ModelResolver;
use AIArmada\Persons\Support\PersonsModelReferenceGuard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateTitleIssuer extends CreateRecord
{
    protected static string $resource = TitleIssuerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assertInstitutionReference($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertInstitutionReference(array $data): void
    {
        $institutionId = $data['institution_id'] ?? null;

        if ($institutionId === null || $institutionId === '') {
            return;
        }

        try {
            app(PersonsModelReferenceGuard::class)->resolve(
                ModelResolver::institutionClass(),
                $institutionId,
                'title issuer institution',
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'institution_id' => [$exception->getMessage()],
            ]);
        }
    }
}
