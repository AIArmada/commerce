<?php

declare(strict_types=1);

namespace AIArmada\Docs\Http\Controllers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DocPreviewController
{
    public function __invoke(Doc | string $doc, DocRenderService $renderer): Response
    {
        $ownerEnabled = (bool) config('docs.owner.enabled', false);

        if ($doc instanceof Doc) {
            if ($ownerEnabled) {
                try {
                    /** @var Doc $docModel */
                    $docModel = OwnerWriteGuard::findOrFailForOwner(
                        Doc::class,
                        (string) $doc->getKey(),
                        OwnerContext::CURRENT,
                        (bool) config('docs.owner.include_global', false),
                    );
                } catch (AuthorizationException) {
                    throw new NotFoundHttpException('Document not found.');
                }
            } else {
                $docModel = $doc;
            }
        } else {
            if ($ownerEnabled) {
                try {
                    /** @var Doc $docModel */
                    $docModel = OwnerWriteGuard::findOrFailForOwner(
                        Doc::class,
                        $doc,
                        OwnerContext::CURRENT,
                        (bool) config('docs.owner.include_global', false),
                    );
                } catch (AuthorizationException) {
                    throw new NotFoundHttpException('Document not found.');
                }
            } else {
                $docModel = Doc::query()->find($doc);

                if (! $docModel instanceof Doc) {
                    throw new NotFoundHttpException('Document not found.');
                }
            }
        }

        return response($renderer->renderHtml($docModel, RenderAudience::AdminPreview)->toHtml());
    }
}
