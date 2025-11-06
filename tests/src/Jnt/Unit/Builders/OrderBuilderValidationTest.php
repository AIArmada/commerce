<?php

declare(strict_types=1);

use AIArmada\Jnt\Builders\OrderBuilder;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\GoodsType;
use AIArmada\Jnt\Exceptions\JntException;

describe('OrderBuilder - Required Fields Validation', function (): void {
    it('throws exception when orderId is missing', function (): void {
        $builder = new OrderBuilder('TEST123', 'password');

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'orderId is required');
    });

    it('throws exception when sender is missing', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123');

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Sender address is required');
    });

    it('throws exception when receiver is missing', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Receiver address is required');
    });

    it('throws exception when items are missing', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'At least one item is required');
    });

    it('throws exception when packageInfo is missing', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00,
                description: 'Test Description'
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Package info is required');
    });
});

describe('OrderBuilder - Field Format Validation', function (): void {
    it('throws exception for invalid sender phone (too short)', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '012345678', // Only 9 digits
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Sender phone must be 10-15 digits');
    });

    it('throws exception for invalid sender postal code', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '1234' // Only 4 digits
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Sender postCode must be 5 digits');
    });
});

describe('OrderBuilder - Field Range Validation', function (): void {
    it('throws exception for package weight too low', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 0.001, // Too low
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Package weight must be between 0.01 and 999.99 kg');
    });

    it('throws exception for package weight too high', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1000.00, // Too high
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Package weight must be between 0.01 and 999.99 kg');
    });

    it('throws exception for item quantity too low', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 0, // Too low
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Item #1 quantity must be between 1 and 999');
    });

    it('throws exception for item weight too low', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 0, // Too low
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Item #1 weight must be between 1 and 999,999 grams');
    });

    it('throws exception for item price too low', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 0.001 // Too low
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Item #1 price must be between 0.01 and 999,999.99');
    });
});

describe('OrderBuilder - Field Length Validation', function (): void {
    it('throws exception for orderId too long', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId(str_repeat('A', 51)) // 51 characters (max is 50)
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'orderId must not exceed 50 characters');
    });

    it('throws exception for sender name too long', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: str_repeat('A', 201), // 201 characters
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Sender name must not exceed 200 characters');
    });

    it('throws exception for item description too long', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00,
                description: str_repeat('A', 501) // 501 characters
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ));

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Item #1 description must not exceed 500 characters');
    });

    it('throws exception for remark too long', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test Item',
                quantity: 1,
                weight: 500,
                price: 10.00
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.0,
                value: 10.00,
                goodsType: GoodsType::PACKAGE
            ))
            ->remark(str_repeat('A', 301)); // 301 characters (max is 300)

        expect(fn (): array => $builder->build())
            ->toThrow(JntException::class, 'Remark must not exceed 300 characters');
    });
});

describe('OrderBuilder - Valid Order', function (): void {
    it('successfully builds valid order with all required fields', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: 'Sender Name',
                phone: '0123456789',
                address: '123 Sender Street',
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver Name',
                phone: '0123456789',
                address: '456 Receiver Avenue',
                postCode: '54321'
            ))
            ->addItem(new ItemData(
                name: 'Test Product',
                quantity: 2,
                weight: 500,
                price: 25.50,
                description: 'Test product description'
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 1,
                weight: 1.5,
                value: 51.00,
                goodsType: GoodsType::PACKAGE,
                length: 30.0,
                width: 20.0,
                height: 10.0
            ));

        $result = $builder->build();

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys([
                'customerCode',
                'password',
                'txlogisticId',
                'sender',
                'receiver',
                'items',
                'packageInfo',
            ]);
    });

    it('successfully builds order with valid edge values', function (): void {
        $builder = new OrderBuilder('TEST123', 'password')
            ->orderId('ORDER123')
            ->sender(new AddressData(
                name: str_repeat('A', 200), // Max length
                phone: '0123456789012', // 13 digits (valid)
                address: str_repeat('B', 200), // Max length
                postCode: '12345'
            ))
            ->receiver(new AddressData(
                name: 'Receiver',
                phone: '0123456789',
                address: 'Address',
                postCode: '12345'
            ))
            ->addItem(new ItemData(
                name: 'Test',
                quantity: 999, // Max quantity
                weight: 999999, // Max weight in grams
                price: 999999.99, // Max price
                description: str_repeat('C', 500) // Max description length
            ))
            ->packageInfo(new PackageInfoData(
                quantity: 999, // Max quantity
                weight: 999.99, // Max weight in kg
                value: 999999.99, // Max value
                goodsType: GoodsType::PACKAGE,
                length: 999.99, // Max length
                width: 999.99, // Max width
                height: 999.99 // Max height
            ));

        $result = $builder->build();

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('txlogisticId', 'ORDER123');
    });
});
