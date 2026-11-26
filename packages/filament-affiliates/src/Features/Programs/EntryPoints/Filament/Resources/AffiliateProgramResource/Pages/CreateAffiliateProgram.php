<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\Pages;

use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateProgram extends CreateRecord
{
    protected static string $resource = AffiliateProgramResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(ProgramAdministration::class)->create($data);

        return AffiliateProgram::query()->findOrFail($id);
    }
}
