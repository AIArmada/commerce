---
title: Implement aiarmada/feedback and aiarmada/filament-feedback
---

# Implement `aiarmada/feedback` and `aiarmada/filament-feedback`

This document is the implementation instruction for two new packages:

1. `aiarmada/feedback` — the core feedback, survey, review, testimonial, and response engine.
2. `aiarmada/filament-feedback` — the Filament v5 admin adapter for managing forms, responses, analytics, invitations, templates, and testimonials.

Use this document as an agent handoff. Follow the monorepo rules in `AGENTS.md` strictly.

## Non-negotiable monorepo rules

Before editing code, read:

```txt
CONTEXT-MAP.md
packages/commerce-support/CONTEXT.md
packages/feedback/CONTEXT.md        # after creating it
packages/filament-feedback/CONTEXT.md # after creating it
relevant sibling package CONTEXT.md files
```

Apply these rules throughout:

- Target PHP 8.4+ only.
- Use Filament v5 APIs for the adapter package.
- Keep `filament-*` packages as adapters only. They must not own domain logic.
- Keep packages standalone. Use optional integrations with `class_exists()` and `suggest`, not hard `require`, unless the dependency is foundational and already required by the monorepo convention.
- Check `commerce-support` before creating new tenant, owner, cache, filesystem, money, resolver, or shared support primitives.
- Use UUID primary keys: `$table->uuid('id')->primary()`.
- Use `foreignUuid()` for foreign key columns, but do not create database foreign key constraints.
- Never use `->constrained()`, `->cascadeOnDelete()`, or database cascades.
- No soft deletes.
- Use application-level integrity and delete/null-out behavior through Actions, services, and model lifecycle hooks.
- Any package using JSON columns must expose and use a `json_column_type` config setting.
- Tenant-owned tables use `$table->nullableMorphs('owner')`.
- Tenant-owned models use `HasOwner` from `commerce-support`.
- Owner scoping must be enforced on every read and write path, including HTTP, Filament, exports, widgets, jobs, commands, and aggregate queries.
- Filament tenancy is not security. Validate submitted IDs again server-side.
- Business logic belongs in Actions, not controllers, not Filament resources, and not fat models.
- Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Do not set `protected $table`; implement `getTable()` using package config.
- Use immutable datetime casts.
- Use per-package verification. Never run Pint repo-wide just to be safe.
- All Pest/PHPUnit runs must include `--parallel`.

## Implementation goal

Build a reusable feedback engine that can attach forms and responses to any domain model:

```php
Event::class
Occurrence::class
Session::class
Speaker::class
Venue::class
Course::class
Product::class
Order::class
Service::class
Organization::class
Contact::class
```

The package must support:

- post-event feedback
- session feedback
- speaker feedback
- venue feedback
- NPS
- CSAT
- customer satisfaction
- training evaluation
- product reviews
- testimonial collection
- complaints
- lead qualification
- pre-event surveys
- anonymous or identified responses
- invitation links
- public or private forms
- scoring
- branching / visibility rules
- reusable templates
- analytics
- export-ready response queries
- integration events for certificates, engagement, events, notifications, and future AI summarisation

The core package must not be a Google Form clone sahaja. Kalau setakat question-answer table, itu borang pakai helmet tapi tak ada motor.

## Package boundaries

### `aiarmada/feedback`

Owns:

- database schema
- domain models
- enums
- contracts
- traits
- Actions
- services
- validation
- scoring
- analytics
- invitation token logic
- testimonial extraction/moderation state
- domain events
- optional submission routes/controllers if enabled by config
- package docs
- tests

Must not own:

- Filament resources, pages, widgets, or UI schemas
- event-specific business rules
- certificate eligibility rules
- public marketing page rendering
- AI summarisation, unless later added as a separate optional package
- engagement interactions like likes, shares, comments, bookmarks

### `aiarmada/filament-feedback`

Owns:

- Filament plugin/provider registration
- resources
- relation managers
- pages
- widgets
- tables
- forms
- built-in Filament import/export wiring
- admin actions that call core Actions
- package docs
- Filament tests

Must not own:

- migrations for core domain tables
- response submission logic
- scoring logic
- invitation token validation logic
- analytics calculations beyond presentation/wrapping core services
- owner scoping primitives

## Target package layout

### Core package

```txt
packages/feedback/
├── CONTEXT.md
├── composer.json
├── config/
│   └── feedback.php
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/
│   ├── 01-overview.md
│   ├── 02-installation.md
│   ├── 03-configuration.md
│   ├── 04-usage.md
│   └── 99-troubleshooting.md
├── src/
│   ├── Actions/
│   ├── Analytics/
│   ├── Contracts/
│   ├── Data/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   ├── Models/
│   ├── Policies/
│   ├── Support/
│   ├── Traits/
│   └── FeedbackServiceProvider.php
└── tests/
```

### Filament adapter

```txt
packages/filament-feedback/
├── CONTEXT.md
├── composer.json
├── config/
│   └── filament-feedback.php
├── docs/
│   ├── 01-overview.md
│   ├── 02-installation.md
│   ├── 03-configuration.md
│   ├── 04-usage.md
│   └── 99-troubleshooting.md
├── src/
│   ├── Actions/
│   ├── Exports/
│   ├── Filament/
│   │   ├── Pages/
│   │   ├── Resources/
│   │   └── Widgets/
│   ├── Support/
│   └── FilamentFeedbackServiceProvider.php
└── tests/
```

## Step 1 — Create package contexts

Create `packages/feedback/CONTEXT.md`:

```md
---
title: Feedback Package Context
package: aiarmada/feedback
status: active
surface: core
family: feedback
---

## Snapshot

Composer package: `aiarmada/feedback`.

This package owns the core feedback, survey, response, invitation, scoring, analytics, and testimonial domain.

Start code search in:

- `packages/feedback/src/Models`
- `packages/feedback/src/Actions`
- `packages/feedback/src/Analytics`
- `packages/feedback/src/Events`
- `packages/feedback/database/migrations`
- `packages/feedback/config/feedback.php`

Related packages:

- `aiarmada/commerce-support`
- `aiarmada/filament-feedback`
- `aiarmada/events`
- `aiarmada/certificates`
- `aiarmada/engagement`
- `aiarmada/contacting`

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../commerce-support/CONTEXT.md`
- `../filament-feedback/CONTEXT.md`

## Guardrails

This package owns the feedback domain only.

Do not put Filament resources, pages, widgets, or admin UI here.

Do not put certificate eligibility, event attendance, or engagement interaction logic here.

Feedback forms and responses are tenant-owned through `owner_type` / `owner_id` and must enforce owner scoping on every read and write path.

Use UUID primary keys, configurable table names, configurable JSON column type, no database foreign constraints, no database cascades, and no soft deletes.
```

Create `packages/filament-feedback/CONTEXT.md`:

```md
---
title: Filament Feedback Package Context
package: aiarmada/filament-feedback
status: active
surface: filament
family: feedback
---

## Snapshot

Composer package: `aiarmada/filament-feedback`.

This package is the Filament v5 admin adapter for `aiarmada/feedback`.

Start code search in:

