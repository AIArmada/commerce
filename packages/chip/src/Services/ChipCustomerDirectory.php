<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class ChipCustomerDirectory implements ChipCustomerDirectoryInterface
{
    public function __construct(
        private readonly ChipCollectService $chip,
    ) {}

    public function findForSubject(Model $subject): ?ChipCustomerLink
    {
        /** @var ChipCustomerLink|null $link */
        $link = ChipCustomerLink::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->first();

        return $link;
    }

    public function findByChipCustomerId(string $chipCustomerId, ?Model $owner = null): ?ChipCustomerLink
    {
        $query = ChipCustomerLink::query();

        if ($owner !== null) {
            $query
                ->withoutOwnerScope()
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey());
        }

        /** @var ChipCustomerLink|null $link */
        $link = $query->where('chip_customer_id', $chipCustomerId)->first();

        return $link;
    }

    public function getChipCustomerId(Model $subject): ?string
    {
        return $this->findForSubject($subject)?->chip_customer_id;
    }

    public function hasChipCustomerId(Model $subject): bool
    {
        return $this->getChipCustomerId($subject) !== null;
    }

    public function link(Model $subject, string $chipCustomerId, array $metadata = []): ChipCustomerLink
    {
        $link = $this->findForSubject($subject);

        if ($link === null) {
            if ($this->findAnyForSubject($subject) !== null) {
                throw new AuthorizationException('CHIP customer link is not accessible for the current owner context.');
            }

            $link = new ChipCustomerLink;
        }

        $link->subject()->associate($subject);
        $link->chip_customer_id = $chipCustomerId;
        $link->metadata = $metadata;

        $this->applyOwner($link, $subject);

        $link->save();

        return $link->refresh();
    }

    public function ensureForSubject(Model $subject, CustomerInterface $customer): ChipCustomerLink
    {
        $link = $this->findForSubject($subject);

        if ($link !== null) {
            return $link;
        }

        if ($this->findAnyForSubject($subject) !== null) {
            throw new AuthorizationException('CHIP customer link is not accessible for the current owner context.');
        }

        $client = $this->chip->createClient($this->toClientPayload($customer));

        return $this->link($subject, $client->id, $customer->getCustomerMetadata());
    }

    public function syncForSubject(Model $subject, CustomerInterface $customer): ChipCustomerLink
    {
        $link = $this->ensureForSubject($subject, $customer);

        $this->chip->updateClient($link->chip_customer_id, $this->toClientPayload($customer));

        $link->metadata = $customer->getCustomerMetadata();
        $link->save();

        return $link->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function toClientPayload(CustomerInterface $customer): array
    {
        return array_filter([
            'email' => $customer->getCustomerEmail(),
            'full_name' => $customer->getCustomerName(),
            'phone' => $customer->getCustomerPhone(),
            'country' => $customer->getCustomerCountry(),
            'street_address' => $customer->getBillingStreetAddress(),
            'city' => $customer->getBillingCity(),
            'state' => $customer->getBillingState(),
            'zip_code' => $customer->getBillingPostalCode(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function applyOwner(ChipCustomerLink $link, Model $subject): void
    {
        $subjectOwnerType = $subject->getAttribute('owner_type');
        $subjectOwnerId = $subject->getAttribute('owner_id');

        if (is_string($subjectOwnerType) && $subjectOwnerType !== '' && is_scalar($subjectOwnerId)) {
            $link->owner_type = $subjectOwnerType;
            $link->owner_id = (string) $subjectOwnerId;

            return;
        }

        if ($link->hasOwner()) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner !== null) {
            $link->assignOwner($owner);
        }
    }

    private function findAnyForSubject(Model $subject): ?ChipCustomerLink
    {
        /** @var ChipCustomerLink|null $link */
        $link = ChipCustomerLink::query()
            ->withoutOwnerScope()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->first();

        return $link;
    }
}
