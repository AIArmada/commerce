---
title: Usage
---

# Usage

## Create a form

```php
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;

$form = app(CreateFeedbackFormAction::class)->execute(
    new CreateFeedbackFormData(
        name: 'Post-Event Feedback',
        purpose: 'post_event_feedback',
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

## Add questions

```php
use AIArmada\Feedback\Actions\CreateFeedbackQuestionAction;

app(CreateFeedbackQuestionAction::class)->execute(
    formId: $form->id,
    key: 'overall_rating',
    type: 'rating',
    label: 'Overall rating',
    isRequired: true,
    settings: ['min' => 1, 'max' => 5],
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
