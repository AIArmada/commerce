# 09 — Laravel Migration Blueprints

This document gives concrete Laravel migration style examples. These are representative snippets, not every full migration.

## Global style

Use:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('example_table', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('example_table');
    }
};
```

Do not use:

```php
$table->foreignId('event_id')->constrained()->cascadeOnDelete();
$table->softDeletes();
```

Use UUID reference columns without FK constraints:

```php
$table->uuid('event_id')->index();
$table->uuid('event_occurrence_id')->nullable()->index();
$table->uuid('event_session_id')->nullable()->index();
```

Use polymorphic pairs explicitly:

```php
$table->string('owner_type')->nullable()->index();
$table->uuid('owner_id')->nullable()->index();
$table->index(['owner_type', 'owner_id']);
```

## `events` migration example

```php
Schema::create('events', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->string('owner_type')->nullable()->index();
    $table->uuid('owner_id')->nullable()->index();
    $table->index(['owner_type', 'owner_id']);

    $table->string('created_by_type')->nullable()->index();
    $table->uuid('created_by_id')->nullable()->index();
    $table->index(['created_by_type', 'created_by_id']);

    $table->string('title');
    $table->string('slug')->nullable()->index();
    $table->text('summary')->nullable();
    $table->text('description')->nullable();

    $table->string('type')->nullable()->index();
    $table->string('status')->index();
    $table->string('visibility')->index();
    $table->string('delivery_mode')->nullable()->index();
    $table->string('timezone')->nullable();

    $table->uuid('default_venue_id')->nullable()->index();

    $table->timestampTz('published_at')->nullable()->index();
    $table->timestampTz('cancelled_at')->nullable()->index();
    $table->timestampTz('postponed_at')->nullable()->index();
    $table->timestampTz('archived_at')->nullable()->index();
    $table->timestampTz('completed_at')->nullable()->index();

    $table->text('status_reason')->nullable();
    $table->text('status_message')->nullable();

    $table->jsonb('metadata')->nullable();
    $table->timestampsTz();
});
```

## `event_occurrences` migration example

```php
Schema::create('event_occurrences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('event_id')->index();

    $table->string('title')->nullable();
    $table->string('slug')->nullable()->index();

    $table->timestampTz('starts_at')->nullable()->index();
    $table->timestampTz('ends_at')->nullable()->index();
    $table->string('timezone')->nullable();

    $table->string('status')->index();
    $table->string('visibility')->index();
    $table->string('delivery_mode')->nullable()->index();

    $table->integer('capacity')->nullable();

    $table->timestampTz('published_at')->nullable()->index();
    $table->timestampTz('delayed_at')->nullable()->index();
    $table->timestampTz('postponed_at')->nullable()->index();
    $table->timestampTz('rescheduled_at')->nullable()->index();
    $table->timestampTz('cancelled_at')->nullable()->index();
    $table->timestampTz('completed_at')->nullable()->index();
    $table->timestampTz('archived_at')->nullable()->index();

    $table->uuid('rescheduled_from_occurrence_id')->nullable()->index();
    $table->uuid('rescheduled_to_occurrence_id')->nullable()->index();

    $table->text('status_reason')->nullable();
    $table->text('status_message')->nullable();

    $table->jsonb('metadata')->nullable();
    $table->timestampsTz();
});
```

## Scoped table pattern example

Use for tables like `event_locations`, `event_involvements`, `event_materials`, etc.

```php
$table->uuid('event_id')->index();
$table->uuid('event_occurrence_id')->nullable()->index();
$table->uuid('event_session_id')->nullable()->index();
$table->index(['event_id', 'event_occurrence_id', 'event_session_id']);
```

## `event_locations` migration example

```php
Schema::create('event_locations', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->uuid('event_id')->index();
    $table->uuid('event_occurrence_id')->nullable()->index();
    $table->uuid('event_session_id')->nullable()->index();
    $table->index(['event_id', 'event_occurrence_id', 'event_session_id']);

    $table->string('location_role')->index();

    $table->string('locationable_type')->nullable()->index();
    $table->uuid('locationable_id')->nullable()->index();
    $table->index(['locationable_type', 'locationable_id']);

    $table->uuid('venue_id')->nullable()->index();
    $table->uuid('venue_space_id')->nullable()->index();
    $table->uuid('venue_space_type_id')->nullable()->index();

    $table->string('label')->nullable();

    $table->string('line1')->nullable();
    $table->string('line2')->nullable();
    $table->string('city')->nullable()->index();
    $table->string('district')->nullable()->index();
    $table->string('state')->nullable()->index();
    $table->string('postcode', 20)->nullable()->index();
    $table->string('country', 2)->nullable()->index();

    $table->string('level')->nullable();
    $table->string('unit_no')->nullable();

    $table->decimal('latitude', 10, 7)->nullable()->index();
    $table->decimal('longitude', 10, 7)->nullable()->index();
    // Optional if PostGIS is enabled:
    // $table->geography('geo_point', subtype: 'point', srid: 4326)->nullable();

    $table->string('google_place_id')->nullable()->index();
    $table->text('google_maps_url')->nullable();
    $table->text('waze_url')->nullable();
    $table->text('map_url')->nullable();
    $table->text('directions')->nullable();

    $table->jsonb('address_snapshot')->nullable();

    $table->timestampTz('geocoded_at')->nullable();
    $table->string('geocoding_source')->nullable();

    $table->string('visibility')->index();
    $table->string('status')->index();
    $table->integer('sort_order')->default(0)->index();

    $table->jsonb('metadata')->nullable();
    $table->timestampsTz();
});
```

## `event_involvements` migration example

```php
Schema::create('event_involvements', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->uuid('event_id')->index();
    $table->uuid('event_occurrence_id')->nullable()->index();
    $table->uuid('event_session_id')->nullable()->index();
    $table->index(['event_id', 'event_occurrence_id', 'event_session_id']);

    $table->string('involveable_type')->index();
    $table->uuid('involveable_id')->index();
    $table->index(['involveable_type', 'involveable_id']);

    $table->uuid('event_role_id')->nullable()->index();
    $table->string('role_code')->nullable()->index();

    $table->string('status')->index();
    $table->string('visibility')->index();

    $table->string('prominence')->nullable()->index();
    $table->boolean('is_featured')->default(false)->index();
    $table->boolean('is_primary')->default(false)->index();

    $table->timestampTz('starts_at')->nullable()->index();
    $table->timestampTz('ends_at')->nullable()->index();

    $table->uuid('replaced_by_involvement_id')->nullable()->index();
    $table->text('replacement_reason')->nullable();

    $table->text('notes')->nullable();
    $table->integer('sort_order')->default(0)->index();

    $table->jsonb('metadata')->nullable();
    $table->timestampsTz();
});
```

## `event_registrations` migration example

```php
Schema::create('event_registrations', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->uuid('event_id')->index();
    $table->uuid('event_occurrence_id')->nullable()->index();
    $table->uuid('event_session_id')->nullable()->index();

    $table->string('registrant_type')->nullable()->index();
    $table->uuid('registrant_id')->nullable()->index();
    $table->index(['registrant_type', 'registrant_id']);

    $table->string('registration_no')->unique();
    $table->string('registration_type')->index();
    $table->string('status')->index();
    $table->string('source')->index();

    $table->integer('total_participants')->default(1);
    $table->decimal('total_amount', 12, 2)->nullable();
    $table->string('currency')->nullable();

    $table->uuid('external_order_id')->nullable()->index();
    $table->string('external_order_type')->nullable()->index();
    $table->string('payment_status')->nullable()->index();

    $table->timestampTz('registered_at')->index();
    $table->timestampTz('approved_at')->nullable()->index();
    $table->timestampTz('cancelled_at')->nullable()->index();
    $table->timestampTz('rejected_at')->nullable()->index();
    $table->timestampTz('waitlisted_at')->nullable()->index();
    $table->timestampTz('expired_at')->nullable()->index();

    $table->text('status_reason')->nullable();
    $table->text('notes')->nullable();
    $table->jsonb('metadata')->nullable();
    $table->timestampsTz();
});
```

## `event_change_logs` migration example

```php
Schema::create('event_change_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->uuid('event_id')->index();
    $table->uuid('event_occurrence_id')->nullable()->index();
    $table->uuid('event_session_id')->nullable()->index();

    $table->string('subject_type')->index();
    $table->uuid('subject_id')->nullable()->index();
    $table->index(['subject_type', 'subject_id']);

    $table->string('change_type')->index();
    $table->string('change_category')->index();

    $table->jsonb('old_value')->nullable();
    $table->jsonb('new_value')->nullable();

    $table->text('reason')->nullable();
    $table->text('internal_notes')->nullable();

    $table->string('impact_level')->index();
    $table->string('visibility')->index();
    $table->boolean('requires_notification')->default(false)->index();

    $table->string('changed_by_type')->nullable()->index();
    $table->uuid('changed_by_id')->nullable()->index();
    $table->index(['changed_by_type', 'changed_by_id']);

    $table->timestampTz('changed_at')->index();

    $table->jsonb('metadata')->nullable();
    $table->timestampTz('created_at')->useCurrent();
});
```

## Migration lint checklist

A CI script should inspect migration files and fail if any of these appear:

```text
softDeletes
softDeletesTz
foreign(
constrained(
cascadeOnDelete
restrictOnDelete
nullOnDelete
foreignId(
```

Exceptions must be explicitly approved and documented. Default is no exceptions.

## Suggested indexes

For scoped tables:

```php
$table->index(['event_id', 'event_occurrence_id', 'event_session_id']);
```

For polymorphic references:

```php
$table->index(['model_type', 'model_id']);
```

For lifecycle queries:

```php
$table->index(['status', 'visibility']);
$table->index('starts_at');
$table->index('published_at');
```

For geospatial/radius search:

```text
latitude index
longitude index
geo_point geospatial index if PostGIS supported
```

## Checklist

- [x] UUID primary key on every table.
- [x] No soft deletes.
- [x] No DB foreign keys.
- [x] No cascade rules.
- [x] Scope indexes on event/occurrence/session tables.
- [x] Composite indexes for polymorphic pairs.
- [x] JSONB metadata only where useful.
- [x] Lifecycle timestamp columns use `timestampTz()`.
- [x] Normal mutable records use `timestampsTz()`.
