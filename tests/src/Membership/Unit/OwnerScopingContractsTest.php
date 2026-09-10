<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

abstract class AbstractMembershipOwnerScopingContractTest extends MembershipTestCase
{
    use OwnerScopingContractTests;

    /**
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    protected function createOwner(): Model
    {
        return User::query()->create([
            'name' => 'Membership owner ' . Str::upper(Str::random(8)),
            'email' => Str::uuid() . '@membership-owner.example.com',
            'password' => 'secret',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, fn (): Model => $this->createModel($owner));
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, fn (): Model => $this->createModel(null));
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = $this->createGlobalModel();

        expect(fn () => $model->assignOwner($owner)->save())
            ->toThrow(InvalidArgumentException::class, 'persisted global');
    }

    private function createModel(?Model $owner): Model
    {
        $subject = TestSubject::query()->create([
            'name' => 'Contract subject ' . Str::uuid(),
        ]);
        $actor = $owner ?? $this->createOwner();

        return match ($this->getModelClass()) {
            MembershipApplication::class => MembershipApplication::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'applicant_id' => $actor->getKey(),
                'status' => ApplicationStatus::Pending,
                'justification' => 'Owner contract application.',
            ]),
            MembershipInvitation::class => $this->createInvitation([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'email' => 'contract-' . Str::uuid() . '@example.com',
                'role' => MemberRole::Viewer->spatieRoleName(),
                'invited_by' => $actor->getKey(),
            ]),
            default => throw new LogicException('Unhandled membership owner contract model.'),
        };
    }
}