- `packages/filament-feedback/src/Filament/Resources`
- `packages/filament-feedback/src/Filament/Pages`
- `packages/filament-feedback/src/Filament/Widgets`
- `packages/filament-feedback/src/Support`
- `packages/filament-feedback/config/filament-feedback.php`

Related packages:

- `aiarmada/feedback`
- `aiarmada/commerce-support`
- `aiarmada/events`
- `aiarmada/certificates`
- `aiarmada/engagement`

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../feedback/CONTEXT.md`
- `../commerce-support/CONTEXT.md`

## Guardrails

This package is an adapter only.

Do not create core feedback domain tables here.

Do not implement scoring, submission, invitation token validation, or testimonial state transitions here.

All Filament resources, actions, widgets, exports, and relation managers must be owner-scoped and must call core package Actions for writes.
```

## Step 2 — Create composer packages

### `packages/feedback/composer.json`

Use the existing package composer conventions from sibling packages. Do not invent structure if the monorepo already has package skeleton conventions.

Required intent:

```json
{
  "name": "aiarmada/feedback",
  "description": "Core feedback, survey, response, invitation, analytics, and testimonial package for AI Armada applications.",
  "type": "library",
  "autoload": {
    "psr-4": {
      "AIArmada\\Feedback\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "AIArmada\\Feedback\\FeedbackServiceProvider"
      ]
    }
  },
  "suggest": {
    "aiarmada/filament-feedback": "Admin panel adapter for managing feedback forms and responses.",
    "aiarmada/events": "Attach feedback forms to events, occurrences, sessions, speakers, and venues.",
    "aiarmada/certificates": "React to feedback submission before issuing certificates.",
    "aiarmada/engagement": "Publish approved testimonials and public review summaries."
  }
}
```

Only add hard dependencies after checking sibling package style and current monorepo dependency policy.

### `packages/filament-feedback/composer.json`

```json
{
  "name": "aiarmada/filament-feedback",
  "description": "Filament v5 admin adapter for aiarmada/feedback.",
  "type": "library",
  "autoload": {
    "psr-4": {
      "AIArmada\\FilamentFeedback\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "AIArmada\\FilamentFeedback\\FilamentFeedbackServiceProvider"
      ]
    }
  },
  "require": {
    "aiarmada/feedback": "*"
  }
}
```

If the monorepo uses path repositories, update the root composer configuration consistently with sibling packages.

## Step 3 — Core config

Create `packages/feedback/config/feedback.php`.

Keep keys minimal. Do not add keys that are not read.

Follow this order:

1. Database
2. Defaults
3. Features / Behavior
4. Integrations
5. HTTP
6. Cache
7. Logging

Recommended config shape:

```php
<?php

declare(strict_types=1);

return [
    'database' => [
        'table_prefix' => '',
        'json_column_type' => 'jsonb',

        'tables' => [
            'forms' => 'feedback_forms',
            'sections' => 'feedback_sections',
            'questions' => 'feedback_questions',
            'question_options' => 'feedback_question_options',
            'responses' => 'feedback_responses',
            'answers' => 'feedback_answers',
            'invitations' => 'feedback_invitations',
            'templates' => 'feedback_templates',
            'testimonials' => 'feedback_testimonials',
        ],
    ],

    'owner' => [
        'enabled' => true,
        'auto_assign_on_create' => true,
        'include_global_templates' => false,
    ],

    'defaults' => [
        'form_status' => 'draft',
        'visibility' => 'private',
        'response_status' => 'draft',
        'invitation_expiry_days' => 14,
    ],

    'features' => [
        'anonymous_responses' => true,
        'invitations' => true,
        'testimonials' => true,
        'templates' => true,
        'analytics' => true,
    ],

    'integrations' => [
        'events' => true,
        'certificates' => true,
        'engagement' => true,
    ],

    'http' => [
        'routes_enabled' => false,
        'route_prefix' => 'feedback',
        'middleware' => ['web'],
    ],

    'cache' => [
        'analytics_ttl_seconds' => 300,
    ],

    'logging' => [
        'enabled' => false,
    ],
];
```

Important:

- `json_column_type` must be used by migrations.
- Table names must be read by models through `getTable()`.
- Owner config must be wired through `HasOwnerScopeConfig` if the project already uses that pattern.
- Do not use env vars unless they are secrets or deploy-time values.

## Step 4 — Filament adapter config

Create `packages/filament-feedback/config/filament-feedback.php`.

Recommended shape:

```php
<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Feedback',
        'icon' => 'heroicon-o-chat-bubble-left-right',
        'sort' => 70,
    ],

    'tables' => [
        'default_pagination' => 25,
    ],

    'features' => [
        'forms' => true,
        'responses' => true,
        'invitations' => true,
        'templates' => true,
        'testimonials' => true,
        'analytics_dashboard' => true,
        'exports' => true,
    ],

    'resources' => [
        'feedback_form' => true,
        'feedback_response' => true,
        'feedback_invitation' => true,
        'feedback_template' => true,
        'feedback_testimonial' => true,
    ],
];
```

Do not add unused UI settings.

## Step 5 — Core migrations

Create one main migration for all core tables unless sibling packages consistently split tables.

Migration rules:

- Use UUID primary keys.
- Use `foreignUuid()` for foreign-key columns only.
- Do not call `constrained()`.
- Do not add database foreign key constraints.
- Do not add cascade rules.
- Do not add soft deletes.
- Use `$table->nullableMorphs('owner')` for tenant-owned tables.
- Use `uuidMorphs` / `nullableUuidMorphs` for domain polymorphic relationships if available in the installed Laravel version.
- Use config table names.
- Use config JSON column type.
- Keep migration idempotent with `Schema::hasTable()` guards if sibling packages do this.

### JSON helper instruction

Create a small migration helper inside the migration closure if sibling packages do not already have one:

```php
$jsonColumnType = config('feedback.database.json_column_type', 'jsonb');

$addJsonColumn = function (Blueprint $table, string $column) use ($jsonColumnType): void {
    if ($jsonColumnType === 'jsonb') {
        $table->jsonb($column)->nullable();

        return;
    }

    $table->json($column)->nullable();
};
```

### Table: `feedback_forms`

Purpose: one feedback/survey/review/testimonial form.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->string('name');
$table->string('slug')->nullable();
$table->string('purpose')->index();
$table->string('status')->index();
$table->string('visibility')->index();

$table->nullableUuidMorphs('subject');

$table->boolean('is_anonymous_allowed')->default(true);
$table->boolean('is_anonymity_optional')->default(false);
$table->boolean('is_login_required')->default(false);
$table->boolean('is_one_response_per_respondent')->default(false);
$table->boolean('is_edit_after_submit_allowed')->default(false);

$table->timestampTz('opens_at')->nullable();
$table->timestampTz('closes_at')->nullable();
$table->timestampTz('published_at')->nullable();
$table->timestampTz('closed_at')->nullable();
$table->timestampTz('archived_at')->nullable();

$addJsonColumn($table, 'settings');
$addJsonColumn($table, 'metadata');

$table->nullableUuidMorphs('created_by');

$table->timestampsTz();

