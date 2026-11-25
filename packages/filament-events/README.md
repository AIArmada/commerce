# AIArmada Filament Events

Filament v5 admin UI for events, occurrences, sessions, venues, registrations, attendance, check-in, and moderation workflows. Every mutation delegates to `aiarmada/events` scalar-ID contracts (`EventLifecycleWorkflow`, `EventCloneService`, `EventModerationWorkflow`, `EventCheckInService`, `EventTemplateService`); ticket type and pass administration lives in `aiarmada/filament-ticketing`.

See [docs/](docs/) for installation, configuration, and usage.

## Event description safety

Public preview descriptions are rendered as escaped plain text. Stored HTML is never executed.
