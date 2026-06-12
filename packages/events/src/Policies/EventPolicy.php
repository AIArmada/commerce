<?php
declare(strict_types=1);
namespace AIArmada\Events\Policies;

use AIArmada\Events\Models\Event;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EventPolicy
{
    use HandlesAuthorization;

    public function viewAny(mixed $user): bool { return true; }
    public function view(mixed $user, Event $event): bool { return true; }
    public function create(mixed $user): bool { return true; }
    public function update(mixed $user, Event $event): bool { return true; }
    public function delete(mixed $user, Event $event): bool { return false; }
    public function publish(mixed $user, Event $event): bool { return true; }
    public function archive(mixed $user, Event $event): bool { return true; }
    public function cancel(mixed $user, Event $event): bool { return true; }
}
