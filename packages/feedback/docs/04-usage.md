---
title: Usage
---

# Usage

## Create a form

```php
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\CommerceSupport\Support\OwnerContext;

$form = OwnerContext::withOwner($tenant, fn () =>
    app(CreateFeedbackFormAction::class)->execute(
        new CreateFeedbackFormData(
            name: 'Post-Event Feedback',
            purpose: 'post_event_feedback',
        )
    )
);
```

## Create from template

```php
use AIArmada\Feedback\Actions\CreateFeedbackFormFromTemplateAction;

$template = FeedbackTemplate::where('slug', 'post-event-feedback')->first();
$form = app(CreateFeedbackFormFromTemplateAction::class)->execute($template, [
    'subject_type' => $event->getMorphClass(),
    'subject_id' => $event->getKey(),
]);
```

## Attach to an event

Use the `ReceivesFeedback` trait on your model:

```php
use AIArmada\Feedback\Traits\ReceivesFeedback;

class Event extends Model
{
    use ReceivesFeedback;
}

$event->feedbackForms(); // MorphMany
$event->createFeedbackFormFromTemplate('post-event-feedback');
```

## Add questions and sections

```php
use AIArmada\Feedback\Actions\SaveFeedbackFormStructureAction;

app(SaveFeedbackFormStructureAction::class)->saveQuestion(
    formId: $form->id,
    data: [
        'key' => 'overall_rating',
        'type' => 'rating',
        'label' => 'Overall rating',
        'is_required' => true,
        'settings' => ['min' => 1, 'max' => 5],
    ],
);
```

## Publish form

```php
use AIArmada\Feedback\Actions\PublishFeedbackFormAction;

app(PublishFeedbackFormAction::class)->execute($form);
```

## Send invitation

```php
use AIArmada\Feedback\Actions\SendFeedbackInvitationAction;

$result = app(SendFeedbackInvitationAction::class)->execute(
    form: $form,
    email: 'user@example.com',
);

$url = $result['url']; // Send this to the user
```

The raw invitation token is only available when the invitation is created. Store or
send the returned URL immediately; only its hash is persisted.

## Submit response

```php
use AIArmada\Feedback\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;

$response = app(SubmitFeedbackResponseAction::class)->execute(
    new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: collect([
            new SubmittedAnswerData(
                questionId: $question->id,
                questionKey: 'overall_rating',
                value: 5,
            ),
        ]),
    )
);
```

When the form enables one response per respondent, repeating the same submission
returns the existing submitted response.

Starting the same response again while it is still a draft returns that draft.
When one-response mode is disabled, the draft is reused until submission and
later submissions create additional submitted responses.

## Anonymous response

```php
$response = app(SubmitFeedbackResponseAction::class)->execute(
    new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: collect([...]),
        isAnonymous: true,
    )
);
```

## NPS / CSAT

```php
use AIArmada\Feedback\Analytics\NpsCalculator;
use AIArmada\Feedback\Analytics\CsatCalculator;

$nps = app(NpsCalculator::class)->calculate($form);
$csat = app(CsatCalculator::class)->calculate($form);
```

## Testimonials

```php
use AIArmada\Feedback\Actions\ApproveFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\PublishFeedbackTestimonialAction;

app(ApproveFeedbackTestimonialAction::class)->execute($testimonial);
app(PublishFeedbackTestimonialAction::class)->execute($testimonial);
```

## Listening to domain events

```php
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;

Event::listen(FeedbackResponseSubmitted::class, function ($event) {
    // Issue certificate, update metrics, etc.
});
```

## Form structure path

`SaveFeedbackFormStructureAction` is the single owner-guarded structure path (`saveSection` / `saveQuestion` / `saveOption`, each via `OwnerWriteGuard::findOrFailForOwner`). Pass the parent id every time so cross-form attaches fail fast:

```php
use AIArmada\Feedback\Actions\SaveFeedbackFormStructureAction;

app(SaveFeedbackFormStructureAction::class)->saveSection(formId: $form->id, data: ['title' => 'Basics']);
app(SaveFeedbackFormStructureAction::class)->saveOption(questionId: $question->id, data: ['label' => 'Yes', 'value' => 'yes']);
```

## Invitation tokens

`SendFeedbackInvitationAction` mints `bin2hex(random_bytes(32))` and persists only `hash('sha256', $rawToken)`. Resolve via `ResolveFeedbackInvitationTokenAction`, which does the hashed lookup plus expiry, single-use (`submitted` rejects reuse), and rate-limit (`feedback.security.invitation_rate_limit`):

```php
use AIArmada\Feedback\Actions\ResolveFeedbackInvitationTokenAction;

$invitation = app(ResolveFeedbackInvitationTokenAction::class)->execute($rawToken);
```

## Analytics aggregates

`summaryForForm()` reads the `feedback_form_analytics` aggregate row and falls back to live calculation. The dashboard is owner-keyed via `OwnerCache::remember(..., config('feedback.analytics.dashboard_cache_ttl', 30))`, and submissions queue `RecalculateFeedbackFormAnalyticsJob::forResponse($response)` to refresh the aggregate:

```php
use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;

$data = app(FeedbackAnalyticsService::class)->summaryForForm($form);
$dashboard = app(FeedbackAnalyticsService::class)->dashboard();
```
