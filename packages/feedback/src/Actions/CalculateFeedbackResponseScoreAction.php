<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Support\ScoreCalculator;

final class CalculateFeedbackResponseScoreAction
{
    public function __construct(
        private readonly ScoreCalculator $scores,
    ) {}

    public function execute(FeedbackResponse $response): void
    {
        $response = OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);

        $answers = FeedbackAnswer::query()
            ->where('feedback_response_id', $response->id)
            ->whereNotNull('score')
            ->with('question.options')
            ->get();

        $totalScore = 0.0;
        $maxScore = 0.0;
        $seenQuestions = [];

        foreach ($answers as $answer) {
            $totalScore += (float) $answer->score;

            $question = $answer->question;

            if ($question === null || isset($seenQuestions[$question->id])) {
                continue;
            }

            $seenQuestions[$question->id] = true;
            $maxScore += $this->scores->calculateMaxScore($question) ?? 0.0;
        }

        $response->forceFill([
            'score' => $totalScore,
            'max_score' => $maxScore,
        ])->save();
    }
}
