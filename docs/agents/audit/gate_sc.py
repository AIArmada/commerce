import csv, os, sys
# Seychelles gate. B7 revisit: verify-only, zero data changes.
# Tree 27/27 vs ISO 3166-2:SC (SC-26/27 Ile Perseverance I/II added
# 2020-11-24 per OBP; WP districts article is stale at 26 with
# 26=Outer Islands). Names follow local French forms, not the ISO
# ASCII renderings (Anse-aux-Pins, Grand'Anse Mahé/Praslin,
# La Rivière Anglaise, Pointe La Rue). No postcode system: UPU syc
# profile addressing example carries no code and GeoNames ships no
# SC.zip (MW/RE/GP/IE/LK all present in the same index), so no
# postal files exist. Run from repo root:
# python3 docs/agents/audit/gate_sc.py
A = './packages/addressing/resources/geography/seychelles-address-areas.csv'
C = './packages/addressing/resources/geography/seychelles-postal-codes.csv'
L = './packages/addressing/resources/geography/seychelles-postal-code-areas.csv'
ISO = {'01': 'Anse-aux-Pins', '02': 'Anse Boileau', '03': 'Anse Etoile',
       '04': 'Au Cap', '05': 'Anse Royale', '06': 'Baie Lazare',
       '07': 'Baie Sainte Anne', '08': 'Beau Vallon', '09': 'Bel Air',
       '10': 'Bel Ombre', '11': 'Cascade', '12': 'Glacis',
       '13': "Grand'Anse Mahé", '14': "Grand'Anse Praslin",
       '15': 'La Digue', '16': 'La Rivière Anglaise',
       '17': 'Mont Buxton', '18': 'Mont Fleuri', '19': 'Plaisance',
       '20': 'Pointe La Rue', '21': 'Port Glaud', '22': 'Saint Louis',
       '23': 'Takamaka', '24': 'Les Mamelles', '25': 'Roche Caiman',
       '26': 'Ile Perseverance I', '27': 'Ile Perseverance II'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw)
check('areas-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-27', len(areas) == 27, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('iso-27', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
check('districts-27', all(r['type'] == 'district' and r['level'] == '1' for r in areas))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
