<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Malaysia\MalaysiaAddressComponents;

it('partners the town and collapses the locality when the subdivision is town-like', function (): void {
    expect(MalaysiaAddressComponents::for('Shah Alam', 'Bandar', 'Petaling', 'Seksyen U11'))
        ->toBe(['locality' => null, 'postcode_partner' => 'Shah Alam']);
});

it('partners the daerah and keeps the mukim otherwise', function (): void {
    expect(MalaysiaAddressComponents::for('Mukim Bagan', 'mukim', 'Batu Pahat', 'Bagan'))
        ->toBe(['locality' => 'Bagan', 'postcode_partner' => 'Batu Pahat']);
});

it('leads with the mukim name when no locality assignment exists', function (): void {
    // Live 4511 shape: district + mukim subdivision, no postal_locality.
    expect(MalaysiaAddressComponents::for('Bagan', 'mukim', 'Batu Pahat', null, 'Bandar Penggaram'))
        ->toBe(['locality' => 'Bagan', 'postcode_partner' => 'Batu Pahat']);
});

it('leaves the partner null where no daerah exists', function (): void {
    // Perlis + WP: subdivision present but not town-like, no daerah.
    expect(MalaysiaAddressComponents::for('Mukim Arau', 'mukim', null, 'Arau'))
        ->toBe(['locality' => 'Arau', 'postcode_partner' => null]);
});

it('treats blank and missing subdivision names as rural', function (): void {
    expect(MalaysiaAddressComponents::for(null, 'bandar', 'Batu Pahat', 'Bagan'))
        ->toBe(['locality' => 'Bagan', 'postcode_partner' => 'Batu Pahat'])
        ->and(MalaysiaAddressComponents::for('  ', 'pekan', '  ', '  '))
        ->toBe(['locality' => null, 'postcode_partner' => null]);
});

it('falls back to entered city text only when assignments say nothing', function (): void {
    expect(MalaysiaAddressComponents::for(null, null, null, null, 'Temerloh'))
        ->toBe(['locality' => null, 'postcode_partner' => 'Temerloh'])
        // Daerah partner wins over the postal town; city text never
        // feeds the locality slot beside it.
        ->and(MalaysiaAddressComponents::for('Mukim Bagan', 'mukim', 'Batu Pahat', 'Bagan', 'Bandar Penggaram'))
        ->toBe(['locality' => 'Bagan', 'postcode_partner' => 'Batu Pahat'])
        ->and(MalaysiaAddressComponents::for('Shah Alam', 'bandar', 'Petaling', 'Seksyen U11', 'Shah Alam'))
        ->toBe(['locality' => null, 'postcode_partner' => 'Shah Alam']);
});
