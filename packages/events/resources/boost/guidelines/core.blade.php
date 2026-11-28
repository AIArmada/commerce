## Events (`aiarmada/events`)

Modular Dune event platform for Laravel: 9 owned features (`Catalog`, `Venues`, `Taxonomy`, `Registrations`, `Attendance`, `Commerce`, `Changes`, `Governance`, `Search`) behind pure scalar-ID contracts.

### Key Conventions

- Configure package options in `config/events.php` rather than calling `env()` directly.
- Resolve configured models through `AIArmada\Events\Platform\ModelResolver` (`eventClass()`, `registrationClass()`, …). The event binding must extend the Catalog Event model. Occurrence and session are final domain models and cannot be substituted; invalid bindings fail closed.
- Depend on public contracts under `AIArmada\Events\Contracts\` and feature `Api` classes (`Features\...\Api\`) plus immutable DTOs under `AIArmada\Events\Data\`. These expose only scalar IDs, arrays, and `final readonly` DTOs — no Eloquent models, no `spatie/laravel-data`.
- Single-writer rule: every durable `table:*` resource is owned by exactly one feature listed in `src/Features/<Feature>/feature.json`. Mutate it only through that feature's `Actions`/`Persistence` classes; verify with `composer arch:check`.
- No legacy surface: there is no `Events` facade and no legacy top-level namespaces under `AIArmada\Events` — update call sites instead of adding shims.
- External integrations live behind `AIArmada\Events\Contracts\` and resolve through `AIArmada\Events\Drivers\`; bind a custom driver or contract implementation in a service provider.
- Scope slots (`events.cascade-delete`, `events.clone-scope`) resolve through `AIArmada\Events\Platform\ExtensionRegistry`; declare feature extensions in `feature.json` under `extensions`.
- Ownership: event roots use `commerce-support` owner scoping; event children inherit the event boundary via `Platform\ScopesByEventOwner` with `Platform\EventWriteGuard::findOrFail()` on write paths. `CatalogApi::describeScope()` and `RegistrationsApi::lock()` also honor the active owner context.
- Record privacy: public events do not expose registration or submission records; `EventRegistrationPolicy` and `EventSubmissionPolicy` allow managers and the polymorphic record owner only. `requestRefund` and `manageAdmissions` are explicit deny-by-default abilities.
- Pass issuance: `IssueEventRegistrationPassesAction` locks the scope chain and registration row, aggregates item quantities per ticket type, and only issues for `events.lifecycle.registration.pass_issuance_statuses`.
- Registration modes (`pricing_mode`, `registration_mode`, `issue_passes_for_free`) inherit session → occurrence → event; `issue_passes_for_free` is nullable at every level, and explicit `false` must be preserved.

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