$table->index(['subject_type', 'subject_id']);
$table->index(['owner_type', 'owner_id']);
$table->index(['status', 'visibility']);
```

Notes:

- `purpose` is not a hard-coded DB enum. Use PHP enum values stored as strings.
- `subject` means what this form is about: event, session, speaker, product, venue, etc.
- `owner` is the tenant boundary, not the feedback subject.
- `owner = null` means global-only rows, not all owners.

### Table: `feedback_sections`

Purpose: optional form section grouping.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_form_id')->index();
$table->string('key')->nullable()->index();
$table->string('title');
$table->text('description')->nullable();
$table->unsignedInteger('order_column')->default(0);

$addJsonColumn($table, 'settings');
$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['feedback_form_id', 'order_column']);
```

### Table: `feedback_questions`

Purpose: individual questions inside a form.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_form_id')->index();
$table->foreignUuid('feedback_section_id')->nullable()->index();

$table->string('key')->index();
$table->string('type')->index();
$table->string('label');
$table->text('description')->nullable();
$table->text('help_text')->nullable();
$table->string('placeholder')->nullable();

$table->boolean('is_required')->default(false);
$table->boolean('is_scored')->default(false);
$table->unsignedInteger('order_column')->default(0);

$addJsonColumn($table, 'validation_rules');
$addJsonColumn($table, 'visibility_rules');
$addJsonColumn($table, 'scoring_rules');
$addJsonColumn($table, 'settings');
$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['feedback_form_id', 'key']);
$table->index(['feedback_form_id', 'order_column']);
```

Notes:

- `key` is the stable developer-friendly identifier, for example `overall_rating`.
- Validate uniqueness of `key` per form in Actions, not DB constraints.

### Table: `feedback_question_options`

Purpose: choices for single choice, multiple choice, dropdown, likert, matrix rows/options, etc.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_question_id')->index();

$table->string('label');
$table->string('value')->index();
$table->decimal('score', 10, 2)->nullable();
$table->unsignedInteger('order_column')->default(0);

$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['feedback_question_id', 'order_column']);
```

### Table: `feedback_responses`

Purpose: one respondent submission attempt.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_form_id')->index();
$table->foreignUuid('feedback_invitation_id')->nullable()->index();

$table->nullableUuidMorphs('subject');
$table->nullableUuidMorphs('respondent');

$table->string('status')->index();
$table->boolean('is_anonymous')->default(false);
$table->boolean('is_editable')->default(false);

$table->decimal('score', 10, 2)->nullable();
$table->decimal('max_score', 10, 2)->nullable();

$table->timestampTz('started_at')->nullable();
$table->timestampTz('submitted_at')->nullable();
$table->timestampTz('reviewed_at')->nullable();
$table->timestampTz('rejected_at')->nullable();
$table->timestampTz('marked_spam_at')->nullable();

$table->string('ip_address')->nullable();
$table->text('user_agent')->nullable();

$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['subject_type', 'subject_id']);
$table->index(['respondent_type', 'respondent_id']);
$table->index(['feedback_form_id', 'status']);
$table->index(['submitted_at']);
```

Notes:

- `subject` snapshots the form subject at response time.
- `respondent` can be `User`, `Participant`, `Customer`, `Contact`, or another model.
- For anonymous responses, respondent can be null.
- If storing IP/user agent is sensitive for the app, make it controlled by settings and docs.

### Table: `feedback_answers`

Purpose: one answer per question per response.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_response_id')->index();
$table->foreignUuid('feedback_question_id')->index();

$addJsonColumn($table, 'value');

$table->text('text_value')->nullable();
$table->decimal('number_value', 12, 4)->nullable();
$table->boolean('boolean_value')->nullable();
$table->date('date_value')->nullable();
$table->timestampTz('datetime_value')->nullable();

$table->decimal('score', 10, 2)->nullable();

$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['feedback_response_id', 'feedback_question_id']);
$table->index(['feedback_question_id', 'number_value']);
$table->index(['feedback_question_id', 'score']);
```

Notes:

- `value` stores the canonical answer.
- Typed columns exist for analytics and filtering.
- Normalize answer values through an Action/service, not inside controllers.

### Table: `feedback_invitations`

Purpose: private feedback request link.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_form_id')->index();
$table->nullableUuidMorphs('recipient');

$table->string('email')->nullable()->index();
$table->string('phone')->nullable()->index();
$table->string('token_hash')->unique();
$table->string('status')->index();

$table->timestampTz('sent_at')->nullable();
$table->timestampTz('opened_at')->nullable();
$table->timestampTz('started_at')->nullable();
$table->timestampTz('submitted_at')->nullable();
$table->timestampTz('cancelled_at')->nullable();
$table->timestampTz('expires_at')->nullable();

$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['recipient_type', 'recipient_id']);
$table->index(['feedback_form_id', 'status']);
```

Notes:

- Do not store raw invitation tokens.
- Generate raw token once, hash it, store the hash, and only expose the raw token in the generated URL.
- Use timing-safe comparison / hash lookup.
- Expiry belongs in `expires_at`, not in JSON.

### Table: `feedback_templates`

Purpose: reusable form blueprints.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->string('name');
$table->string('slug')->index();
$table->string('purpose')->index();
$table->string('category')->nullable()->index();
$table->string('status')->index();

$addJsonColumn($table, 'definition');
$addJsonColumn($table, 'settings');
$addJsonColumn($table, 'metadata');

$table->timestampTz('published_at')->nullable();
$table->timestampTz('archived_at')->nullable();

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['purpose', 'status']);
```

Notes:

- Store template sections/questions/options in `definition`.
- Templates are copied into real `feedback_forms`, `feedback_sections`, `feedback_questions`, and `feedback_question_options`.
- Do not submit responses directly against a template.

### Table: `feedback_testimonials`

Purpose: moderated public testimonial extracted from feedback.

Columns:

```php
$table->uuid('id')->primary();
$table->nullableMorphs('owner');

$table->foreignUuid('feedback_response_id')->nullable()->index();
$table->foreignUuid('feedback_answer_id')->nullable()->index();

$table->nullableUuidMorphs('subject');
$table->nullableUuidMorphs('respondent');

$table->text('quote');
$table->string('display_name')->nullable();
$table->string('display_title')->nullable();
$table->string('display_organization')->nullable();

$table->decimal('rating', 10, 2)->nullable();
$table->string('status')->index();

$table->timestampTz('permission_given_at')->nullable();
$table->timestampTz('approved_at')->nullable();
$table->timestampTz('rejected_at')->nullable();
$table->timestampTz('published_at')->nullable();
$table->timestampTz('hidden_at')->nullable();

$addJsonColumn($table, 'metadata');

$table->timestampsTz();

$table->index(['owner_type', 'owner_id']);
$table->index(['subject_type', 'subject_id']);
$table->index(['respondent_type', 'respondent_id']);
$table->index(['status', 'published_at']);
```

Notes:

- A testimonial must not become public without permission.
- Permission timestamp must be explicit.
- Approval and publishing must be state transitions handled by Actions.

## Step 6 — Core enums

Create backed string enums with TitleCase enum keys.

Recommended enums:

```txt
Enums/
├── FeedbackFormPurpose.php
├── FeedbackFormStatus.php
├── FeedbackFormVisibility.php
├── FeedbackQuestionType.php
├── FeedbackResponseStatus.php
├── FeedbackInvitationStatus.php
├── FeedbackTemplateStatus.php
└── FeedbackTestimonialStatus.php
```

