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
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Profile & Payout Setup') }}
            </x-slot>

            <form wire:submit.prevent="saveProfile" class="fia-portal-field-grid">
                <div class="fia-portal-field">
                    <label for="name" class="fia-portal-label">{{ __('Affiliate Name') }}</label>

                    <x-filament::input.wrapper>
                        <x-filament::input id="name" wire:model.defer="name" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fia-portal-field">
                    <label for="contact_email" class="fia-portal-label">{{ __('Contact Email') }}</label>

                    <x-filament::input.wrapper>
                        <x-filament::input id="contact_email" type="email" wire:model.defer="contactEmail" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fia-portal-field">
                    <label for="website_url" class="fia-portal-label">{{ __('Website URL') }}</label>

                    <x-filament::input.wrapper>
                        <x-filament::input id="website_url" type="url" wire:model.defer="websiteUrl" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fia-portal-field">
                    <label for="payout_method_type" class="fia-portal-label">{{ __('Default Payout Method') }}</label>

                    <x-filament::input.wrapper>
                        <x-filament::input.select id="payout_method_type" wire:model.defer="payoutMethodType">
                            <option value="">{{ __('Select a method') }}</option>
                            @foreach ($payoutMethodOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="fia-portal-field">
                    <label for="payout_method_label" class="fia-portal-label">{{ __('Payout Label') }}</label>

                    <x-filament::input.wrapper>
                        <x-filament::input id="payout_method_label" wire:model.defer="payoutMethodLabel" placeholder="{{ __('e.g. Main PayPal or Bank Account') }}" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fia-portal-field">
                    <label for="payout_method_account_ref" class="fia-portal-label">{{ __('Account Reference') }}</label>

                    <x-filament::input.wrapper>
                        <x-filament::input id="payout_method_account_ref" wire:model.defer="payoutMethodAccountRef" placeholder="{{ __('e.g. email or last 4 digits') }}" />
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <x-filament::button type="submit">
                        {{ __('Save Profile') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
