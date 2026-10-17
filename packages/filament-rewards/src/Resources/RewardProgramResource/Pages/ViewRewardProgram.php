<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages;

use AIArmada\FilamentRewards\Resources\RewardProgramResource\RewardProgramResource;
use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Actions\PreviewRewardQualificationAction;
use AIArmada\Rewards\Actions\PublishRewardProgramVersionAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardVerificationLevel;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Support\RewardPresetBuilders;
use AIArmada\Rewards\Support\RewardPublishAssembler;
use AIArmada\Rewards\Support\RewardRuleValidator;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Override;

class ViewRewardProgram extends ViewRecord
{
    protected static string $resource = RewardProgramResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->publishVersionAction(),
            $this->transitionStatusAction(),
            $this->previewQualificationAction(),
        ];
    }

    private function programRecord(): RewardProgram
    {
        $record = $this->getRecord();

        abort_unless($record instanceof RewardProgram, 404);

        return $record;
    }

    private function publishVersionAction(): Action
    {
        return Action::make('publish_version')
            ->label('Publish version')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('primary')
            ->visible(fn (): bool => RewardAdminPresenter::staffActor() !== null)
            ->modalHeading('Publish program version')
            ->modalDescription('Immutable rules and terms. Editing an active program creates a new version; existing awards keep their frozen rules.')
            ->schema(fn (): array => $this->publishSchema())
            ->action(function (array $data): void {
                $staff = RewardAdminPresenter::staffActor();
                abort_unless($staff !== null, 403);

                try {
                    $payload = RewardPublishAssembler::assemble($this->programRecord()->mode, $data);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([$this->publishErrorField($exception) => $exception->getMessage()]);
                }

                try {
                    PublishRewardProgramVersionAction::run(
                        $this->programRecord(),
                        CarbonImmutable::parse((string) $data['effective_from']),
                        $payload['rules'],
                        $payload['terms'],
                        filled($data['reason'] ?? null) ? (string) $data['reason'] : null,
                        'user',
                        (string) $staff->getKey(),
                    );
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([$this->publishErrorField($exception) => $exception->getMessage()]);
                }

                Notification::make()->title('Program version published')->success()->send();
            });
    }

    private function publishErrorField(InvalidArgumentException $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'milestone') => 'milestone_rows',
            str_contains($message, 'component') => 'component_rows',
            str_contains($message, 'eligibility') => 'eligibility_outcomes',
            str_contains($message, 'terms') => 'terms_label',
            default => 'effective_from',
        };
    }

    /**
     * Structured publish controls prefilled from the mode preset. No
     * raw JSON: every rule key maps to a typed field, and the assembler
     * builds the validated rules/terms maps on submit.
     *
     * @return list<mixed>
     */
    private function publishSchema(): array
    {
        $mode = $this->programRecord()->mode;
        $preset = RewardPresetBuilders::for($mode);
        $rules = $preset['rules'];
        $terms = $preset['terms'];
        $componentRows = [];
        foreach ($rules['components'] as $component) {
            $amount = $component;
            unset($amount['type']);

            $componentRows[] = [
                'type' => $component['type'],
                'variants' => [[
                    'outcomes' => $rules['eligibility']['outcomes'],
                    'roles' => [RewardBeneficiaryRole::Sharer->value, RewardBeneficiaryRole::Participant->value],
                    'min_verification' => $rules['eligibility']['min_verification'],
                    'first_only' => (bool) ($rules['eligibility']['first_only'] ?? false),
                    ...$amount,
                ]],
            ];
        }

        $schema = [
            DateTimePicker::make('effective_from')
                ->label('Effective from (UTC)')
                ->timezone('UTC')
                ->required()
                ->default(now()->toIso8601String()),
        ];

        $componentOptions = [];
        foreach ($mode->allowedComponents() as $component) {
            $componentOptions[$component->value] = ucfirst($component->value);
        }

        $roleOptions = [];
        foreach (RewardBeneficiaryRole::cases() as $role) {
            $roleOptions[$role->value] = str($role->value)->replace('_', ' ')->headline()->toString();
        }

        $milestoneRewardOptions = [];
        foreach ([
            RewardComponent::Recognition->value => 'Recognition',
            RewardComponent::Points->value => 'Points',
            RewardComponent::Badge->value => 'Badge',
        ] as $type => $label) {
            if (in_array(RewardComponent::from($type), $mode->allowedComponents(), true)) {
                $milestoneRewardOptions[$type] = $label;
            }
        }

        $typeIs = static fn (Get $get, string $type): bool => $get('../../type') === $type;
        $schema[] = Repeater::make('component_rows')
            ->label('Per-activity rewards')
            ->schema([
                Select::make('type')
                    ->label('Reward component')
                    ->options($componentOptions)
                    ->live()
                    ->required(),
                Repeater::make('variants')
                    ->label('Outcome amounts')
                    ->minItems(1)
                    ->schema([
                        Select::make('outcomes')
                            ->label('Outcomes')
                            ->options(RewardAdminPresenter::outcomeOptions())
                            ->multiple()
                            ->required(),
                        Select::make('roles')
                            ->label('Beneficiary roles')
                            ->options($roleOptions)
                            ->multiple()
                            ->required(),
                        Select::make('min_verification')
                            ->label('Minimum verification')
                            ->options(RewardAdminPresenter::verificationOptions())
                            ->required(),
                        Toggle::make('first_only')->label('First eligible outcome only')->default(false),
                        TextInput::make('template')
                            ->label('Recognition template')
                            ->maxLength(64)
                            ->visible(fn (Get $get): bool => $typeIs($get, 'recognition'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'recognition')),
                        TextInput::make('points')
                            ->label('Points')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(RewardRuleValidator::maxPoints())
                            ->required(fn (Get $get): bool => $typeIs($get, 'points'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'points'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'points')),
                        TextInput::make('unit')
                            ->label('Points unit')
                            ->maxLength(16)
                            ->required(fn (Get $get): bool => $typeIs($get, 'points'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'points'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'points')),
                        TextInput::make('badge_key')
                            ->label('Badge key')
                            ->maxLength(64)
                            ->required(fn (Get $get): bool => $typeIs($get, 'badge'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'badge'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'badge')),
                        TextInput::make('minor_units')
                            ->label('Cash amount (minor units)')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => $typeIs($get, 'cash'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'cash'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'cash')),
                        Select::make('currency')
                            ->label('Cash currency')
                            ->options(RewardAdminPresenter::currencyOptions())
                            ->required(fn (Get $get): bool => $typeIs($get, 'cash'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'cash'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'cash')),
                        TextInput::make('benefit_key')
                            ->label('Benefit key')
                            ->maxLength(64)
                            ->required(fn (Get $get): bool => $typeIs($get, 'benefit'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'benefit'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'benefit')),
                        Textarea::make('conditions')
                            ->label('Benefit conditions (one per line)')
                            ->rows(2)
                            ->required(fn (Get $get): bool => $typeIs($get, 'benefit'))
                            ->visible(fn (Get $get): bool => $typeIs($get, 'benefit'))
                            ->dehydrated(fn (Get $get): bool => $typeIs($get, 'benefit')),
                    ])
                    ->defaultItems(1)
                    ->addActionLabel('Add outcome amount'),
            ])
            ->default($componentRows)
            ->minItems(1)
            ->maxItems(5)
            ->addActionLabel('Add reward component');

        $schema[] = Repeater::make('milestone_rows')
            ->label('Star and milestone levels')
            ->visible($milestoneRewardOptions !== [])
            ->schema([
                TextInput::make('key')->label('Milestone key')->required()->maxLength(64),
                TextInput::make('label')->label('Member-facing label')->required()->maxLength(191),
                Select::make('outcomes')->label('Eligible outcomes')->options(RewardAdminPresenter::outcomeOptions())->multiple()->required(),
                Select::make('roles')->label('Beneficiary roles')->options($roleOptions)->multiple()->required(),
                Select::make('min_verification')->label('Minimum verification')->options(RewardAdminPresenter::verificationOptions())->required(),
                Toggle::make('first_only')->label('First eligible outcome only')->default(false),
                TextInput::make('target_count')->label('Threshold')->numeric()->minValue(1)->required(),
                TextInput::make('window_days')->label('Cycle window (days)')->numeric()->minValue(1)->maxValue(365)->required(),
                Select::make('reward_type')
                    ->label('Milestone grant')
                    ->options($milestoneRewardOptions)
                    ->rule(Rule::in(array_keys($milestoneRewardOptions)))
                    ->default(array_key_first($milestoneRewardOptions))
                    ->live()
                    ->required(),
                TextInput::make('template')->label('Recognition template')->maxLength(64)->visible(fn (Get $get): bool => $get('reward_type') === 'recognition')->dehydrated(fn (Get $get): bool => $get('reward_type') === 'recognition'),
                TextInput::make('points')->label('Points grant')->numeric()->minValue(1)->maxValue(RewardRuleValidator::maxPoints())->required(fn (Get $get): bool => $get('reward_type') === 'points')->visible(fn (Get $get): bool => $get('reward_type') === 'points')->dehydrated(fn (Get $get): bool => $get('reward_type') === 'points'),
                TextInput::make('unit')->label('Points unit')->maxLength(16)->required(fn (Get $get): bool => $get('reward_type') === 'points')->visible(fn (Get $get): bool => $get('reward_type') === 'points')->dehydrated(fn (Get $get): bool => $get('reward_type') === 'points'),
                TextInput::make('badge_key')->label('Badge key')->maxLength(64)->required(fn (Get $get): bool => $get('reward_type') === 'badge')->visible(fn (Get $get): bool => $get('reward_type') === 'badge')->dehydrated(fn (Get $get): bool => $get('reward_type') === 'badge'),
            ])
            ->default([])
            ->maxItems(20)
            ->addActionLabel('Add star or milestone');

        $schema[] = Select::make('eligibility_outcomes')
            ->label('Eligible outcomes')
            ->options(RewardAdminPresenter::outcomeOptions())
            ->multiple()
            ->required()
            ->default($rules['eligibility']['outcomes']);
        $schema[] = Textarea::make('eligibility_categories')
            ->label('Category slugs (one per line, empty means all)')
            ->rows(2)
            ->default('');
        $schema[] = Select::make('min_verification')
            ->label('Minimum verification')
            ->options(RewardAdminPresenter::verificationOptions())
            ->required()
            ->default($rules['eligibility']['min_verification']);
        $schema[] = Toggle::make('first_only')->label('First-time outcomes only')->default(false);
        $schema[] = Toggle::make('exclude_self_credit')->label('Exclude self credit')->default(true);
        $schema[] = Toggle::make('require_enrollment')->label('Require enrollment')->default(true);
        $schema[] = Toggle::make('require_claimed_identity')->label('Require claimed identity')->default(true);
        $schema[] = TextInput::make('preclaim_lookback_days')
            ->label('Preclaim lookback (days)')
            ->numeric()
            ->minValue(0)
            ->maxValue(90)
            ->required()
            ->default(30);
        $schema[] = TextInput::make('hold_days')
            ->label('Hold (days)')
            ->numeric()
            ->minValue(0)
            ->maxValue(60)
            ->required()
            ->default(0);

        $presetCaps = $rules['caps'];

        for ($row = 1; $row <= 3; $row++) {
            $schema[] = Select::make('cap_scope_' . $row)
                ->label('Cap ' . $row . ' scope (blank skips)')
                ->options(RewardAdminPresenter::capScopeOptions())
                ->default($presetCaps[$row - 1]['scope'] ?? null);
            $schema[] = TextInput::make('cap_limit_' . $row)
                ->label('Cap ' . $row . ' limit')
                ->numeric()
                ->minValue(1)
                ->default($presetCaps[$row - 1]['limit'] ?? null);
        }

        $schema[] = Toggle::make('ambiguous_device')->label('Hold ambiguous devices')->default(true);
        $schema[] = Select::make('manual_review_outcomes')
            ->label('Manual review outcomes')
            ->options(RewardAdminPresenter::outcomeOptions())
            ->multiple()
            ->default([]);

        $schema[] = TextInput::make('terms_label')
            ->label('Terms label')
            ->maxLength(191)
            ->required()
            ->default($terms['label']);
        $schema[] = TextInput::make('terms_version_label')
            ->label('Terms version label')
            ->maxLength(32)
            ->required()
            ->default('v1');
        $schema[] = Textarea::make('terms_body')
            ->label('Terms body')
            ->rows(4)
            ->required()
            ->default($terms['body']);
        $schema[] = Textarea::make('terms_earn_basis')
            ->label('Material clause: earn basis')
            ->rows(2)
            ->required()
            ->default($terms['material']['earn_basis']);
        $schema[] = Textarea::make('terms_changes')
            ->label('Material clause: changes')
            ->rows(2)
            ->required()
            ->default($terms['material']['changes']);
        $schema[] = Textarea::make('terms_no_ranking')
            ->label('Material clause: no ranking')
            ->rows(2)
            ->required()
            ->default($terms['material']['no_ranking'] ?? 'No public ranking, streak pressure, or claims of spiritual merit.');

        $schema[] = TextInput::make('reason')->maxLength(191);

        return $schema;
    }

    private function transitionStatusAction(): Action
    {
        return Action::make('transition_status')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => RewardAdminPresenter::staffActor() !== null)
            ->modalHeading('Change program status')
            ->modalDescription('Lifecycle: draft → ready → active → paused → closed. Ready/active transitions validate program readiness.')
            ->schema([
                Select::make('to')
                    ->label('New status')
                    ->options(RewardAdminPresenter::programStatusOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                abort_unless(RewardAdminPresenter::staffActor() !== null, 403);

                try {
                    TransitionRewardProgramStatusAction::run($this->programRecord(), (string) $data['to']);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['to' => $exception->getMessage()]);
                }

                Notification::make()->title('Program status updated')->success()->send();
            });
    }

    private function previewQualificationAction(): Action
    {
        return Action::make('preview_qualification')
            ->label('Preview qualification')
            ->icon(Heroicon::OutlinedEye)
            ->color('info')
            ->visible(fn (): bool => RewardAdminPresenter::staffActor() !== null)
            ->modalHeading('Deterministic qualification preview')
            ->modalDescription('Explains why this deterministic fixture qualifies, holds, or rejects under the applicable version. Nothing is persisted.')
            ->schema([
                Select::make('component')
                    ->options(RewardAdminPresenter::componentOptions())
                    ->required(),
                Select::make('outcome')
                    ->options(RewardAdminPresenter::outcomeOptions())
                    ->required(),
                Select::make('verification')
                    ->options(RewardAdminPresenter::verificationOptions())
                    ->default(RewardVerificationLevel::Accepted->value)
                    ->required(),
                Select::make('beneficiary_role')
                    ->label('Beneficiary role')
                    ->options([
                        RewardBeneficiaryRole::Recipient->value => 'Recipient (sharer-bound; attribution required)',
                        RewardBeneficiaryRole::Participant->value => 'Participant (actor-bound)',
                        RewardBeneficiaryRole::Purchaser->value => 'Purchaser (actor-bound)',
                        RewardBeneficiaryRole::Staff->value => 'Staff (actor-bound)',
                        RewardBeneficiaryRole::Sharer->value => 'Sharer (sharer-bound; attribution required)',
                    ])
                    ->default(RewardBeneficiaryRole::Recipient->value)
                    ->required()
                    ->helperText('Actor-bound roles can earn unattributed evidence; sharer-bound roles still require attribution.'),
                Toggle::make('is_first_time')->label('First-time outcome')->default(true),
                Toggle::make('is_attributed')->label('Attributed')->default(true),
                Toggle::make('is_claimed_identity')->label('Claimed identity')->default(true),
                Toggle::make('self_credit')->label('Actor is the sharer (self credit)')->default(false),
            ])
            ->action(function (array $data, PreviewRewardQualificationAction $preview): void {
                $actorId = '11111111-1111-4111-8111-111111111111';
                $sharerId = ($data['self_credit'] ?? false) ? $actorId : '22222222-2222-4222-8222-222222222222';

                try {
                    $result = $preview->handle(
                        $this->programRecord(),
                        RewardComponent::from((string) $data['component']),
                        [
                            'source_business_key' => 'fixture:preview:' . (string) $data['outcome'],
                            'outcome' => (string) $data['outcome'],
                            'category' => null,
                            'occurred_at' => CarbonImmutable::now('UTC')->subDay()->toIso8601String(),
                            'verification' => (string) $data['verification'],
                            'is_first_time' => (bool) ($data['is_first_time'] ?? false),
                            'actor_id' => $actorId,
                            'sharer_id' => $sharerId,
                            'is_claimed_identity' => (bool) ($data['is_claimed_identity'] ?? false),
                            'is_attributed' => (bool) ($data['is_attributed'] ?? false),
                            'beneficiary_role' => (string) ($data['beneficiary_role'] ?? RewardBeneficiaryRole::Recipient->value),
                        ],
                        CarbonImmutable::now('UTC'),
                    );
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['component' => $exception->getMessage()]);
                }

                $lines = ['Decision: ' . $result->decision->value];

                foreach ($result->reasons as $index => $reason) {
                    $lines[] = '- ' . $reason->value . ': ' . ($result->explanations[$index] ?? '');
                }

                Notification::make()
                    ->title('Preview: ' . $result->decision->value)
                    ->body(implode("\n", $lines))
                    ->info()
                    ->persistent()
                    ->send();
            });
    }
}
