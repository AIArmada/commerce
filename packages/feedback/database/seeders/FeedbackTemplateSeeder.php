<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Database\Seeders;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Enums\FeedbackTemplateStatus;
use AIArmada\Feedback\Models\FeedbackTemplate;
use Illuminate\Database\Seeder;

final class FeedbackTemplateSeeder extends Seeder
{
    public function run(): void
    {
        OwnerContext::withOwner(null, function (): void {
            $templates = $this->defaultTemplates();

            foreach ($templates as $data) {
                FeedbackTemplate::firstOrCreate(
                    ['slug' => $data['slug']],
                    $data,
                );
            }
        });
    }

    private function defaultTemplates(): array
    {
        return [
            [
                'name' => 'Post Event Feedback',
                'slug' => 'post-event-feedback',
                'purpose' => 'post_event_feedback',
                'category' => 'event',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'event_experience',
                            'title' => 'Event Experience',
                            'questions' => [
                                ['key' => 'overall_rating', 'type' => 'rating', 'label' => 'Overall, how would you rate this event?', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'recommend_score', 'type' => 'nps', 'label' => 'How likely are you to recommend this event?', 'is_required' => true],
                                ['key' => 'highlights', 'type' => 'long_text', 'label' => 'What were your favorite parts?', 'is_required' => false],
                                ['key' => 'improvements', 'type' => 'long_text', 'label' => 'What could be improved?', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Post Session Feedback',
                'slug' => 'session-feedback',
                'purpose' => 'post_session_feedback',
                'category' => 'event',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'session_content',
                            'title' => 'Session Content',
                            'questions' => [
                                ['key' => 'content_rating', 'type' => 'rating', 'label' => 'How would you rate the session content?', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'presentation_rating', 'type' => 'rating', 'label' => 'How would you rate the presentation?', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'key_takeaways', 'type' => 'long_text', 'label' => 'What are your key takeaways?', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Speaker Feedback',
                'slug' => 'speaker-feedback',
                'purpose' => 'speaker_feedback',
                'category' => 'event',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'speaker_rating',
                            'title' => 'Speaker Rating',
                            'questions' => [
                                ['key' => 'knowledge_rating', 'type' => 'rating', 'label' => 'Knowledge of subject', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'clarity_rating', 'type' => 'rating', 'label' => 'Clarity of presentation', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'engagement_rating', 'type' => 'rating', 'label' => 'Engagement with audience', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'comments', 'type' => 'long_text', 'label' => 'Additional comments', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Venue Feedback',
                'slug' => 'venue-feedback',
                'purpose' => 'venue_feedback',
                'category' => 'event',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'venue_rating',
                            'title' => 'Venue Rating',
                            'questions' => [
                                ['key' => 'location_rating', 'type' => 'rating', 'label' => 'Location convenience', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'facilities_rating', 'type' => 'rating', 'label' => 'Facilities quality', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'accessibility_rating', 'type' => 'rating', 'label' => 'Accessibility', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'suggestions', 'type' => 'long_text', 'label' => 'Suggestions for improvement', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Training Evaluation',
                'slug' => 'training-evaluation',
                'purpose' => 'training_evaluation',
                'category' => 'training',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'training_content',
                            'title' => 'Training Content',
                            'questions' => [
                                ['key' => 'content_relevance', 'type' => 'rating', 'label' => 'How relevant was the content?', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'trainer_effectiveness', 'type' => 'rating', 'label' => 'Trainer effectiveness', 'is_required' => true, 'settings' => ['min' => 1, 'max' => 5]],
                                ['key' => 'apply_skills', 'type' => 'yes_no', 'label' => 'Can you apply what you learned?', 'is_required' => true],
                                ['key' => 'nps_score', 'type' => 'nps', 'label' => 'How likely to recommend this training?', 'is_required' => true],
                                ['key' => 'feedback', 'type' => 'long_text', 'label' => 'Additional feedback', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'NPS Survey',
                'slug' => 'nps-survey',
                'purpose' => 'nps',
                'category' => 'satisfaction',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'nps',
                            'title' => 'How likely are you to recommend us?',
                            'questions' => [
                                ['key' => 'nps_score', 'type' => 'nps', 'label' => 'On a scale of 0-10, how likely are you to recommend us?', 'is_required' => true],
                                ['key' => 'reason', 'type' => 'long_text', 'label' => 'What is the primary reason for your score?', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'CSAT Survey',
                'slug' => 'csat-survey',
                'purpose' => 'csat',
                'category' => 'satisfaction',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'satisfaction',
                            'title' => 'Satisfaction',
                            'questions' => [
                                ['key' => 'csat_score', 'type' => 'csat', 'label' => 'How satisfied are you with our service?', 'is_required' => true],
                                ['key' => 'feedback', 'type' => 'long_text', 'label' => 'Tell us more', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Testimonial Request',
                'slug' => 'testimonial-request',
                'purpose' => 'testimonial_collection',
                'category' => 'marketing',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'testimonial',
                            'title' => 'Share Your Experience',
                            'questions' => [
                                ['key' => 'rating', 'type' => 'star_rating', 'label' => 'Your rating', 'is_required' => true, 'settings' => ['max' => 5]],
                                ['key' => 'testimonial_text', 'type' => 'long_text', 'label' => 'What has been your experience?', 'is_required' => true],
                                ['key' => 'permission_given', 'type' => 'boolean', 'label' => 'I give permission to publish my testimonial', 'is_required' => true],
                                ['key' => 'display_name', 'type' => 'short_text', 'label' => 'Name to display', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Complaint Form',
                'slug' => 'complaint-form',
                'purpose' => 'complaint',
                'category' => 'support',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'complaint_details',
                            'title' => 'Complaint Details',
                            'questions' => [
                                ['key' => 'subject', 'type' => 'short_text', 'label' => 'Subject', 'is_required' => true],
                                ['key' => 'description', 'type' => 'long_text', 'label' => 'Describe your issue', 'is_required' => true],
                                ['key' => 'urgency', 'type' => 'single_choice', 'label' => 'Urgency', 'is_required' => true, 'options' => [['label' => 'Low', 'value' => 'low'], ['label' => 'Medium', 'value' => 'medium'], ['label' => 'High', 'value' => 'high']]],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Product Review',
                'slug' => 'product-review',
                'purpose' => 'product_review',
                'category' => 'marketing',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'review',
                            'title' => 'Product Review',
                            'questions' => [
                                ['key' => 'rating', 'type' => 'star_rating', 'label' => 'Your rating', 'is_required' => true, 'settings' => ['max' => 5]],
                                ['key' => 'review_title', 'type' => 'short_text', 'label' => 'Review title', 'is_required' => false],
                                ['key' => 'review_text', 'type' => 'long_text', 'label' => 'Your review', 'is_required' => true],
                                ['key' => 'recommend', 'type' => 'yes_no', 'label' => 'Would you recommend this product?', 'is_required' => true],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Lead Qualification',
                'slug' => 'lead-qualification',
                'purpose' => 'lead_qualification',
                'category' => 'sales',
                'status' => FeedbackTemplateStatus::Published,
                'definition' => [
                    'sections' => [
                        [
                            'key' => 'qualification',
                            'title' => 'Qualification',
                            'questions' => [
                                ['key' => 'interest_level', 'type' => 'single_choice', 'label' => 'Interest level', 'is_required' => true, 'options' => [['label' => 'Just browsing', 'value' => 'browsing'], ['label' => 'Considering', 'value' => 'considering'], ['label' => 'Ready to buy', 'value' => 'ready']]],
                                ['key' => 'budget_range', 'type' => 'single_choice', 'label' => 'Budget range', 'is_required' => false, 'options' => [['label' => 'Under $1,000', 'value' => 'low'], ['label' => '$1,000-$5,000', 'value' => 'medium'], ['label' => 'Over $5,000', 'value' => 'high']]],
                                ['key' => 'timeline', 'type' => 'single_choice', 'label' => 'Purchase timeline', 'is_required' => false, 'options' => [['label' => 'Immediately', 'value' => 'immediate'], ['label' => '1-3 months', 'value' => 'short'], ['label' => '3-6 months', 'value' => 'medium'], ['label' => 'Not sure', 'value' => 'unknown']]],
                                ['key' => 'notes', 'type' => 'long_text', 'label' => 'Additional notes', 'is_required' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