### `FeedbackFormPurpose`

Values:

```php
General = 'general'
PostEventFeedback = 'post_event_feedback'
PostOccurrenceFeedback = 'post_occurrence_feedback'
PostSessionFeedback = 'post_session_feedback'
SpeakerFeedback = 'speaker_feedback'
VenueFeedback = 'venue_feedback'
TrainingEvaluation = 'training_evaluation'
TestimonialCollection = 'testimonial_collection'
ProductReview = 'product_review'
Complaint = 'complaint'
LeadQualification = 'lead_qualification'
CustomerSatisfaction = 'customer_satisfaction'
Nps = 'nps'
Csat = 'csat'
PreEventSurvey = 'pre_event_survey'
```

### `FeedbackFormStatus`

```php
Draft = 'draft'
Published = 'published'
Closed = 'closed'
Archived = 'archived'
```

Lifecycle timestamp mapping:

- `Published` sets `published_at`
- `Closed` sets `closed_at`
- `Archived` sets `archived_at`

### `FeedbackFormVisibility`

```php
Private = 'private'
Public = 'public'
InviteOnly = 'invite_only'
Embedded = 'embedded'
```

### `FeedbackQuestionType`

```php
ShortText = 'short_text'
LongText = 'long_text'
Email = 'email'
Phone = 'phone'
Number = 'number'
Date = 'date'
Time = 'time'
DateTime = 'datetime'
SingleChoice = 'single_choice'
MultipleChoice = 'multiple_choice'
Dropdown = 'dropdown'
Rating = 'rating'
StarRating = 'star_rating'
Scale = 'scale'
Nps = 'nps'
Csat = 'csat'
YesNo = 'yes_no'
Boolean = 'boolean'
Matrix = 'matrix'
Likert = 'likert'
Ranking = 'ranking'
FileUpload = 'file_upload'
Signature = 'signature'
Statement = 'statement'
Divider = 'divider'
Heading = 'heading'
```

Only implement storage/validation for file upload and signature if media/storage support already exists. Otherwise define the enum but mark it disabled until a media integration is approved.

### `FeedbackResponseStatus`

```php
Draft = 'draft'
Submitted = 'submitted'
Reviewed = 'reviewed'
Rejected = 'rejected'
Spam = 'spam'
```

Lifecycle timestamp mapping:

- `Submitted` sets `submitted_at`
- `Reviewed` sets `reviewed_at`
- `Rejected` sets `rejected_at`
- `Spam` sets `marked_spam_at`

### `FeedbackInvitationStatus`

```php
Pending = 'pending'
Sent = 'sent'
Opened = 'opened'
Started = 'started'
Submitted = 'submitted'
Expired = 'expired'
Cancelled = 'cancelled'
```

Lifecycle timestamp mapping:

- `Sent` sets `sent_at`
- `Opened` sets `opened_at`
- `Started` sets `started_at`
- `Submitted` sets `submitted_at`
- `Cancelled` sets `cancelled_at`
- `Expired` does not replace `expires_at`; it is derived or explicitly marked by an Action.

### `FeedbackTestimonialStatus`

```php
Pending = 'pending'
Approved = 'approved'
Rejected = 'rejected'
Published = 'published'
Hidden = 'hidden'
```

Lifecycle timestamp mapping:

- `Approved` sets `approved_at`
- `Rejected` sets `rejected_at`
- `Published` sets `published_at`
- `Hidden` sets `hidden_at`

## Step 7 — Core models

Create models:

```txt
Models/
├── FeedbackForm.php
├── FeedbackSection.php
├── FeedbackQuestion.php
├── FeedbackQuestionOption.php
├── FeedbackResponse.php
├── FeedbackAnswer.php
├── FeedbackInvitation.php
├── FeedbackTemplate.php
└── FeedbackTestimonial.php
```

Each model must:

- use `HasUuids`
- use `HasOwner` when tenant-owned
- implement `getTable()` from config
- use explicit casts
- use immutable datetime casts
- type relations with PHPDoc generics
- avoid business orchestration
- implement application-level cascade/null-out behavior in `booted()` where direct deletes are possible

Example pattern:

```php
<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Concerns\HasOwner;
use AIArmada\CommerceSupport\Concerns\HasOwnerScopeConfig;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FeedbackForm extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => FeedbackFormStatus::class,
            'visibility' => FeedbackFormVisibility::class,
            'is_anonymous_allowed' => 'boolean',
            'is_anonymity_optional' => 'boolean',
            'is_login_required' => 'boolean',
            'is_one_response_per_respondent' => 'boolean',
            'is_edit_after_submit_allowed' => 'boolean',
            'settings' => 'array',
            'metadata' => 'array',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix.(string) config('feedback.database.tables.forms', 'feedback_forms');
    }

    protected static function ownerScopeConfig(): array
    {
        return [
            'enabled' => (bool) config('feedback.owner.enabled', true),
            'auto_assign_on_create' => (bool) config('feedback.owner.auto_assign_on_create', true),
        ];
    }
}
```

Adjust `ownerScopeConfig()` to the exact existing `commerce-support` contract.

### Required relationships

`FeedbackForm`:

```php
sections(): HasMany
questions(): HasMany
responses(): HasMany
invitations(): HasMany
subject(): MorphTo
createdBy(): MorphTo
```

`FeedbackSection`:

```php
form(): BelongsTo
questions(): HasMany
```

`FeedbackQuestion`:

```php
form(): BelongsTo
section(): BelongsTo
options(): HasMany
answers(): HasMany
```

`FeedbackQuestionOption`:

```php
question(): BelongsTo
```

`FeedbackResponse`:

```php
form(): BelongsTo
invitation(): BelongsTo
answers(): HasMany
subject(): MorphTo
respondent(): MorphTo
```

`FeedbackAnswer`:

```php
response(): BelongsTo
question(): BelongsTo
```

`FeedbackInvitation`:

```php
form(): BelongsTo
recipient(): MorphTo
```

`FeedbackTemplate`:

No child relation required if using JSON `definition`.

`FeedbackTestimonial`:

```php
response(): BelongsTo
answer(): BelongsTo
subject(): MorphTo
respondent(): MorphTo
```

## Step 8 — Traits for consuming packages

Create:

```txt
Traits/
├── ReceivesFeedback.php
└── GivesFeedback.php
```

### `ReceivesFeedback`

For models that are subjects of feedback, such as Event, Occurrence, Session, Speaker, Venue, Product.

Methods:

```php
feedbackForms(): MorphMany
feedbackResponses(): MorphMany
feedbackTestimonials(): MorphMany
createFeedbackFormFromTemplate(string|FeedbackTemplate $template, array $overrides = []): FeedbackForm
averageFeedbackScore(?string $questionKey = null): ?float
npsScore(?FeedbackForm $form = null): ?int
```

Important:

- Queries must be owner-scoped.
- Do not use raw unscoped `DB::table()` unless applying `OwnerQuery`.
- Trait methods must not assume the consuming model is tenant-owned. If it is tenant-owned, inherit current owner or validate owner compatibility through Actions.

