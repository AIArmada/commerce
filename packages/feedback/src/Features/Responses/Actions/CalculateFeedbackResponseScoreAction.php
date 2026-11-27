<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Support\ScoreCalculator;

final class CalculateFeedbackResponseScoreAction
{
    public function __construct(
        private readonly ScoreCalculator $scores,
    ) {}

    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        return $response->getConnection()->transaction(function () use ($response): FeedbackResponse {
            if (FeedbackResponse::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);
            }

            $response = FeedbackResponse::query()->whereKey($response->id)->lockForUpdate()->firstOrFail();

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

            return $response;
        });
    }
}
