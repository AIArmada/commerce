<x-filament-panels::page>
    @if (! $hasAffiliate)
        <x-filament::section>
            <div class="fia-portal-empty">
                <x-heroicon-o-user-plus class="fia-portal-empty-icon" />
                <h3 class="fia-portal-empty-title">{{ __('No Affiliate Account') }}</h3>
                <p class="fia-portal-empty-copy">{{ __('You do not have an affiliate account yet.') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="fia-portal-stack">
            <x-filament::section>
                <x-slot name="heading">{{ __('Create Support Ticket') }}</x-slot>

                <form wire:submit.prevent="createTicket" class="fia-portal-field-grid">
                    <div class="fia-portal-field">
                        <label for="subject" class="fia-portal-label">{{ __('Subject') }}</label>

                        <x-filament::input.wrapper>
                            <x-filament::input id="subject" wire:model.defer="subject" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="fia-portal-field">
                        <label for="category" class="fia-portal-label">{{ __('Category') }}</label>

                        <x-filament::input.wrapper>
                            <x-filament::input.select id="category" wire:model.defer="category">
                                @foreach ($ticketCategories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="fia-portal-field">
                        <label for="priority" class="fia-portal-label">{{ __('Priority') }}</label>

                        <x-filament::input.wrapper>
                            <x-filament::input.select id="priority" wire:model.defer="priority">
                                @foreach ($ticketPriorities as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="fia-portal-field fia-portal-field--full">
                        <label for="message" class="fia-portal-label">{{ __('Message') }}</label>

                        <x-filament::input.wrapper>
                            <textarea
                                id="message"
                                wire:model.defer="message"
                                rows="5"
                                class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            ></textarea>
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <x-filament::button type="submit">
                            {{ __('Submit Ticket') }}
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Your Support Tickets') }}</x-slot>

                @forelse ($tickets as $ticket)
                    <div class="fia-portal-stack fia-portal-stack--compact">
                        <x-filament::section>
                            <div class="fia-portal-field-grid">
                                <div class="fia-portal-field">
                                    <label class="fia-portal-label">{{ __('Subject') }}</label>
                                    <p>{{ $ticket['subject'] }}</p>
                                </div>

                                <div class="fia-portal-field">
                                    <label class="fia-portal-label">{{ __('Category') }}</label>
                                    <p>{{ $ticketCategories[$ticket['category']] ?? $ticket['category'] }}</p>
                                </div>

                                <div class="fia-portal-field">
                                    <label class="fia-portal-label">{{ __('Priority') }}</label>
                                    <p>{{ $ticketPriorities[$ticket['priority']] ?? $ticket['priority'] }}</p>
                                </div>

                                <div class="fia-portal-field">
                                    <label class="fia-portal-label">{{ __('Status') }}</label>
                                    <p>{{ $ticket['status'] }}</p>
                                </div>
                            </div>

                            <div class="fia-portal-stack fia-portal-stack--compact">
                                @foreach ($ticket['messages'] as $ticketMessage)
                                    <div class="fia-portal-field">
                                        <label class="fia-portal-label">
                                            {{ $ticketMessage['is_staff_reply'] ? __('Support') : __('You') }}
                                        </label>
                                        <p>{{ $ticketMessage['message'] }}</p>
                                        @if ($ticketMessage['created_at'])
                                            <p class="fia-portal-helper">{{ $ticketMessage['created_at'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <form wire:submit.prevent="replyToTicket(@js($ticket['id']))" class="fia-portal-field-grid">
                                <div class="fia-portal-field fia-portal-field--full">
                                    <label for="reply-{{ $ticket['id'] }}" class="fia-portal-label">{{ __('Add a Reply') }}</label>

                                    <x-filament::input.wrapper>
                                        <textarea
                                            id="reply-{{ $ticket['id'] }}"
                                            wire:model.defer="replyMessages.{{ $ticket['id'] }}"
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        ></textarea>
                                    </x-filament::input.wrapper>
                                </div>

                                <div>
                                    <x-filament::button type="submit" color="primary">
                                        {{ __('Send Reply') }}
                                    </x-filament::button>
                                </div>
                            </form>
                        </x-filament::section>
                    </div>
                @empty
                    <div class="fia-portal-empty">
                        <x-heroicon-o-chat-bubble-left-right class="fia-portal-empty-icon" />
                        <h3 class="fia-portal-empty-title">{{ __('No Support Tickets Yet') }}</h3>
                        <p class="fia-portal-empty-copy">{{ __('Create a ticket to contact support and track replies here.') }}</p>
                    </div>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Compliance Documents') }}</x-slot>

                @forelse ($taxDocuments as $document)
                    <div class="fia-portal-field-grid">
                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Document') }}</label>
                            <p>{{ $document['document_type'] }}</p>
                        </div>

                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Tax Year') }}</label>
                            <p>{{ $document['tax_year'] }}</p>
                        </div>

                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Status') }}</label>
                            <p>{{ $documentStatuses[$document['status']] ?? $document['status'] }}</p>
                        </div>

                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Total') }}</label>
                            <p>{{ $document['currency'] }} {{ number_format($document['total_amount_minor'] / 100, 2) }}</p>
                        </div>
                    </div>

                    @if ($document['notes'])
                        <p class="fia-portal-helper">{{ $document['notes'] }}</p>
                    @endif
                @empty
                    <div class="fia-portal-empty">
                        <x-heroicon-o-document-text class="fia-portal-empty-icon" />
                        <h3 class="fia-portal-empty-title">{{ __('No Compliance Documents Yet') }}</h3>
                        <p class="fia-portal-empty-copy">{{ __('Tax documents and compliance records will appear here when available.') }}</p>
                    </div>
                @endforelse
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>