### `GivesFeedback`

For models that submit responses, such as User, Participant, Customer, Contact.

Methods:

```php
feedbackResponses(): MorphMany
hasSubmittedFeedbackFor(Model $subject, ?FeedbackForm $form = null): bool
latestFeedbackResponseFor(Model $subject, ?FeedbackForm $form = null): ?FeedbackResponse
```

Important:

- Anonymous responses may not link to respondent.
- Do not leak anonymous identity through helper methods.

## Step 9 — Contracts

Create contracts only where they add real extension value.

Recommended:

```txt
Contracts/
├── FeedbackSubject.php
├── FeedbackRespondent.php
├── InvitationUrlGenerator.php
├── FeedbackAnalyticsCalculator.php
└── AnswerNormalizer.php
```

`InvitationUrlGenerator` is useful because apps may generate different domains or signed URLs.

`AnswerNormalizer` is useful because every question type has different storage and scoring behavior.

Bind default implementations in `FeedbackServiceProvider`.

## Step 10 — Data objects

If `spatie/laravel-data` is already used in the monorepo, use it. If not, do not add the dependency without approval.

Recommended data objects:

```txt
Data/
├── CreateFeedbackFormData.php
├── CreateFeedbackQuestionData.php
├── SubmitFeedbackResponseData.php
├── SubmittedAnswerData.php
├── FeedbackAnalyticsData.php
├── NpsResultData.php
└── CsatResultData.php
```

Keep DTOs typed and small.

## Step 11 — Core Actions

Create Actions for every meaningful workflow.

```txt
Actions/
├── CreateFeedbackFormAction.php
├── CreateFeedbackFormFromTemplateAction.php
├── PublishFeedbackFormAction.php
├── CloseFeedbackFormAction.php
├── ArchiveFeedbackFormAction.php
├── DuplicateFeedbackFormAction.php
├── DeleteFeedbackFormAction.php
├── CreateFeedbackSectionAction.php
├── UpdateFeedbackSectionAction.php
├── ReorderFeedbackSectionsAction.php
├── CreateFeedbackQuestionAction.php
├── UpdateFeedbackQuestionAction.php
├── DeleteFeedbackQuestionAction.php
├── ReorderFeedbackQuestionsAction.php
├── CreateFeedbackQuestionOptionAction.php
├── UpdateFeedbackQuestionOptionAction.php
├── ReorderFeedbackQuestionOptionsAction.php
├── SendFeedbackInvitationAction.php
├── GenerateFeedbackInvitationUrlAction.php
├── ResolveFeedbackInvitationTokenAction.php
├── MarkFeedbackInvitationOpenedAction.php
├── StartFeedbackResponseAction.php
├── SubmitFeedbackResponseAction.php
├── ValidateFeedbackAnswersAction.php
├── NormalizeFeedbackAnswerAction.php
├── CalculateFeedbackAnswerScoreAction.php
├── CalculateFeedbackResponseScoreAction.php
├── CalculateFeedbackFormAnalyticsAction.php
├── ExtractFeedbackTestimonialAction.php
├── ApproveFeedbackTestimonialAction.php
├── RejectFeedbackTestimonialAction.php
├── PublishFeedbackTestimonialAction.php
└── HideFeedbackTestimonialAction.php
```

### Action rules

- Use constructor property promotion.
- Use explicit parameter and return types.
- Use database transactions for multi-row writes.
- Use owner guards for inbound IDs.
- Do not trust Filament form option scoping.
- Do not mutate global rows without explicit global context.
- Dispatch domain events after successful transitions.
- Keep Actions reusable from HTTP, Filament, jobs, commands, and tests.

Example Action style:

```php
<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormPublished;
use AIArmada\Feedback\Models\FeedbackForm;
use Carbon\CarbonImmutable;

final class PublishFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        if ($form->status === FeedbackFormStatus::Published) {
            return $form;
        }

        $form->forceFill([
            'status' => FeedbackFormStatus::Published,
            'published_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackFormPublished::dispatch($form);

        return $form;
    }
}
```

If the project uses model states from Spatie, first check whether sibling packages already use it. Do not add a new dependency just for this package unless approved.

## Step 12 — Question validation and answer normalization

Create a support class:

```txt
Support/
├── QuestionTypeRegistry.php
├── QuestionTypeDefinition.php
├── ValidationRuleBuilder.php
├── AnswerValueNormalizer.php
└── ScoreCalculator.php
```

### Required question behavior

#### Text questions

Types:

```txt
short_text
long_text
email
phone
```

Store:

- `value` as JSON string
- `text_value`
- no score unless scoring rules define one

Validate:

- required / nullable
- string
- max length from settings
- email format for email
- phone string rules if project has shared phone validation

#### Number questions

Types:

```txt
number
rating
star_rating
scale
nps
csat
```

Store:

- `value`
- `number_value`
- `score` where applicable

Validate:

- numeric
- min / max from settings
- integer if configured
- NPS min 0 max 10 by default
- CSAT min 1 max 5 by default

#### Choice questions

Types:

```txt
single_choice
dropdown
yes_no
boolean
multiple_choice
ranking
likert
matrix
```

Store:

- single choice: selected option value in `text_value`
- multiple/ranking/matrix: array/object in `value`
- numeric/boolean typed fields when appropriate
- score from option scores or scoring rules

Validate:

- selected values must belong to owner-scoped options for the question
- submitted option IDs/values must not cross owner/form/question boundary
- required behavior must reject empty array when required

#### Display-only questions

Types:

```txt
statement
divider
heading
```

Do not require answers.

#### File upload and signature

Only enable if approved storage/media support exists.

If not implemented in the first pass:

- keep enum values
- add registry entries as disabled
- make Filament hide these types by default
- document as planned/disabled

## Step 13 — Branching and visibility rules

Implement support for basic visibility rules.

Example JSON:

```json
{
  "show_if": {
    "question_key": "overall_rating",
    "operator": "<=",
    "value": 2
  }
}
```

Supported operators:

```txt
=
!=
>
>=
<
<=
in
not_in
contains
not_contains
empty
not_empty
```

Rules:

- Visibility rules affect rendering and validation.
- Hidden questions must not be required.
- Submitted answers for hidden questions should either be ignored or rejected. Choose one behavior and document it. Recommended: reject by default unless `settings.allow_hidden_answers` is true.
- Server-side validation must re-evaluate visibility. Do not trust the browser or Filament state.

## Step 14 — Scoring, NPS, and CSAT

Create analytics/scoring services:

```txt
Analytics/
├── FeedbackAnalyticsService.php
├── NpsCalculator.php
├── CsatCalculator.php
├── RatingDistributionCalculator.php
└── CompletionRateCalculator.php
```

### NPS

NPS calculation:

```txt
promoters = scores 9-10
passives = scores 7-8
detractors = scores 0-6
NPS = percentage(promoters) - percentage(detractors)
```

Return:

```txt
score
promoter_count
passive_count
detractor_count
response_count
promoter_percentage
passive_percentage
detractor_percentage
```

### CSAT

Default CSAT:

```txt
satisfied = scores 4-5 on 1-5 scale
CSAT = satisfied / total * 100
```

Return:

