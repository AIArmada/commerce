<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Support\Facades\DB;

final class CalculateFeedbackResponseScoreAction
{
    public function execute(FeedbackResponse $response): void
    {
        $response = OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);
        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        $query = DB::table((new FeedbackAnswer)->getTable());
        $query = OwnerQuery::applyToQueryBuilder($query, $owner);

        $row = $query
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
