<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Concerns\CanFollow;
use AIArmada\Engagement\Features\Interactions\Concerns\HasFollowers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

it('keeps actor and subject trait calls equivalent to manager calls', function (): void {
    $actor = new class extends Model implements CanInteract
    {
        use CanFollow;

        public $incrementing = false;

        protected $keyType = 'string';

        public function getTable(): string
        {
            return 'users';
        }

        public function interactionDisplayName(): string
        {
            return 'Trait actor';
        }

        public function interactionNotificationRoute(?string $channel = null): mixed
        {
            return null;
        }
    };
    $subject = new class extends Model implements Followable
    {
        use HasFollowers;

        public $incrementing = false;

        protected $keyType = 'string';

        public function getTable(): string
        {
            return 'users';
        }

        public function followableName(): string
        {
            return 'Trait subject';
        }

        public function followableUrl(): ?string
        {
            return null;
        }

        public function followableImage(): ?string
        {
            return null;
        }

        public function defaultFollowNotificationLevel(): ?string
        {
            return 'all';
        }
    };

    $actor->setAttribute('id', 'trait-actor');
    $subject->setAttribute('id', 'trait-subject');

    foreach ([$actor, $subject] as $record) {
        DB::table('users')->insert([
            'id' => $record->getKey(),
            'name' => 'Trait record',
            'email' => 'trait-' . $record->getKey() . '-' . uniqid() . '@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $record->exists = true;
    }

    $manager = app(ManageInteractionsAction::class);
    $viaTrait = $actor->follow($subject);
    $viaManager = $manager->follow($actor, $subject);

    expect($viaManager->getKey())->toBe($viaTrait->getKey())
        ->and($subject->followersCount())->toBe(1);
});