```txt
score
satisfied_count
neutral_count
unsatisfied_count
response_count
average
distribution
```

### General analytics

Expose methods:

```php
summaryForForm(FeedbackForm $form): FeedbackAnalyticsData
averageForQuestion(FeedbackForm $form, string $questionKey): ?float
distributionForQuestion(FeedbackForm $form, string $questionKey): array
latestComments(FeedbackForm $form, int $limit = 10): Collection
completionRate(FeedbackForm $form): float
```

Rules:

- Queries must be owner-scoped.
- If using raw query builder for performance, apply owner scoping through `OwnerQuery`.
- Cache only through owner-safe cache primitives such as `OwnerCache`.
- Do not cache tenant-sensitive analytics with raw global cache keys.

## Step 15 — Invitation token flow

Implement secure invitations.

### Generate invitation

`SendFeedbackInvitationAction` should:

1. Validate form is published or allowed by settings.
2. Validate recipient belongs to current owner scope when it is an owned model.
3. Generate a high-entropy raw token.
4. Store only `token_hash`.
5. Set `status = pending` or `sent` depending on whether notification sending exists.
6. Set `expires_at`.
7. Return invitation plus raw URL through a value object, not by storing raw token.

### Resolve invitation

`ResolveFeedbackInvitationTokenAction` should:

1. Hash incoming token.
2. Find invitation by `token_hash`.
3. Reject expired/cancelled/submitted invitations where applicable.
4. Enter the invitation owner context if needed.
5. Return owner-safe invitation.

### Do not

- Store raw tokens.
- Log raw tokens.
- Expose `token_hash` in Filament tables.
- Let a token resolve a cross-tenant form without owner context.

## Step 16 — Response submission flow

`SubmitFeedbackResponseAction` should:

1. Resolve form owner context.
2. Confirm form is published and open.
3. Confirm visibility rules allow submission:
   - public
   - invite-only with valid invitation
   - private with authorized user
   - embedded according to config
4. Enforce login/identity rules.
5. Enforce anonymous/identified rules.
6. Enforce one-response-per-respondent when enabled.
7. Start or load draft response.
8. Evaluate branching/visibility rules.
9. Validate answers server-side.
10. Normalize answer values.
11. Store answers.
12. Calculate answer scores.
13. Calculate response score.
14. Set `status = submitted`.
15. Set `submitted_at`.
16. Mark invitation submitted if applicable.
17. Extract testimonial if form/settings request it and permission is present.
18. Dispatch `FeedbackResponseSubmitted`.

Use a transaction.

## Step 17 — Testimonials

Testimonials are feedback-derived marketing/public proof.

Rules:

- Do not automatically publish.
- Do not approve without permission.
- Keep testimonial state transitions in Actions.
- Keep permission timestamp explicit.
- Allow display name/title/organization to differ from the respondent model.
- Allow subject to be event/session/speaker/product/etc.
- Keep respondent null if anonymous.

Required Actions:

```txt
ExtractFeedbackTestimonialAction
ApproveFeedbackTestimonialAction
RejectFeedbackTestimonialAction
PublishFeedbackTestimonialAction
HideFeedbackTestimonialAction
```

Events:

```txt
FeedbackTestimonialExtracted
FeedbackTestimonialApproved
FeedbackTestimonialRejected
FeedbackTestimonialPublished
FeedbackTestimonialHidden
```

## Step 18 — Domain events

Create events:

```txt
Events/
├── FeedbackFormCreated.php
├── FeedbackFormPublished.php
├── FeedbackFormClosed.php
├── FeedbackInvitationCreated.php
├── FeedbackInvitationSent.php
├── FeedbackInvitationOpened.php
├── FeedbackResponseStarted.php
├── FeedbackResponseSubmitted.php
├── FeedbackResponseReviewed.php
├── FeedbackResponseRejected.php
├── FeedbackResponseMarkedSpam.php
├── FeedbackTestimonialExtracted.php
├── FeedbackTestimonialApproved.php
└── FeedbackTestimonialPublished.php
```

Events should expose typed model properties.

Use these for integration:

- certificates package listens to `FeedbackResponseSubmitted`
- engagement package listens to `FeedbackTestimonialPublished`
- notifications system listens to invitation events
- events package may update post-event completion metrics

Do not hard-code downstream package calls inside core Actions.

## Step 19 — Optional integration with events, certificates, and engagement

Do not require these packages.

Use optional integrations with `class_exists()` or contracts/registrars.

### Events package integration

Models like `Event`, `Occurrence`, `Session`, `Speaker`, and `Venue` should opt in with `ReceivesFeedback`.

The events package should be able to call:

```php
$event->feedbackForms();
$occurrence->feedbackForms();
$session->feedbackForms();
$speaker->feedbackForms();
$venue->feedbackForms();
```

Feedback must support all three event levels:

```txt
Event-level feedback
Occurrence-level feedback
Session-level feedback
```

Do not store event-specific fields in feedback tables.

### Certificates integration

Certificates package should listen for:

```php
FeedbackResponseSubmitted::class
```

Then certificate package decides:

```txt
Did participant attend?
Did participant submit required feedback?
Should certificate be issued?
```

Do not put certificate eligibility inside feedback.

### Engagement integration

Engagement package may consume approved/published testimonials.

Do not put likes/shares/comments in feedback.

## Step 20 — HTTP surface in core package

Core package may expose optional submission routes only if `feedback.http.routes_enabled` is true.

Possible routes:

```txt
GET  /feedback/forms/{feedbackForm:slug}
POST /feedback/forms/{feedbackForm}/responses
GET  /feedback/invitations/{token}
POST /feedback/invitations/{token}/responses
```

Rules:

- Use route model binding safely.
- Route model binding must not resolve cross-tenant rows.
- Public token routes must resolve owner context from invitation.
- Controllers must be thin and call Actions.
- Do not build full branded UI in core package.
- If full public UI is needed, create a separate frontend package or app-level views later.

## Step 21 — Policies and authorization

Create policies for core models if sibling packages do this.

Suggested abilities:

```txt
viewAny
view
create
update
delete
publish
close
archive
submit
review
reject
markSpam
export
approveTestimonial
publishTestimonial
```

Rules:

- Policies must respect owner scope.
- Do not rely only on Filament visibility.
- Private/invite-only forms must not be accessible by unauthorized users.

## Step 22 — Seed default templates

Create optional seeder:

```txt
database/seeders/FeedbackTemplateSeeder.php
```

Default templates:

1. Post Event Feedback
2. Occurrence Feedback
3. Session Feedback
4. Speaker Feedback
5. Venue Feedback
6. Training Evaluation
7. NPS Survey
8. CSAT Survey
9. Testimonial Request
10. Complaint Form
11. Product Review
12. Lead Qualification

Seeder rules:

- Use explicit global context for global templates.
- Do not accidentally create tenant templates without owner context.
- Make seeder idempotent by slug and owner/global context.
- Store templates in `definition` JSON.

Example template definition shape:

```json
{
  "sections": [
    {
      "key": "event_experience",
      "title": "Event Experience",
      "questions": [
        {
          "key": "overall_rating",
          "type": "rating",
          "label": "Overall, how would you rate this event?",
          "is_required": true,
          "settings": {
            "min": 1,
            "max": 5,
            "labels": {
              "1": "Very poor",
              "5": "Excellent"
            }
          }
        },
        {
          "key": "recommend_score",
          "type": "nps",
          "label": "How likely are you to recommend this event?",
          "is_required": true
        }
      ]
    }
  ]
}
```

