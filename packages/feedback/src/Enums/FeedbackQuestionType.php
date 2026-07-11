<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum FeedbackQuestionType: string
{
    use HasLabelOptions;

    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Dropdown = 'dropdown';
    case Rating = 'rating';
    case StarRating = 'star_rating';
    case Scale = 'scale';
    case Nps = 'nps';
    case Csat = 'csat';
    case YesNo = 'yes_no';
    case Boolean = 'boolean';
    case Matrix = 'matrix';
    case Likert = 'likert';
    case Ranking = 'ranking';
    case FileUpload = 'file_upload';
    case Signature = 'signature';
    case Statement = 'statement';
    case Divider = 'divider';
    case Heading = 'heading';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short Text',
            self::LongText => 'Long Text',
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Time => 'Time',
            self::DateTime => 'Date/Time',
            self::SingleChoice => 'Single Choice',
            self::MultipleChoice => 'Multiple Choice',
            self::Dropdown => 'Dropdown',
            self::Rating => 'Rating',
            self::StarRating => 'Star Rating',
            self::Scale => 'Scale',
            self::Nps => 'NPS',
            self::Csat => 'CSAT',
            self::YesNo => 'Yes/No',
            self::Boolean => 'Boolean',
            self::Matrix => 'Matrix',
            self::Likert => 'Likert',
            self::Ranking => 'Ranking',
            self::FileUpload => 'File Upload',
            self::Signature => 'Signature',
            self::Statement => 'Statement',
            self::Divider => 'Divider',
            self::Heading => 'Heading',
        };
    }

    public function isInputType(): bool
    {
        return in_array($this, [
            self::ShortText,
            self::LongText,
            self::Email,
            self::Phone,
            self::Number,
            self::Date,
            self::Time,
            self::DateTime,
        ], true);
    }

    public function isChoiceType(): bool
    {
        return in_array($this, [
            self::SingleChoice,
            self::MultipleChoice,
            self::Dropdown,
            self::YesNo,
            self::Boolean,
            self::Matrix,
            self::Likert,
            self::Ranking,
        ], true);
    }

    public function isScoredType(): bool
    {
        return in_array($this, [
            self::Rating,
            self::StarRating,
            self::Scale,
            self::Nps,
            self::Csat,
        ], true);
    }

    public function isDisplayOnly(): bool
    {
        return in_array($this, [
            self::Statement,
            self::Divider,
            self::Heading,
        ], true);
    }

    public function isDisabled(): bool
    {
        return in_array($this, [
            self::FileUpload,
            self::Signature,
        ], true);
    }
}
