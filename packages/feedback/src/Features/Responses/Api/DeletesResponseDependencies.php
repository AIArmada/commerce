<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

interface DeletesResponseDependencies
{
    public function deleteForForm(string $formId): void;

    public function deleteAnswersForQuestion(string $questionId): void;
}
