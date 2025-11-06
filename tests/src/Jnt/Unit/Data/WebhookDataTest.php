<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Data\WebhookData;
use AIArmada\Jnt\Exceptions\JntValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

describe('WebhookData', function (): void {
    describe('fromRequest', function (): void {
        it('parses valid webhook payload', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JT001',
                'txlogisticId' => 'ORDER123',
                'details' => [
                    [
                        'scanType' => '收件',
                        'scanTime' => '2024-01-15 10:30:00',
                        'desc' => 'Package collected',
                        'scanTypeCode' => '1',
                        'scanTypeName' => 'Collection',
                        'scanNetworkId' => '1',
                        'scanNetworkName' => 'Kuala Lumpur Hub',
                        'scanNetworkCity' => 'Kuala Lumpur',
                        'scanNetworkProvince' => 'Wilayah Persekutuan',
                    ],
                ],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'digest' => 'test_signature',
                'bizContent' => $bizContent,
                'apiAccount' => '640826271705595946',
                'timestamp' => '1622520000000',
            ]);

            $webhook = WebhookData::fromRequest($request);

            expect($webhook->billCode)->toBe('JT001')
                ->and($webhook->txlogisticId)->toBe('ORDER123')
                ->and($webhook->details)->toHaveCount(1)
                ->and($webhook->details[0])->toBeInstanceOf(TrackingDetailData::class)
                ->and($webhook->details[0]->scanType)->toBe('收件')
                ->and($webhook->details[0]->scanNetworkName)->toBe('Kuala Lumpur Hub');
        });

        it('handles optional txlogisticId', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JT002',
                'details' => [
                    [
                        'scanType' => '派件',
                        'scanTime' => '2024-01-16 14:20:00',
                        'desc' => 'Out for delivery',
                        'scanTypeCode' => '5',
                        'scanTypeName' => 'Delivery',
                        'scanNetworkId' => '2',
                        'scanNetworkName' => 'Penang Hub',
                        'scanNetworkCity' => 'Penang',
                        'scanNetworkProvince' => 'Pulau Pinang',
                    ],
                ],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            $webhook = WebhookData::fromRequest($request);

            expect($webhook->billCode)->toBe('JT002')
                ->and($webhook->txlogisticId)->toBeNull()
                ->and($webhook->details)->toHaveCount(1);
        });

        it('parses multiple tracking details', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JT003',
                'details' => [
                    [
                        'scanType' => '收件',
                        'scanTime' => '2024-01-15 10:00:00',
                        'desc' => 'Collected',
                        'scanTypeCode' => '1',
                        'scanTypeName' => 'Collection',
                        'scanNetworkId' => '1',
                        'scanNetworkName' => 'Origin Hub',
                        'scanNetworkCity' => 'Kuala Lumpur',
                        'scanNetworkProvince' => 'Wilayah Persekutuan',
                    ],
                    [
                        'scanType' => '运输中',
                        'scanTime' => '2024-01-15 15:00:00',
                        'desc' => 'In transit',
                        'scanTypeCode' => '2',
                        'scanTypeName' => 'Transit',
                        'scanNetworkId' => '2',
                        'scanNetworkName' => 'Transit Hub',
                        'scanNetworkCity' => 'Selangor',
                        'scanNetworkProvince' => 'Selangor',
                    ],
                    [
                        'scanType' => '派件',
                        'scanTime' => '2024-01-16 09:00:00',
                        'desc' => 'Out for delivery',
                        'scanTypeCode' => '5',
                        'scanTypeName' => 'Delivery',
                        'scanNetworkId' => '3',
                        'scanNetworkName' => 'Destination Hub',
                        'scanNetworkCity' => 'Penang',
                        'scanNetworkProvince' => 'Pulau Pinang',
                    ],
                ],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            $webhook = WebhookData::fromRequest($request);

            expect($webhook->details)->toHaveCount(3)
                ->and($webhook->details[0]->scanType)->toBe('收件')
                ->and($webhook->details[1]->scanType)->toBe('运输中')
                ->and($webhook->details[2]->scanType)->toBe('派件');
        });

        it('throws exception when bizContent is missing', function (): void {
            $request = Request::create('/webhook', 'POST', [
                'digest' => 'test_signature',
            ]);

            WebhookData::fromRequest($request);
        })->throws(ValidationException::class);

        it('throws exception when bizContent is not valid JSON', function (): void {
            $request = Request::create('/webhook', 'POST', [
                'bizContent' => 'not-valid-json',
            ]);

            WebhookData::fromRequest($request);
        })->throws(JntValidationException::class);

        it('throws exception when bizContent is missing billCode', function (): void {
            $bizContent = json_encode([
                'details' => [],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            WebhookData::fromRequest($request);
        })->throws(JntValidationException::class);

        it('throws exception when bizContent is missing details', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JT001',
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            WebhookData::fromRequest($request);
        })->throws(JntValidationException::class);

        it('throws exception when details is not an array', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JT001',
                'details' => 'not-an-array',
            ]);

            $request = Request::create('/webhook', 'POST', [
                'bizContent' => $bizContent,
            ]);

            WebhookData::fromRequest($request);
        })->throws(JntValidationException::class);
    });

    describe('toResponse', function (): void {
        it('generates correct success response structure', function (): void {
            $webhook = new WebhookData(
                billCode: 'JT001',
                txlogisticId: 'ORDER123',
                details: []
            );

            $response = $webhook->toResponse();

            expect($response)->toBeArray()
                ->and($response)->toHaveKeys(['code', 'msg', 'data', 'requestId'])
                ->and($response['code'])->toBe('1')
                ->and($response['msg'])->toBe('success')
                ->and($response['data'])->toBe('SUCCESS')
                ->and($response['requestId'])->toBeString()
                ->and(Str::isUuid($response['requestId']))->toBeTrue();
        });

        it('generates unique requestId for each response', function (): void {
            $webhook = new WebhookData(
                billCode: 'JT001',
                txlogisticId: null,
                details: []
            );

            $response1 = $webhook->toResponse();
            $response2 = $webhook->toResponse();

            expect($response1['requestId'])->not->toBe($response2['requestId']);
        });
    });

    describe('getLatestDetail', function (): void {
        it('returns latest tracking detail', function (): void {
            $detail1 = TrackingDetailData::fromApiArray([
                'scanType' => '收件',
                'scanTime' => '2024-01-15 10:00:00',
                'desc' => 'Collected',
                'scanTypeCode' => '1',
                'scanTypeName' => 'Collection',
                'scanNetworkId' => '1',
                'scanNetworkName' => 'Origin',
                'scanNetworkCity' => 'KL',
                'scanNetworkProvince' => 'WP',
            ]);

            $detail2 = TrackingDetailData::fromApiArray([
                'scanType' => '派件',
                'scanTime' => '2024-01-16 15:00:00',
                'desc' => 'Delivered',
                'scanTypeCode' => '5',
                'scanTypeName' => 'Delivery',
                'scanNetworkId' => '2',
                'scanNetworkName' => 'Destination',
                'scanNetworkCity' => 'Penang',
                'scanNetworkProvince' => 'PP',
            ]);

            $webhook = new WebhookData(
                billCode: 'JT001',
                txlogisticId: null,
                details: [$detail1, $detail2]
            );

            $latest = $webhook->getLatestDetail();

            expect($latest)->toBeInstanceOf(TrackingDetailData::class)
                ->and($latest->scanType)->toBe('派件')
                ->and($latest->scanTime)->toBe('2024-01-16 15:00:00');
        });

        it('returns null when no details exist', function (): void {
            $webhook = new WebhookData(
                billCode: 'JT001',
                txlogisticId: null,
                details: []
            );

            expect($webhook->getLatestDetail())->toBeNull();
        });

        it('returns only detail when single detail exists', function (): void {
            $detail = TrackingDetailData::fromApiArray([
                'scanType' => '收件',
                'scanTime' => '2024-01-15 10:00:00',
                'desc' => 'Collected',
                'scanTypeCode' => '1',
                'scanTypeName' => 'Collection',
                'scanNetworkId' => '1',
                'scanNetworkName' => 'Hub',
                'scanNetworkCity' => 'KL',
                'scanNetworkProvince' => 'WP',
            ]);

            $webhook = new WebhookData(
                billCode: 'JT001',
                txlogisticId: null,
                details: [$detail]
            );

            expect($webhook->getLatestDetail())->toBe($detail);
        });
    });

    describe('toArray', function (): void {
        it('converts webhook to array with all data', function (): void {
            $detail = TrackingDetailData::fromApiArray([
                'scanType' => '收件',
                'scanTime' => '2024-01-15 10:30:00',
                'desc' => 'Package collected',
                'scanTypeCode' => '1',
                'scanTypeName' => 'Collection',
                'scanNetworkId' => '1',
                'scanNetworkName' => 'Kuala Lumpur Hub',
                'scanNetworkCity' => 'Kuala Lumpur',
                'scanNetworkProvince' => 'Wilayah Persekutuan',
            ]);

            $webhook = new WebhookData(
                billCode: 'JT001',
                txlogisticId: 'ORDER123',
                details: [$detail]
            );

            $array = $webhook->toArray();

            expect($array)->toBeArray()
                ->and($array)->toHaveKeys(['billCode', 'txlogisticId', 'details', 'latestStatus', 'latestLocation', 'latestTime'])
                ->and($array['billCode'])->toBe('JT001')
                ->and($array['txlogisticId'])->toBe('ORDER123')
                ->and($array['details'])->toHaveCount(1)
                ->and($array['details'][0])->toBeArray()
                ->and($array['details'][0]['scanType'])->toBe('收件')
                ->and($array['details'][0]['scanNetworkName'])->toBe('Kuala Lumpur Hub')
                ->and($array['latestStatus'])->toBe('收件')
                ->and($array['latestLocation'])->toBe('Kuala Lumpur Hub')
                ->and($array['latestTime'])->toBe('2024-01-15 10:30:00');
        });

        it('handles null txlogisticId', function (): void {
            $webhook = new WebhookData(
                billCode: 'JT002',
                txlogisticId: null,
                details: []
            );

            $array = $webhook->toArray();

            expect($array['txlogisticId'])->toBeNull();
        });

        it('handles empty details array', function (): void {
            $webhook = new WebhookData(
                billCode: 'JT003',
                txlogisticId: 'ORDER789',
                details: []
            );

            $array = $webhook->toArray();

            expect($array['details'])->toBeArray()->toBeEmpty()
                ->and($array['latestStatus'])->toBeNull()
                ->and($array['latestLocation'])->toBeNull()
                ->and($array['latestTime'])->toBeNull();
        });

        it('includes multiple details in correct format', function (): void {
            $detail1 = TrackingDetailData::fromApiArray([
                'scanType' => '收件',
                'scanTime' => '2024-01-15 10:00:00',
                'desc' => 'Collected',
                'scanTypeCode' => '1',
                'scanTypeName' => 'Collection',
                'scanNetworkId' => '1',
                'scanNetworkName' => 'Origin Hub',
                'scanNetworkCity' => 'KL',
                'scanNetworkProvince' => 'WP',
            ]);

            $detail2 = TrackingDetailData::fromApiArray([
                'scanType' => '派件',
                'scanTime' => '2024-01-16 14:00:00',
                'desc' => 'Delivered',
                'scanTypeCode' => '5',
                'scanTypeName' => 'Delivery',
                'scanNetworkId' => '2',
                'scanNetworkName' => 'Destination Hub',
                'scanNetworkCity' => 'Penang',
                'scanNetworkProvince' => 'PP',
            ]);

            $webhook = new WebhookData(
                billCode: 'JT004',
                txlogisticId: null,
                details: [$detail1, $detail2]
            );

            $array = $webhook->toArray();

            expect($array['details'])->toHaveCount(2)
                ->and($array['details'][0]['scanType'])->toBe('收件')
                ->and($array['details'][1]['scanType'])->toBe('派件')
                ->and($array['latestStatus'])->toBe('派件')
                ->and($array['latestTime'])->toBe('2024-01-16 14:00:00');
        });
    });

    describe('Real-World Scenarios', function (): void {
        it('handles complete webhook from J&T', function (): void {
            $bizContent = json_encode([
                'billCode' => 'JNTMY12345678',
                'txlogisticId' => 'SHOP-ORDER-2024-001',
                'details' => [
                    [
                        'scanType' => '收件',
                        'scanTime' => '2024-01-15 09:30:15',
                        'desc' => 'Package collected from sender',
                        'scanTypeCode' => '1',
                        'scanTypeName' => 'Collection',
                        'scanNetworkId' => '1',
                        'scanNetworkName' => 'Kuala Lumpur Collection Hub',
                        'scanNetworkCity' => 'Kuala Lumpur',
                        'scanNetworkProvince' => 'Wilayah Persekutuan',
                    ],
                    [
                        'scanType' => '运输中',
                        'scanTime' => '2024-01-15 14:45:30',
                        'desc' => 'Package in transit to destination hub',
                        'scanTypeCode' => '2',
                        'scanTypeName' => 'Transit',
                        'scanNetworkId' => '2',
                        'scanNetworkName' => 'Selangor Transit Center',
                        'scanNetworkCity' => 'Petaling Jaya',
                        'scanNetworkProvince' => 'Selangor',
                    ],
                    [
                        'scanType' => '到件',
                        'scanTime' => '2024-01-16 08:15:45',
                        'desc' => 'Package arrived at destination hub',
                        'scanTypeCode' => '3',
                        'scanTypeName' => 'Arrived',
                        'scanNetworkId' => '3',
                        'scanNetworkName' => 'Penang Distribution Hub',
                        'scanNetworkCity' => 'George Town',
                        'scanNetworkProvince' => 'Pulau Pinang',
                    ],
                    [
                        'scanType' => '派件',
                        'scanTime' => '2024-01-16 10:30:00',
                        'desc' => 'Out for delivery',
                        'scanTypeCode' => '5',
                        'scanTypeName' => 'Delivery',
                        'scanNetworkId' => '4',
                        'scanNetworkName' => 'Penang Last Mile Delivery',
                        'scanNetworkCity' => 'George Town',
                        'scanNetworkProvince' => 'Pulau Pinang',
                    ],
                ],
            ]);

            $request = Request::create('/webhook', 'POST', [
                'digest' => 'YmFzZTY0X2VuY29kZWRfc2lnbmF0dXJl',
                'bizContent' => $bizContent,
                'apiAccount' => '640826271705595946',
                'timestamp' => '1622520000000',
            ]);

            $webhook = WebhookData::fromRequest($request);

            expect($webhook->billCode)->toBe('JNTMY12345678')
                ->and($webhook->txlogisticId)->toBe('SHOP-ORDER-2024-001')
                ->and($webhook->details)->toHaveCount(4)
                ->and($webhook->getLatestDetail()->scanType)->toBe('派件')
                ->and($webhook->getLatestDetail()->description)->toBe('Out for delivery');

            $response = $webhook->toResponse();
            expect($response['code'])->toBe('1')
                ->and($response['data'])->toBe('SUCCESS');

            $array = $webhook->toArray();
            expect($array['latestStatus'])->toBe('派件')
                ->and($array['latestLocation'])->toBe('Penang Last Mile Delivery');
        });
    });
});
