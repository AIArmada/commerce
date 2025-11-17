<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class ConditionSelector implements Arrayable, JsonSerializable
{
    /** @var list<ConditionFilter> */
    private array $filters = [];

    /**
     * @param  iterable<ConditionFilter>  $filters
     */
    public function __construct(
        iterable $filters = [],
        public readonly ?ConditionGrouping $grouping = null
    ) {
        foreach ($filters as $filter) {
            $this->addFilter($filter);
        }
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $filters = array_map(
            fn (array $filter) => ConditionFilter::fromArray($filter),
            $data['filters'] ?? []
        );

        $grouping = isset($data['grouping'])
            ? ConditionGrouping::fromArray($data['grouping'])
            : null;

        return new self($filters, $grouping);
    }

    public function addFilter(ConditionFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @return list<ConditionFilter>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    public function isEmpty(): bool
    {
        return $this->filters === [] && $this->grouping === null;
    }

    public function toArray(): array
    {
        return [
            'filters' => array_map(
                fn (ConditionFilter $filter) => $filter->toArray(),
                $this->filters
            ),
            'grouping' => $this->grouping?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toDslFilters(): ?string
    {
        if ($this->filters === []) {
            return null;
        }

        return implode(';', array_map(
            fn (ConditionFilter $filter) => $filter->toDslToken(),
            $this->filters
        ));
    }
}
