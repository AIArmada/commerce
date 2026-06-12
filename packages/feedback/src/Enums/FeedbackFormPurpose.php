<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Enums;

use AIArmada\Feedback\Enums\Concerns\HasLabelOptions;

enum FeedbackFormPurpose: string
{
    use HasLabelOptions;

    case General = 'general';
    case PostEventFeedback = 'post_event_feedback';
    case PostOccurrenceFeedback = 'post_occurrence_feedback';
    case PostSessionFeedback = 'post_session_feedback';
    case SpeakerFeedback = 'speaker_feedback';
    case VenueFeedback = 'venue_feedback';
    case TrainingEvaluation = 'training_evaluation';
    case TestimonialCollection = 'testimonial_collection';
    case ProductReview = 'product_review';
    case Complaint = 'complaint';
    case LeadQualification = 'lead_qualification';
    case CustomerSatisfaction = 'customer_satisfaction';
    case Nps = 'nps';
    case Csat = 'csat';
    case PreEventSurvey = 'pre_event_survey';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::PostEventFeedback => 'Post-Event Feedback',
            self::PostOccurrenceFeedback => 'Post-Occurrence Feedback',
            self::PostSessionFeedback => 'Post-Session Feedback',
            self::SpeakerFeedback => 'Speaker Feedback',
            self::VenueFeedback => 'Venue Feedback',
            self::TrainingEvaluation => 'Training Evaluation',
            self::TestimonialCollection => 'Testimonial Collection',
            self::ProductReview => 'Product Review',
            self::Complaint => 'Complaint',
            self::LeadQualification => 'Lead Qualification',
            self::CustomerSatisfaction => 'Customer Satisfaction',
            self::Nps => 'NPS',
            self::Csat => 'CSAT',
            self::PreEventSurvey => 'Pre-Event Survey',
        };
    }
}
