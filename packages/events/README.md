# AIArmada Events

Event definitions, scheduling, venues, registrations, ticketing bridges, check-in, attendance, governance, and change workflows.

The package follows a Modular Dune layout: nine owned features (`Catalog`, `Venues`, `Taxonomy`, `Registrations`, `Attendance`, `Commerce`, `Changes`, `Governance`, `Search`), pure scalar-ID `Contracts`/`Data`, external `Drivers`, and a shared `Platform` core. There is no `Events` facade and no legacy `AIArmada\Events\Models`-style namespace — application code resolves configured models through `AIArmada\Events\Platform\ModelResolver` and calls feature `Api` contracts or `Contracts` interfaces.

Event-domain organizer profiles are exposed as `EventOrganizer`. This package
does not own tenant organizations; install `aiarmada/organizations` for the
application tenancy aggregate.

See [docs/](docs/) for installation, configuration, usage, and the breaking-name list.
