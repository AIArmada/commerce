<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Inbox\EntryPoints\Http\Livewire;

use AIArmada\Communications\Features\Inbox\Actions\ManageInboxEntries;
use AIArmada\Communications\Features\Inbox\Models\NotificationInbox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class InboxIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    /** @return LengthAwarePaginator<int, NotificationInbox> */
    #[Computed]
    public function inboxItems(): LengthAwarePaginator
    {
        $query = $this->recipientQuery()->recent();

        if ($this->filter === 'unread') {
            $query->unread();
        } elseif ($this->filter === 'archived') {
            $query->archived();
        }

        return $query->paginate(15);
    }

    public function markAsRead(string $id): void
    {
        $recipient = Auth::user();

        if ($recipient instanceof Model) {
            app(ManageInboxEntries::class)->markAsRead($recipient, $id);
        }
    }

    public function markAllAsRead(): void
    {
        $recipient = Auth::user();

        if ($recipient instanceof Model) {
            app(ManageInboxEntries::class)->markAllAsRead($recipient);
        }
    }

    public function archive(string $id): void
    {
        $recipient = Auth::user();

        if ($recipient instanceof Model) {
            app(ManageInboxEntries::class)->archive($recipient, $id);
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'unread', 'archived'], true)
            ? $filter
            : 'all';
        $this->resetPage();
    }

    public function render(): View
    {
        return view('communications::livewire.inbox-index');
    }

    /**
     * @return Builder<NotificationInbox>
     */
    private function recipientQuery(): Builder
    {
        $recipient = Auth::user();
        $query = NotificationInbox::query();

        if (! $recipient instanceof Model) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey());
    }
}
