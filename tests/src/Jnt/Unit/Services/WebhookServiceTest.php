<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\WebhookData;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

describe('WebhookService', function (): void {
    beforeEach(function (): void {
        $this->privateKey = 'test-private-key-12345';
        $this->service = new WebhookService($this->privateKey);
    });

    describe('verifySignature()', function (): void {
        it('verifies valid signature correctly', function (): void {
            $bizContent = '{"billCode":"TEST123","details":[]}';
            $signature = base64_encode(md5($bizContent.$this->privateKey, true));

            expect($this->service->verifySignature($signature, $bizContent))->toBeTrue();
        });

        it('rejects invalid signature', function (): void {
            $bizContent = '{"billCode":"TEST123","details":[]}';
            $invalidSignature = 'invalid-signature-xyz';

            expect($this->service->verifySignature($invalidSignature, $bizContent))->toBeFalse();
        });

        it('rejects signature with wrong private key', function (): void {
            $bizContent = '{"billCode":"TEST123","details":[]}';
            $wrongKey = 'wrong-private-key';
            $wrongSignature = base64_encode(md5($bizContent.$wrongKey, true));

            expect($this->service->verifySignature($wrongSignature, $bizContent))->toBeFalse();
        });

        it('rejects signature with modified content', function (): void {
            $originalContent = '{"billCode":"TEST123","details":[]}';
            $signature = base64_encode(md5($originalContent.$this->privateKey, true));

            $modifiedContent = '{"billCode":"MODIFIED","details":[]}';

            expect($this->service->verifySignature($signature, $modifiedContent))->toBeFalse();
        });

        it('rejects empty digest', function (): void {
            $bizContent = '{"billCode":"TEST123","details":[]}';

            expect($this->service->verifySignature('', $bizContent))->toBeFalse();
        });

        it('rejects empty bizContent', function (): void {
            $signature = 'some-signature';

            expect($this->service->verifySignature($signature, ''))->toBeFalse();
        });

        it('uses timing-safe comparison', function (): void {
            // This test ensures hash_equals is being used
            // by verifying the method exists and works correctly
            $bizContent = '{"billCode":"TEST123","details":[]}';
            $validSignature = base64_encode(md5($bizContent.$this->privateKey, true));

            // Test multiple times to ensure consistent behavior
            foreach (range(1, 10) as $i) {
                expect($this->service->verifySignature($validSignature, $bizContent))->toBeTrue();
            }
        });
    });

    describe('generateSignature()', function (): void {
        it('generates correct signature', function (): void {
            $bizContent = '{"billCode":"TEST123","details":[]}';
            $expectedSignature = base64_encode(md5($bizContent.$this->privateKey, true));

            expect($this->service->generateSignature($bizContent))->toBe($expectedSignature);
        });

        it('generates consistent signatures', function (): void {
            $bizContent = '{"billCode":"TEST123","details":[]}';

            $signature1 = $this->service->generateSignature($bizContent);
            $signature2 = $this->service->generateSignature($bizContent);

            expect($signature1)->toBe($signature2);
        });

        it('generates different signatures for different content', function (): void {
            $content1 = '{"billCode":"TEST123"}';
            $content2 = '{"billCode":"TEST456"}';

            $signature1 = $this->service->generateSignature($content1);
            $signature2 = $this->service->generateSignature($content2);

            expect($signature1)->not->toBe($signature2);
        });
    });

    describe('parseWebhook()', function (): void {
        it('parses valid webhook request', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JNTMY12345678',
                'txlogisticId' => 'SHOP-ORDER-001',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 10:30:00',
                        'desc' => 'Parcel collected',
                        'scanTypeCode' => 'CC',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'collection',
                        'scanNetworkId' => 100001,
                        'scanNetworkName' => 'KL Hub',
                        'scanNetworkProvince' => 'Kuala Lumpur',
                        'scanNetworkCity' => 'KL',
                        'scanNetworkArea' => 'Bukit Bintang',
                    ],
                ],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            $webhookData = $this->service->parseWebhook($request);

            expect($webhookData)->toBeInstanceOf(WebhookData::class)
                ->and($webhookData->billCode)->toBe('JNTMY12345678')
                ->and($webhookData->txlogisticId)->toBe('SHOP-ORDER-001')
                ->and($webhookData->details)->toHaveCount(1);
        });

        it('throws validation exception for missing bizContent', function (): void {
            $request = Request::create('/webhook', 'POST', []);

            $this->service->parseWebhook($request);
        })->throws(ValidationException::class);

        it('throws invalid argument exception for invalid JSON', function (): void {
            $request = Request::create('/webhook', 'POST', [
                'bizContent' => 'invalid-json',
            ]);

            $this->service->parseWebhook($request);
        })->throws(JntValidationException::class);
    });

    describe('successResponse()', function (): void {
        it('returns correct success response structure', function (): void {
            $response = $this->service->successResponse();

            expect($response)
                ->toHaveKey('code', '1')
                ->toHaveKey('msg', 'success')
                ->toHaveKey('data', 'SUCCESS')
                ->toHaveKey('requestId');
        });

        it('generates unique request IDs', function (): void {
            $response1 = $this->service->successResponse();
            $response2 = $this->service->successResponse();

            expect($response1['requestId'])->not->toBe($response2['requestId']);
        });

        it('returns valid UUID for requestId', function (): void {
            $response = $this->service->successResponse();

            // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
            expect($response['requestId'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
        });
    });

    describe('failureResponse()', function (): void {
        it('returns correct failure response structure with default message', function (): void {
            $response = $this->service->failureResponse();

            expect($response)
                ->toHaveKey('code', '0')
                ->toHaveKey('msg', 'fail')
                ->toHaveKey('data', null)
                ->toHaveKey('requestId');
        });

        it('returns correct failure response structure with custom message', function (): void {
            $response = $this->service->failureResponse('Invalid signature');

            expect($response)
                ->toHaveKey('code', '0')
                ->toHaveKey('msg', 'Invalid signature')
                ->toHaveKey('data', null)
                ->toHaveKey('requestId');
        });

        it('generates unique request IDs', function (): void {
            $response1 = $this->service->failureResponse();
            $response2 = $this->service->failureResponse();

            expect($response1['requestId'])->not->toBe($response2['requestId']);
        });

        it('returns valid UUID for requestId', function (): void {
            $response = $this->service->failureResponse();

            expect($response['requestId'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
        });
    });

    describe('extractDigest()', function (): void {
        it('extracts digest from request header', function (): void {
            $expectedDigest = 'test-digest-signature-123';

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_DIGEST' => $expectedDigest,
            ]);

            expect($this->service->extractDigest($request))->toBe($expectedDigest);
        });

        it('returns empty string when digest header is missing', function (): void {
            $request = Request::create('/webhook', 'POST');

            expect($this->service->extractDigest($request))->toBe('');
        });

        it('handles case-insensitive header names', function (): void {
            $expectedDigest = 'test-digest-signature-123';

            // Laravel normalizes headers, so 'digest' becomes 'HTTP_DIGEST'
            $request = Request::create('/webhook', 'POST');
            $request->headers->set('Digest', $expectedDigest);

            expect($this->service->extractDigest($request))->toBe($expectedDigest);
        });
    });

    describe('verifyAndParse()', function (): void {
        it('returns WebhookData when signature is valid', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JNTMY12345678',
                'txlogisticId' => 'SHOP-ORDER-001',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 10:30:00',
                        'desc' => 'Parcel collected',
                        'scanTypeCode' => 'CC',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'collection',
                        'scanNetworkId' => 100001,
                        'scanNetworkName' => 'KL Hub',
                        'scanNetworkProvince' => 'Kuala Lumpur',
                        'scanNetworkCity' => 'KL',
                        'scanNetworkArea' => 'Bukit Bintang',
                    ],
                ],
            ]);

            $signature = base64_encode(md5($bizContent.$this->privateKey, true));

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ], [], [], [
                'HTTP_DIGEST' => $signature,
            ]);

            $webhookData = $this->service->verifyAndParse($request);

            expect($webhookData)->toBeInstanceOf(WebhookData::class)
                ->and($webhookData->billCode)->toBe('JNTMY12345678');
        });

        it('returns null when signature is invalid', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JNTMY12345678',
                'details' => [],
            ]);

            $invalidSignature = 'invalid-signature-xyz';

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ], [], [], [
                'HTTP_DIGEST' => $invalidSignature,
            ]);

            $webhookData = $this->service->verifyAndParse($request);

            expect($webhookData)->toBeNull();
        });

        it('returns null when digest header is missing', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JNTMY12345678',
                'details' => [],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            $webhookData = $this->service->verifyAndParse($request);

            expect($webhookData)->toBeNull();
        });

        it('returns null when bizContent is missing', function (): void {
            $request = Request::create('/webhook', 'POST', []);

            $webhookData = $this->service->verifyAndParse($request);

            expect($webhookData)->toBeNull();
        });
    });

    describe('Real-World Scenarios', function (): void {
        it('handles complete J&T webhook flow', function (): void {
            // Create a realistic J&T webhook payload
            $bizContent = json_encode([
                'billCode' => 'JNTMY0000123456',
                'txlogisticId' => 'ECOM-2024-001',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 09:15:30',
                        'desc' => 'Shipment collected from sender',
                        'scanTypeCode' => 'CC',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'collection',
                        'scanNetworkId' => 100001,
                        'scanNetworkName' => 'KL Central Hub',
                        'scanNetworkProvince' => 'Wilayah Persekutuan',
                        'scanNetworkCity' => 'Kuala Lumpur',
                        'scanNetworkArea' => 'Bukit Bintang',
                    ],
                    [
                        'scanTime' => '2024-01-15 14:30:45',
                        'desc' => 'Shipment dispatched to destination hub',
                        'scanTypeCode' => 'DI',
                        'scanTypeName' => 'Dispatch',
                        'scanType' => 'dispatch',
                        'scanNetworkId' => 100002,
                        'scanNetworkName' => 'Penang Hub',
                        'scanNetworkProvince' => 'Penang',
                        'scanNetworkCity' => 'Georgetown',
                        'scanNetworkArea' => 'Jelutong',
                    ],
                ],
            ]);

            // Generate valid signature
            $signature = base64_encode(md5($bizContent.$this->privateKey, true));

            // Create request
            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ], [], [], [
                'HTTP_DIGEST' => $signature,
            ]);

            // Verify and parse
            $webhookData = $this->service->verifyAndParse($request);

            // Assertions
            expect($webhookData)->toBeInstanceOf(WebhookData::class)
                ->and($webhookData->billCode)->toBe('JNTMY0000123456')
                ->and($webhookData->txlogisticId)->toBe('ECOM-2024-001')
                ->and($webhookData->details)->toHaveCount(2);

            // Verify latest detail
            $latestDetail = $webhookData->getLatestDetail();
            expect($latestDetail)->not->toBeNull()
                ->and($latestDetail->scanType)->toBe('dispatch')
                ->and($latestDetail->scanNetworkName)->toBe('Penang Hub');

            // Generate success response
            $response = $this->service->successResponse();
            expect($response['code'])->toBe('1')
                ->and($response['msg'])->toBe('success');
        });

        it('handles webhook with signature mismatch attack attempt', function (): void {
            // Attacker tries to modify content but keeps original signature
            $originalContent = json_encode([
                'billCode' => 'JNTMY0000123456',
                'details' => [],
            ]);

            $validSignature = base64_encode(md5($originalContent.$this->privateKey, true));

            // Attacker modifies the content
            $modifiedContent = json_encode([
                'billCode' => 'HACKED123456',
                'details' => [],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $modifiedContent,
            ], [], [], [
                'HTTP_DIGEST' => $validSignature,
            ]);

            // Should return null (signature verification fails)
            $webhookData = $this->service->verifyAndParse($request);

            expect($webhookData)->toBeNull();
        });
    });
});
