<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentSharing;
use AIArmada\Docs\Data\IssuedShareLink;
use AIArmada\Docs\Data\ShareLinkAction;
use AIArmada\Docs\Data\ShareLinkData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Delivery\Models\DocShareLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class ManageDocShareLinks implements DocumentSharing
{
    public function issue(string $docId, ShareLinkData $data): IssuedShareLink
    {
        $actions = $data->allowedActionValues();
        $plainToken = Str::random(48);

        return (new Doc)->getConnection()->transaction(function () use ($docId, $data, $actions, $plainToken): IssuedShareLink {
            $doc = $this->lockDocument($docId);
            $owner = OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id);

            return OwnerContext::withOwner($owner, function () use ($doc, $data, $actions, $plainToken): IssuedShareLink {
                $link = $doc->shareLinks()->create([
                    'token_hash' => hash('sha256', $plainToken),
                    'allowed_actions' => $actions,
                    'expires_at' => $data->expiresAt ?? CarbonImmutable::now()->addDays((int) config('docs.sharing.default_expiry_days', 30)),
                ]);

                return new IssuedShareLink((string) $link->getKey(), $plainToken, $actions);
            });
        });
    }

    public function revokeAll(string $docId): void
    {
        (new Doc)->getConnection()->transaction(function () use ($docId): void {
            $doc = $this->lockDocument($docId);
            OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc): void {
                $doc->shareLinks()->forOwner()->whereNull('revoked_at')->update(['revoked_at' => CarbonImmutable::now()]);
            });
        });
    }

    public function resolve(string $plainToken, ShareLinkAction $action): DocShareLink
    {
        return (new DocShareLink)->getConnection()->transaction(function () use ($plainToken, $action): DocShareLink {
            $link = DocShareLink::query()
                ->withoutOwnerScope()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if (! $link instanceof DocShareLink || $link->isRevoked() || $link->isExpired() || ! $link->allows($action)) {
                throw new NotFoundHttpException('Document link not found.');
            }

            try {
                $owner = OwnerContext::fromTypeAndIdOrFail($link->owner_type, $link->owner_id);
            } catch (Throwable) {
                throw new NotFoundHttpException('Document link not found.');
            }

            return OwnerContext::withOwner($owner, function () use ($link): DocShareLink {
                $doc = Doc::query()->forOwner()->find($link->doc_id);

                if (! $doc instanceof Doc || $doc->owner_type !== $link->owner_type || (string) $doc->owner_id !== (string) $link->owner_id) {
                    throw new NotFoundHttpException('Document link not found.');
                }

                $link->setRelation('doc', $doc);
                $link->forceFill([
                    'access_count' => (int) $link->access_count + 1,
                    'last_accessed_at' => CarbonImmutable::now(),
                ])->save();

                return $link;
            });
        });
    }

    private function lockDocument(string $docId): Doc
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId);
        }

        return Doc::query()->forOwner()->lockForUpdate()->findOrFail($docId);
    }
}
