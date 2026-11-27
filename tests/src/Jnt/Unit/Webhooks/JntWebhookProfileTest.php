<?php

declare(strict_types=1);

use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Features\Tracking\EntryPoints\Webhooks\JntWebhookProfile;
use Illuminate\Http\Request;

describe('JntWebhookProfile', function (): void {
    it('should not process when bizContent is missing', function (): void {
        $request = Request::create('/webhook', 'POST', []);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should not process when bizContent is empty', function (): void {
        $request = Request::create('/webhook', 'POST', ['bizContent' => '']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should process when bizContent is valid', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY12345678',
                'details' => [],
            ]),
        ]);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('throws when bizContent is invalid JSON', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => 'not-json',
        ]);

        $profile = new JntWebhookProfile;

        $profile->shouldProcess($request);
    })->throws(JntValidationException::class);

    it('throws when billCode is missing', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => json_encode([
                'details' => [],
            ]),
        ]);

        $profile = new JntWebhookProfile;

        $profile->shouldProcess($request);
    })->throws(JntValidationException::class);

    it('enforces byte limits for multibyte payloads', function (): void {
        $content = json_encode(['billCode' => 'JNT123', 'details' => [], 'pad' => '收件'], JSON_UNESCAPED_UNICODE);
        config()->set('jnt.webhooks.max_biz_content_bytes', mb_strlen($content));
        $request = Request::create('/webhook', 'POST', ['bizContent' => $content]);

        expect(fn () => (new JntWebhookProfile)->shouldProcess($request))->toThrow(JntValidationException::class);
    });

    it('rejects malformed identifiers before queueing', function (): void {
        $request = Request::create('/webhook', 'POST', ['bizContent' => json_encode(['billCode' => ['invalid'], 'details' => []])]);

        expect(fn () => (new JntWebhookProfile)->shouldProcess($request))->toThrow(JntValidationException::class);
    });

    it('throws when details is not an array', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY12345678',
                'details' => 'not-array',
            ]),
        ]);

        $profile = new JntWebhookProfile;

        $profile->shouldProcess($request);
    })->throws(JntValidationException::class);
    it('rejects blank tracking identifiers and non-object tracking details', function (array $payload): void {
        $request = Request::create('/webhook', 'POST', ['bizContent' => json_encode($payload)]);

        expect(fn () => (new JntWebhookProfile)->shouldProcess($request))->toThrow(JntValidationException::class);
    })->with([
        'blank identifier' => [['billCode' => '   ', 'details' => []]],
        'scalar detail' => [['billCode' => 'JNT123', 'details' => ['malformed']]],
        'list detail' => [['billCode' => 'JNT123', 'details' => [['malformed']]]],
    ]);
});
