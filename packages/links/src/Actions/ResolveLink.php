<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveLink
{
    use AsAction;

    public function handle(string $slug): ?Link
    {
        $link = $this->findBySlug($slug);

        if (! $link instanceof Link || ! $link->isActive()) {
            return null;
        }

        if (app(LinkGateInterface::class)->blockedReason($link) !== null) {
            return null;
        }

        return $link;
    }

    /**
     * Why a slug does not resolve. Null when the slug is missing or resolves fine.
     *
     * Core reasons are 'deactivated', 'expired' and 'limit_reached'; anything
     * else comes from the bound link gate.
     */
    public function blockedReason(string $slug): ?string
    {
        $link = $this->findBySlug($slug);

        if (! $link instanceof Link) {
            return null;
        }

        if ($link->deactivated_at !== null) {
            return 'deactivated';
        }

        if ($link->isExpired()) {
            return 'expired';
        }

        if ($link->hasReachedClickLimit()) {
            return 'limit_reached';
        }

        return app(LinkGateInterface::class)->blockedReason($link);
    }

    private function findBySlug(string $slug): ?Link
    {
        $slug = mb_trim($slug);

        if ($slug === '') {
            return null;
        }

        return Link::query()->withoutOwnerScope()->where('slug', $slug)->first();
    }
}
