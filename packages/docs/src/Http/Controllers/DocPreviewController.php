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
        if (! $doc instanceof Doc) {
            if ((bool) config('docs.owner.enabled', false)) {
                try {
                    /** @var Doc $doc */
                    $doc = OwnerWriteGuard::findOrFailForOwner(
                        Doc::class,
                        $doc,
                        OwnerContext::CURRENT,
                        (bool) config('docs.owner.include_global', false),
                    );
                } catch (AuthorizationException) {
                    throw new NotFoundHttpException('Document not found.');
                }
            } else {
                $doc = Doc::query()->find($doc);

                if (! $doc instanceof Doc) {
                    throw new NotFoundHttpException('Document not found.');
                }
            }
        }

        return response($renderer->renderHtml($doc, RenderAudience::AdminPreview)->toHtml());
    }
}
