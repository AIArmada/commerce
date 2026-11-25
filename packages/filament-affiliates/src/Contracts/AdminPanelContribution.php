<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Contracts;

interface AdminPanelContribution
{
    public function priority(): int;

    /** @param array<string, bool> $features
     * @return list<class-string>
     */
    public function resources(array $features): array;

    /** @param array<string, bool> $features
     * @return list<class-string>
     */
    public function pages(array $features): array;

    /** @param array<string, bool> $features
     * @return list<class-string>
     */
    public function widgets(array $features): array;
}
