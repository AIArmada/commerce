<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;

function supportConnectionDriverPdo(string $serverVersion): PDO
{
    return new class($serverVersion) extends PDO
    {
        public function __construct(private string $serverVersion)
        {
            // No parent constructor: no network connection is opened.
        }

        public function getAttribute(int $attribute): mixed
        {
            return $attribute === PDO::ATTR_SERVER_VERSION ? $this->serverVersion : null;
        }
    };
}

it('detects MariaDB behind the MySQL connection driver', function (): void {
    $connection = new MySqlConnection(supportConnectionDriverPdo('11.4.2-MariaDB'), 'test', '', ['driver' => 'mysql']);

    expect(ConnectionDriver::name($connection))->toBe('mysql')
        ->and(ConnectionDriver::isMariaDb($connection))->toBeTrue();
});

it('does not classify MySQL as MariaDB', function (): void {
    $connection = new MySqlConnection(supportConnectionDriverPdo('8.0.36'), 'test', '', ['driver' => 'mysql']);

    expect(ConnectionDriver::name($connection))->toBe('mysql')
        ->and(ConnectionDriver::isMariaDb($connection))->toBeFalse();
});

it('recognizes the native MariaDB connection driver', function (): void {
    $connection = new MariaDbConnection(supportConnectionDriverPdo('11.4.2-MariaDB'), 'test', '', ['driver' => 'mariadb']);

    expect(ConnectionDriver::isMariaDb($connection))->toBeTrue();
});
