<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\Feedback\Data\NpsResultData;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final class NpsCalculator
{
    public function calculate(?FeedbackForm $form = null, ?string $questionKey = null): NpsResultData
    {
        $query = $questionKey !== null
            ? $this->answerQuery($form, $questionKey)
            : $this->baseQuery($form);

        $counts = (clone $query)
            ->selectRaw('
                COUNT(CASE WHEN score >= 9 THEN 1 END) as promoters,
                COUNT(CASE WHEN score BETWEEN 7 AND 8 THEN 1 END) as passives,
                COUNT(CASE WHEN score <= 6 THEN 1 END) as detractors,
                COUNT(*) as total
            ')
            ->first();

        $total = (int) ($counts->total ?? 0);

        if ($total === 0) {
            return new NpsResultData(
                score: null,
                promoterCount: 0,
                passiveCount: 0,
                detractorCount: 0,
                responseCount: 0,
                promoterPercentage: 0.0,
                passivePercentage: 0.0,
                detractorPercentage: 0.0,
            );
        }

        $promoters = (int) ($counts->promoters ?? 0);
        $passives = (int) ($counts->passives ?? 0);
        $detractors = (int) ($counts->detractors ?? 0);

        $promoterPct = ($promoters / $total) * 100;
        $detractorPct = ($detractors / $total) * 100;

        return new NpsResultData(
            score: (int) round($promoterPct - $detractorPct),
            promoterCount: $promoters,
            passiveCount: $passives,
            detractorCount: $detractors,
            responseCount: $total,
            promoterPercentage: round($promoterPct, 2),
            passivePercentage: round(($passives / $total) * 100, 2),
            detractorPercentage: round($detractorPct, 2),
        );
    }

    private function baseQuery(?FeedbackForm $form = null): Builder
    {
        /** @var Builder<FeedbackResponse> $query */
        $query = FeedbackResponse::query()
            ->where('status', 'submitted')
            ->whereNotNull('score');

        if ($form !== null) {
            $query->where('feedback_form_id', $form->id);
        }

        return $query;
    }

    /**
     * @return Builder<FeedbackAnswer>
     */
    private function answerQuery(?FeedbackForm $form, string $questionKey): Builder
    {
        /** @var Builder<FeedbackAnswer> $query */
        $query = FeedbackAnswer::query()->whereNotNull('score');

        $query->whereHas('response', function (Builder $q) use ($form): void {
            $q->where('status', 'submitted');

            if ($form !== null) {
                $q->where('feedback_form_id', $form->id);
            }
        });

        $query->whereHas('question', function (Builder $q) use ($form, $questionKey): void {
            $q->where('key', $questionKey);

            if ($form !== null) {
                $q->where('feedback_form_id', $form->id);
            }
        });

        return $query;
    }
}