## Step 23 — Filament service provider and plugin

Create `FilamentFeedbackServiceProvider`.

It should:

- merge config
- publish config
- register resources/pages/widgets according to config
- not register domain migrations
- not contain heavy branching logic
- use `class_exists()` checks if integrating with optional packages

If the monorepo uses Filament plugin classes, create:

```txt
src/Filament/FeedbackPlugin.php
```

The plugin should register resources/pages/widgets.

## Step 24 — Filament resources

Create these resources:

```txt
FeedbackFormResource
FeedbackResponseResource
FeedbackInvitationResource
FeedbackTemplateResource
FeedbackTestimonialResource
```

### Shared resource rules

Every resource must:

- use Filament v5 APIs
- return owner-safe queries from `getEloquentQuery()`
- call core Actions for writes
- validate inbound IDs again inside action handlers
- scope relationship options
- avoid business logic in form/table definitions
- keep action visibility separate from authorization
- define policy checks where appropriate

### `FeedbackFormResource`

Purpose:

- create/edit form
- manage sections
- manage questions
- manage options
- publish/close/archive/duplicate
- view analytics link
- view responses link
- send invitations

Pages:

```txt
ListFeedbackForms
CreateFeedbackForm
EditFeedbackForm
ViewFeedbackForm
ManageFeedbackFormBuilder
FeedbackFormAnalytics
```

Form fields:

```txt
name
slug
purpose
status
visibility
subject_type
subject_id
is_anonymous_allowed
is_anonymity_optional
is_login_required
is_one_response_per_respondent
opens_at
closes_at
settings
```

Important:

- Subject selection must be owner-safe.
- If subject types are configurable, use a registry.
- Do not let user pick arbitrary model class from request.
- Publish/close/archive must call Actions.
- Builder must validate question keys unique per form through Action.

Relation managers:

```txt
SectionsRelationManager
QuestionsRelationManager
ResponsesRelationManager
InvitationsRelationManager
TestimonialsRelationManager
```

### `FeedbackResponseResource`

Purpose:

- view submissions
- view answer details
- review/reject/spam
- export
- extract testimonial

Most fields should be read-only.

Table columns:

```txt
form.name
subject
respondent
status
is_anonymous
score
submitted_at
created_at
```

Filters:

```txt
form
purpose
status
subject type
submitted date
rating range
anonymous
```

Actions:

```txt
View
Review
Reject
Mark Spam
Extract Testimonial
Export
```

Rules:

- If response is anonymous, hide respondent identity unless the current user has explicit permission to view identities.
- Exports must remain owner-scoped.

### `FeedbackInvitationResource`

Purpose:

- send/manage invitation links
- track sent/opened/started/submitted
- cancel invitations

Table columns:

```txt
form.name
recipient
email
phone
status
sent_at
opened_at
started_at
submitted_at
expires_at
```

Actions:

```txt
Send
Copy Link
Cancel
Resend
```

Rules:

- Do not show `token_hash`.
- Copy link must generate or retrieve a safe URL without exposing hash.
- Resend must call core Action.
- Recipient options must be owner-scoped.

### `FeedbackTemplateResource`

Purpose:

- manage reusable templates
- create form from template
- seed/admin edit default templates

Fields:

```txt
name
slug
purpose
category
status
definition
settings
```

Actions:

```txt
Create Form From Template
Publish Template
Archive Template
Duplicate Template
```

Rules:

- If global templates are displayed, it must be explicit and owner-safe.
- Tenant users must not mutate global templates unless authorized and in explicit global context.

### `FeedbackTestimonialResource`

Purpose:

- moderate testimonials
- approve/reject/publish/hide
- filter by subject/respondent/status

Fields:

```txt
quote
display_name
display_title
display_organization
rating
status
permission_given_at
approved_at
published_at
```

Actions:

```txt
Approve
Reject
Publish
Hide
```

Rules:

- Publish only if approved and permission exists.
- Anonymous response must not reveal hidden respondent data.

## Step 25 — Filament analytics dashboard

Create page:

```txt
Filament/Pages/FeedbackDashboard.php
```

Create widgets:

```txt
Widgets/
├── FeedbackOverviewWidget.php
├── FeedbackResponseTrendWidget.php
├── FeedbackAverageRatingWidget.php
├── FeedbackNpsWidget.php
├── FeedbackCsatWidget.php
├── FeedbackRatingDistributionWidget.php
├── FeedbackLatestCommentsWidget.php
├── FeedbackCompletionRateWidget.php
└── FeedbackTestimonialsPendingWidget.php
```

Widget rules:

- All counts, sums, averages, and exists checks must be owner-scoped.
- Widgets must call core analytics services where possible.
- Do not use unscoped model queries.
- If using `DB::table`, apply owner scope intentionally.
- Avoid noisy click tracking.

## Step 26 — Filament exports

Use built-in Filament Export actions only.

Exports:

```txt
FeedbackResponsesExport
FeedbackAnswersExport
FeedbackTestimonialsExport
```

Rules:

- Export query must be owner-scoped.
- Anonymous responses must preserve anonymity unless current user has permission.
- Do not export token hashes.
- Do not export hidden respondent data by default.
- Add tests for export query scoping.

## Step 27 — Documentation

Each package must have:

```txt
docs/01-overview.md
docs/02-installation.md
docs/03-configuration.md
docs/04-usage.md
docs/99-troubleshooting.md
```

Every doc must include YAML frontmatter with `title:`.

### Core docs must cover

`01-overview.md`:

- what feedback package owns
- what it does not own
- model overview
- package boundaries
- owner scoping warning
- event/certificate/engagement integration overview

`02-installation.md`:

- composer installation
- service provider auto-discovery
- publishing config/migrations
- running migrations
- seeding templates
- optional integration setup

`03-configuration.md`:

- database table names
- `json_column_type`
- owner config
- defaults
- feature toggles
- HTTP routes
- cache
- logging

`04-usage.md`:

- create a form
- create from template
- attach to Event/Occurrence/Session
- add questions
- publish form
- send invitation
- submit response
- anonymous response
- NPS/CSAT
- testimonials
- analytics
- listening to domain events

`99-troubleshooting.md`:

- owner context missing
- form not visible
- invitation expired
- one-response-per-respondent blocking submission
- answers fail validation
- analytics count mismatch
- JSON column type mismatch
- Filament adapter not showing resources

### Filament docs must cover

`01-overview.md`:

- adapter-only boundary
- available resources/pages/widgets
- owner scoping warning

`02-installation.md`:

- install after core package
- publish config
- register plugin if required by app convention

`03-configuration.md`:

- navigation
- resource toggles
- feature toggles
- table defaults

`04-usage.md`:

- manage forms
- use builder
- send invitations
- review responses
- moderate testimonials
- dashboard analytics
- export responses

`99-troubleshooting.md`:

- resources not appearing
- policy denial
- owner context missing
- empty widgets
- export missing rows
- question builder validation errors

