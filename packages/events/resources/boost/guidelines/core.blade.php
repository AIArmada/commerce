## Events (`aiarmada/events`)

Modular Dune event platform for Laravel: 9 owned features (`Catalog`, `Venues`, `Taxonomy`, `Registrations`, `Attendance`, `Commerce`, `Changes`, `Governance`, `Search`) behind pure scalar-ID contracts.

### Key Conventions

- Configure package options in `config/events.php` rather than calling `env()` directly.
- Resolve configured models through `AIArmada\Events\Platform\ModelResolver` (`eventClass()`, `registrationClass()`, …) — never hard-code a concrete model class when the app may override `events.models.*`.
- Depend on public contracts under `AIArmada\Events\Contracts\` and feature `Api` classes (`Features\...\Api\`) plus immutable DTOs under `AIArmada\Events\Data\`. These expose only scalar IDs, arrays, and `final readonly` DTOs — no Eloquent models, no `spatie/laravel-data`.
- Single-writer rule: every durable `table:*` resource is owned by exactly one feature listed in `src/Features/<Feature>/feature.json`. Mutate it only through that feature's `Actions`/`Persistence` classes; verify with `composer arch:check`.
- No legacy surface: there is no `Events` facade and no legacy top-level namespaces under `AIArmada\Events` — update call sites instead of adding shims.
- External integrations live behind `AIArmada\Events\Contracts\` and resolve through `AIArmada\Events\Drivers\`; bind a custom driver or contract implementation in a service provider.
- Scope slots (`events.cascade-delete`, `events.clone-scope`) resolve through `AIArmada\Events\Platform\ExtensionRegistry`; declare feature extensions in `feature.json` under `extensions`.
- Ownership: event roots use `commerce-support` owner scoping; event children inherit the event boundary via `Platform\ScopesByEventOwner` with `Platform\EventWriteGuard::findOrFail()` on write paths.

@verbatim
<code-snippet name="Resolve the configured event model and check in an attendee" lang="php">
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Platform\ModelResolver;

$eventClass = ModelResolver::eventClass();
$event = $eventClass::query()->findOrFail($eventId);

$attendance = app(EventCheckInService::class)->checkIn([
    'event_id' => $eventId,
    'event_occurrence_id' => $occurrenceId,
    'event_registration_id' => $registrationId,
    'check_in_source' => 'qr',
]);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Free registration through the scalar-ID action" lang="php">
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;

$registrationIds = app(RegisterForFreeAction::class)->execute(
    'occurrence',
    $occurrenceId,
    participants: [['name' => 'Alice', 'email' => 'alice@example.com']],
);
</code-snippet>
@endverbatim
