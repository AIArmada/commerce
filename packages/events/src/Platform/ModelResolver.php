<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ModelResolver
{
    private const array KEYS = [
        'event',
        'occurrence',
        'session',
        'registration',
        'registration_participant',
        'registration_question',
        'attendance',
        'submission',
        'change_log',
        'update',
        'escalation',
        'search_document',
        'walk_in',
        'headcount_log',
        'approval_request',
        'management_assignment',
        'venue',
        'venue_space',
        'location',
        'facility',
        'classification',
        'attribute',
        'audience',
    ];

    /**
     * @return class-string<Model>
     */
    public static function eventClass(): string
    {
        return self::resolve('event');
    }

    /**
     * @return class-string<Model>
     */
    public static function occurrenceClass(): string
    {
        return self::resolve('occurrence');
    }

    /**
     * @return class-string<Model>
     */
    public static function sessionClass(): string
    {
        return self::resolve('session');
    }

    /**
     * @return class-string<Model>
     */
    public static function registrationClass(): string
    {
        return self::resolve('registration');
    }

    /**
     * @return class-string<Model>
     */
    public static function registrationParticipantClass(): string
    {
        return self::resolve('registration_participant');
    }

    /**
     * @return class-string<Model>
     */
    public static function registrationQuestionClass(): string
    {
        return self::resolve('registration_question');
    }

    /**
     * @return class-string<Model>
     */
    public static function attendanceClass(): string
    {
        return self::resolve('attendance');
    }

    /**
     * @return class-string<Model>
     */
    public static function submissionClass(): string
    {
        return self::resolve('submission');
    }

    /**
     * @return class-string<Model>
     */
    public static function changeLogClass(): string
    {
        return self::resolve('change_log');
    }

    /**
     * @return class-string<Model>
     */
    public static function updateClass(): string
    {
        return self::resolve('update');
    }

    /**
     * @return class-string<Model>
     */
    public static function escalationClass(): string
    {
        return self::resolve('escalation');
    }

    /**
     * @return class-string<Model>
     */
    public static function searchDocumentClass(): string
    {
        return self::resolve('search_document');
    }

    /**
     * @return class-string<Model>
     */
    public static function walkInClass(): string
    {
        return self::resolve('walk_in');
    }

    /**
     * @return class-string<Model>
     */
    public static function headcountLogClass(): string
    {
        return self::resolve('headcount_log');
    }

    /**
     * @return class-string<Model>
     */
    public static function approvalRequestClass(): string
    {
        return self::resolve('approval_request');
    }

    /**
     * @return class-string<Model>
     */
    public static function managementAssignmentClass(): string
    {
        return self::resolve('management_assignment');
    }

    /**
     * @return class-string<Model>
     */
    public static function venueClass(): string
    {
        return self::resolve('venue');
    }

    /**
     * @return class-string<Model>
     */
    public static function venueSpaceClass(): string
    {
        return self::resolve('venue_space');
    }

    /**
     * @return class-string<Model>
     */
    public static function locationClass(): string
    {
        return self::resolve('location');
    }

    /**
     * @return class-string<Model>
     */
    public static function facilityClass(): string
    {
        return self::resolve('facility');
    }

    /**
     * @return class-string<Model>
     */
    public static function classificationClass(): string
    {
        return self::resolve('classification');
    }

    /**
     * @return class-string<Model>
     */
    public static function attributeClass(): string
    {
        return self::resolve('attribute');
    }

    /**
     * @return class-string<Model>
     */
    public static function audienceClass(): string
    {
        return self::resolve('audience');
    }

    /**
     * @return class-string<Model>
     */
    public static function resolve(string $key): string
    {
        if (! in_array($key, self::KEYS, true)) {
            throw new RuntimeException(sprintf(
                'Unknown events model key [%s]. Supported keys: %s.',
                $key,
                implode(', ', self::KEYS),
            ));
        }

        $model = config('events.models.' . $key);

        if (! is_string($model) || $model === '' || ! is_a($model, Model::class, true)) {
            throw new RuntimeException(sprintf(
                'The events model [%s] is not configured. Set events.models.%s to an Eloquent model class.',
                $key,
                $key,
            ));
        }

        $binding = 'events.model-type.' . $key;
        $required = app()->bound($binding) ? app()->make($binding) : Model::class;

        if (! is_string($required) || ! is_a($required, Model::class, true)) {
            throw new RuntimeException(sprintf('Invalid model type constraint for events model [%s].', $key));
        }

        if (! is_a($model, $required, true)) {
            throw new RuntimeException(sprintf(
                'The events model [%s] must be %s or a supported subclass; got %s.',
                $key,
                $required,
                $model,
            ));
        }

        return $model;
    }
}