## Step 28 — Tests for core package

Use Pest. All test runs must include `--parallel`.

Create tests covering:

### Installation / migration

- package loads
- config publishes/merges
- migrations create all tables
- all primary keys are UUID columns
- no DB foreign constraints
- no soft delete columns unless explicitly impossible to avoid

### Model behavior

- each model uses configurable `getTable()`
- casts enum values correctly
- lifecycle transition timestamps are set
- relations work
- application-level delete behavior works

### Owner scoping

- owner-scoped user sees only their forms/responses
- cross-tenant read is blocked or empty
- cross-tenant write throws/aborts
- global template reads are explicit
- aggregates do not leak cross-tenant data

### Forms

- create form
- publish form
- close form
- archive form
- duplicate form
- attach form to a subject model
- copy form from template

### Questions

- create section/question/option
- enforce unique question key per form in Action
- reorder sections/questions/options
- validate question settings
- disabled question types cannot be submitted

### Submission

- submit identified response
- submit anonymous response
- reject anonymous response when not allowed
- require login when configured
- reject closed/unpublished form
- enforce opens_at / closes_at
- enforce one-response-per-respondent
- save typed answer columns
- calculate score
- create response lifecycle timestamp

### Branching

- visible required question validates
- hidden required question does not block
- hidden answer rejected by default
- visibility operators work

### Invitations

- generate invitation with hashed token
- raw token is not stored
- resolve valid token
- reject expired token
- reject cancelled token
- mark invitation submitted after response

### Analytics

- average rating
- rating distribution
- NPS
- CSAT
- completion rate
- latest comments
- owner-scoped analytics

### Testimonials

- extract testimonial only with permission
- approve testimonial
- reject testimonial
- publish testimonial only when approved
- hide testimonial
- anonymous testimonial does not leak respondent

### Domain events

- publish event fires
- response submitted event fires
- testimonial published event fires

## Step 29 — Tests for Filament adapter

Use Filament testing helpers and Pest with `--parallel`.

Cover:

- resources register when enabled
- resources hidden when disabled by config
- `getEloquentQuery()` is owner-safe
- list pages do not show cross-tenant records
- create/edit form calls core Actions
- publish/close/archive actions call core Actions
- subject options are owner-scoped
- response resource hides anonymous respondent identity
- response review/reject/spam actions call core Actions
- invitation resource does not display `token_hash`
- invitation actions call core Actions
- template create-form action calls core Action
- testimonial approve/publish/hide actions call core Actions
- widgets are owner-scoped
- exports are owner-scoped
- policies are respected

## Step 30 — Verification commands

Run only changed package checks.

### Core package

```bash
./vendor/bin/pest --parallel packages/feedback/tests
./vendor/bin/phpstan analyse packages/feedback/src --level=6
./vendor/bin/pint packages/feedback/src packages/feedback/tests packages/feedback/config
rg -n -- "constrained\(|cascadeOnDelete\(" packages/feedback/database packages/feedback/src
rg -n -- "SoftDeletes|softDeletes" packages/feedback
rg -n -- "DB::table\(" packages/feedback/src
rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/feedback/src
rg -n -- "count\(|sum\(|avg\(|exists\(" packages/feedback/src
rg -n -- "config\('" packages/feedback/src packages/feedback/config
```

### Filament adapter

```bash
./vendor/bin/pest --parallel packages/filament-feedback/tests
./vendor/bin/phpstan analyse packages/filament-feedback/src --level=6
./vendor/bin/pint packages/filament-feedback/src packages/filament-feedback/tests packages/filament-feedback/config
rg -n -- "DB::table\(" packages/filament-feedback/src
rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/filament-feedback/src
rg -n -- "count\(|sum\(|avg\(|exists\(" packages/filament-feedback/src
rg -n -- "config\('" packages/filament-feedback/src packages/filament-feedback/config
```

### Cross-package verification

```bash
./vendor/bin/pest --parallel packages/feedback/tests packages/filament-feedback/tests
rg -n -- "constrained\(|cascadeOnDelete\(" packages/feedback packages/filament-feedback
rg -n -- "token_hash" packages/filament-feedback/src
```

If a command cannot be run, document exactly why and provide the exact command the user must run.

## Step 31 — Manual self-review checklist

Before finalizing, review:

```txt
[ ] Read CONTEXT-MAP.md and relevant CONTEXT.md files.
[ ] Created CONTEXT.md for both packages.
[ ] Core package owns domain logic only.
[ ] Filament package is adapter only.
[ ] No soft deletes.
[ ] No database foreign constraints.
[ ] No database cascades.
[ ] All primary keys are UUID.
[ ] All FK columns use foreignUuid.
[ ] JSON columns use config json_column_type.
[ ] Models use HasUuids.
[ ] Models implement getTable() from config.
[ ] Owner-scoped models use commerce-support HasOwner.
[ ] Every query/write path is owner-safe.
[ ] Filament getEloquentQuery is owner-safe.
[ ] Filament action handlers validate IDs server-side.
[ ] Widgets and exports are owner-scoped.
[ ] Token hash is never shown in Filament.
[ ] Raw invitation token is never stored.
[ ] Lifecycle timestamps are explicit.
[ ] Business logic is in Actions.
[ ] Docs exist for both packages.
[ ] Tests cover owner isolation.
[ ] Tests cover submission flow.
[ ] Tests cover invitation token flow.
[ ] Tests cover NPS/CSAT analytics.
[ ] Tests cover testimonial permissions.
[ ] Pest runs use --parallel.
[ ] PHPStan level 6 passes per package.
[ ] Pint ran only on changed package paths.
```

## Step 32 — Suggested implementation order

Use this order to reduce rework:

1. Create package skeletons and composer files.
2. Create `CONTEXT.md` and docs stubs.
3. Add configs.
4. Add enums.
5. Add migrations.
6. Add models and relationships.
7. Add traits and contracts.
8. Add Actions for forms/questions.
9. Add submission and answer normalization.
10. Add invitation token flow.
11. Add analytics/scoring.
12. Add testimonials.
13. Add domain events.
14. Add optional template seeder.
15. Add core tests.
16. Add Filament adapter service provider/plugin.
17. Add Filament resources.
18. Add relation managers.
19. Add widgets/dashboard.
20. Add exports.
21. Add Filament tests.
22. Finish docs.
23. Run verification commands.
24. Self-review using checklist.

## Final architecture summary

The final architecture should feel like this:

```txt
aiarmada/feedback
  owns the truth:
  forms, questions, responses, answers, invitations, templates, testimonials,
  scoring, analytics, events, owner-safe domain actions.

aiarmada/filament-feedback
  owns the admin experience:
  resources, pages, widgets, exports, moderation, dashboards,
  all calling the core package.

aiarmada/events
  can attach feedback to:
  events, occurrences, sessions, speakers, venues.

aiarmada/certificates
  can listen to:
  FeedbackResponseSubmitted.

aiarmada/engagement
  can consume:
  FeedbackTestimonialPublished.
```

Keep the separation clean. Jangan buat package feedback jadi pasar malam: survey ada, certificate ada, likes ada, attendance ada, checkout pun nak masuk sekali. Core mesti power, tapi boundaries mesti tajam.
