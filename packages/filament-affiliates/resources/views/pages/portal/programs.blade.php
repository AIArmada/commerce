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
                <x-slot name="heading">{{ __('Program Participation') }}</x-slot>

                <p class="fia-portal-helper">
                    {{ trans_choice(':count creative asset available across your joined programs.|:count creative assets available across your joined programs.', $creativeCount, ['count' => $creativeCount]) }}
                </p>
            </x-filament::section>

            @forelse ($programs as $program)
                <x-filament::section>
                    <x-slot name="heading">{{ $program['name'] }}</x-slot>

                    <div class="fia-portal-field-grid">
                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Status') }}</label>
                            <p>{{ $program['status'] }}</p>
                        </div>

                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Approval') }}</label>
                            <p>{{ $program['requires_approval'] ? __('Required') : __('Not required') }}</p>
                        </div>

                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Creative Assets') }}</label>
                            <p>{{ $program['creative_count'] }}</p>
                        </div>

                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Membership') }}</label>
                            <p>
                                @if ($program['is_joined'])
                                    {{ __('Joined') }}
                                @elseif ($program['is_pending'])
                                    {{ __('Pending') }}
                                @else
                                    {{ __('Not joined') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($program['description'])
                        <p class="fia-portal-helper">{{ $program['description'] }}</p>
                    @endif

                    <div class="fia-portal-field-grid">
                        @if ($program['can_join'])
                            <x-filament::button
                                color="primary"
                                wire:click="joinProgram('{{ $program['id'] }}')"
                            >
                                {{ $program['join_label'] }}
                            </x-filament::button>
                        @elseif ($program['is_pending'])
                            <x-filament::button disabled>
                                {{ __('Request Pending') }}
                            </x-filament::button>
                        @elseif ($program['is_joined'])
                            <x-filament::button
                                color="danger"
                                wire:click="leaveProgram('{{ $program['id'] }}')"
                            >
                                {{ $program['leave_label'] }}
                            </x-filament::button>
                        @endif
                    </div>

                    @if (! empty($program['creatives']))
                        <div class="fia-portal-field-grid">
                            @foreach ($program['creatives'] as $creative)
                                <div class="fia-portal-field">
                                    <label class="fia-portal-label">{{ $creative['name'] }}</label>
                                    <p class="fia-portal-helper">{{ __('Type: :type', ['type' => $creative['type']]) }}</p>
                                    <p><a href="{{ $creative['asset_url'] }}" target="_blank" rel="noopener">{{ __('View asset') }}</a></p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @empty
                <x-filament::section>
                    <div class="fia-portal-empty">
                        <x-heroicon-o-rectangle-group class="fia-portal-empty-icon" />
                        <h3 class="fia-portal-empty-title">{{ __('No Active Program Memberships') }}</h3>
                        <p class="fia-portal-empty-copy">{{ __('Join a program to access creative assets.') }}</p>
                    </div>
                </x-filament::section>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
