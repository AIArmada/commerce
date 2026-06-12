<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Support\Facades\DB;

final class CalculateFeedbackResponseScoreAction
{
    public function execute(FeedbackResponse $response): void
    {
        $row = DB::table((new FeedbackAnswer)->getTable())
            ->where('feedback_response_id', $response->id)
            ->whereNotNull('score')
            ->selectRaw('COALESCE(SUM(score), 0) as total_score, COALESCE(MAX(score), 0) as max_score_val')
            ->first();

        $response->forceFill([
            'score' => $row !== null ? (float) $row->total_score : null,
            'max_score' => $row !== null ? (float) $row->max_score_val : null,
        ])->save();
    }
}
