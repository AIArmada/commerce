<?php

declare(strict_types=1);

namespace AIArmada\Docs\Facades;

use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateDocNumber(string $docType = 'invoice')
 * @method static \AIArmada\Docs\Models\Doc createDoc(DocData $data)
 * @method static string generatePdf(\AIArmada\Docs\Models\Doc $doc, bool $save = true)
 * @method static string downloadPdf(\AIArmada\Docs\Models\Doc $doc)
 * @method static void emailDoc(\AIArmada\Docs\Models\Doc $doc, string $email)
 * @method static void updateDocStatus(\AIArmada\Docs\Models\Doc $doc, DocStatus $status, ?string $notes = null)
 *
 * @see \AIArmada\Docs\Services\DocService
 */
class Doc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'doc';
    }
}
