<?php

declare(strict_types=1);

use AIArmada\Jnt\Support\FieldNameConverter;

describe('FieldNameConverter', function (): void {
    it('converts order-level clean field names', function (): void {
        $input = [
            'orderId' => 'ORDER123',
            'trackingNumber' => 'TN456',
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted)->toHaveKey('txlogisticId', 'ORDER123');
        expect($converted)->not->toHaveKey('orderId');
        expect($converted)->toHaveKey('billCode', 'TN456');
        expect($converted)->not->toHaveKey('trackingNumber');
    });

    it('converts sender address clean field names', function (): void {
        $input = [
            'sender' => [
                'name' => 'John Doe',
                'state' => 'Selangor',
                'city' => 'Shah Alam',
            ],
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted['sender'])->toHaveKey('prov', 'Selangor');
        expect($converted['sender'])->not->toHaveKey('state');
        expect($converted['sender'])->toHaveKey('name', 'John Doe');
        expect($converted['sender'])->toHaveKey('city', 'Shah Alam');
    });

    it('converts receiver address clean field names', function (): void {
        $input = [
            'receiver' => [
                'name' => 'Jane Smith',
                'state' => 'Kuala Lumpur',
                'phone' => '0123456789',
            ],
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted['receiver'])->toHaveKey('prov', 'Kuala Lumpur');
        expect($converted['receiver'])->not->toHaveKey('state');
        expect($converted['receiver'])->toHaveKey('name', 'Jane Smith');
        expect($converted['receiver'])->toHaveKey('phone', '0123456789');
    });

    it('converts item clean field names', function (): void {
        $input = [
            'items' => [
                [
                    'name' => 'Widget',
                    'quantity' => 2,
                    'price' => 50.00,
                    'description' => 'Test product',
                    'weight' => 200,
                ],
            ],
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted['items'][0])->toHaveKey('itemName', 'Widget');
        expect($converted['items'][0])->not->toHaveKey('name');
        expect($converted['items'][0])->toHaveKey('number', 2);
        expect($converted['items'][0])->not->toHaveKey('quantity');
        expect($converted['items'][0])->toHaveKey('itemValue', 50.00);
        expect($converted['items'][0])->not->toHaveKey('price');
        expect($converted['items'][0])->toHaveKey('itemDesc', 'Test product');
        expect($converted['items'][0])->not->toHaveKey('description');
        expect($converted['items'][0])->toHaveKey('weight', 200);
    });

    it('converts packageInfo clean field names', function (): void {
        $input = [
            'packageInfo' => [
                'quantity' => 1,
                'weight' => 1.5,
                'value' => 99.99,
                'goodsType' => 'ITN8',
                'length' => 30,
                'width' => 20,
                'height' => 10,
            ],
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted['packageInfo'])->toHaveKey('packageQuantity', 1);
        expect($converted['packageInfo'])->not->toHaveKey('quantity');
        expect($converted['packageInfo'])->toHaveKey('packageValue', 99.99);
        expect($converted['packageInfo'])->not->toHaveKey('value');
        expect($converted['packageInfo'])->toHaveKey('weight', 1.5);
        expect($converted['packageInfo'])->toHaveKey('goodsType', 'ITN8');
    });

    it('converts all clean field names in complete order', function (): void {
        $input = [
            'orderId' => 'ORDER123',
            'trackingNumber' => 'TN123',
            'sender' => [
                'name' => 'John Doe',
                'state' => 'Selangor',
            ],
            'receiver' => [
                'name' => 'Jane Smith',
                'state' => 'Kuala Lumpur',
            ],
            'items' => [
                [
                    'name' => 'Product 1',
                    'quantity' => 2,
                    'price' => 50.00,
                    'weight' => 200,
                    'description' => 'Description 1',
                ],
            ],
            'packageInfo' => [
                'quantity' => 1,
                'weight' => 1.5,
                'value' => 100.00,
                'goodsType' => 'ITN8',
            ],
        ];

        $converted = FieldNameConverter::convert($input);

        // Order level
        expect($converted)->toHaveKey('txlogisticId', 'ORDER123');
        expect($converted)->toHaveKey('billCode', 'TN123');
        expect($converted)->not->toHaveKey('orderId');
        expect($converted)->not->toHaveKey('trackingNumber');

        // Sender
        expect($converted['sender'])->toHaveKey('prov', 'Selangor');
        expect($converted['sender'])->not->toHaveKey('state');

        // Receiver
        expect($converted['receiver'])->toHaveKey('prov', 'Kuala Lumpur');
        expect($converted['receiver'])->not->toHaveKey('state');

        // Items
        expect($converted['items'][0])->toHaveKey('itemName', 'Product 1');
        expect($converted['items'][0])->toHaveKey('number', 2);
        expect($converted['items'][0])->toHaveKey('itemValue', 50.00);
        expect($converted['items'][0])->toHaveKey('itemDesc', 'Description 1');
        expect($converted['items'][0])->not->toHaveKey('name');
        expect($converted['items'][0])->not->toHaveKey('quantity');
        expect($converted['items'][0])->not->toHaveKey('price');
        expect($converted['items'][0])->not->toHaveKey('description');

        // PackageInfo
        expect($converted['packageInfo'])->toHaveKey('packageQuantity', 1);
        expect($converted['packageInfo'])->toHaveKey('packageValue', 100.00);
        expect($converted['packageInfo'])->not->toHaveKey('quantity');
        expect($converted['packageInfo'])->not->toHaveKey('value');
    });

    it('preserves API field names when no clean names present', function (): void {
        $input = [
            'txlogisticId' => 'ORDER123',
            'billCode' => 'TN123',
            'sender' => [
                'name' => 'John Doe',
                'prov' => 'Selangor',
            ],
            'items' => [
                [
                    'itemName' => 'Product',
                    'number' => 1,
                    'itemValue' => 50.00,
                    'weight' => 200,
                ],
            ],
            'packageInfo' => [
                'packageQuantity' => 1,
                'weight' => 1.5,
                'packageValue' => 100.00,
                'goodsType' => 'ITN8',
            ],
        ];

        $converted = FieldNameConverter::convert($input);

        // Should preserve API field names
        expect($converted)->toHaveKey('txlogisticId', 'ORDER123');
        expect($converted)->toHaveKey('billCode', 'TN123');
        expect($converted['sender'])->toHaveKey('prov', 'Selangor');
        expect($converted['items'][0])->toHaveKey('itemName', 'Product');
        expect($converted['items'][0])->toHaveKey('number', 1);
        expect($converted['items'][0])->toHaveKey('itemValue', 50.00);
        expect($converted['packageInfo'])->toHaveKey('packageQuantity', 1);
        expect($converted['packageInfo'])->toHaveKey('packageValue', 100.00);
    });

    it('handles mixed clean and API field names (clean takes precedence)', function (): void {
        $input = [
            'orderId' => 'CLEAN_ORDER',
            'txlogisticId' => 'API_ORDER',
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted)->toHaveKey('txlogisticId', 'CLEAN_ORDER');
        expect($converted)->not->toHaveKey('orderId');
    });

    it('handles empty arrays gracefully', function (): void {
        $input = [
            'orderId' => 'ORDER123',
            'sender' => [],
            'receiver' => [],
            'items' => [],
            'packageInfo' => [],
        ];

        $converted = FieldNameConverter::convert($input);

        expect($converted)->toHaveKey('txlogisticId', 'ORDER123');
        expect($converted['sender'])->toBeArray()->toBeEmpty();
        expect($converted['receiver'])->toBeArray()->toBeEmpty();
        expect($converted['items'])->toBeArray()->toBeEmpty();
        expect($converted['packageInfo'])->toBeArray()->toBeEmpty();
    });
});
