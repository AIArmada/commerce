import csv, os, sys
# Bahamas gate. B7 revisit: verify-only, zero data changes.
# Tree 32/32 vs ISO 3166-2:BS current codes (1 island New
# Providence + 31 districts; BS-AC/FC/GH/GT/HR/KB/MH/NB/RS/SP/SR
# appear only in the Changes section as retired). No postcode
# system per the UPU bhs profile ("The Bahamas do not apply a
# postcode system") and GeoNames ships no BS.zip, so no postal
# files exist. Run from repo root:
# python3 docs/agents/audit/gate_bs.py
A = './packages/addressing/resources/geography/bahamas-address-areas.csv'
C = './packages/addressing/resources/geography/bahamas-postal-codes.csv'
L = './packages/addressing/resources/geography/bahamas-postal-code-areas.csv'
ISO = {'AK': ('Acklins', 'district'), 'BY': ('Berry Islands', 'district'),
       'BI': ('Bimini', 'district'), 'BP': ('Black Point', 'district'),
       'CI': ('Cat Island', 'district'), 'CO': ('Central Abaco', 'district'),
       'CS': ('Central Andros', 'district'),
       'CE': ('Central Eleuthera', 'district'),
       'CK': ('Crooked Island and Long Cay', 'district'),
       'EG': ('East Grand Bahama', 'district'), 'EX': ('Exuma', 'district'),
       'FP': ('City of Freeport', 'district'),
       'GC': ('Grand Cay', 'district'),
       'HI': ('Harbour Island', 'district'),
       'HT': ('Hope Town', 'district'), 'IN': ('Inagua', 'district'),
       'LI': ('Long Island', 'district'),
       'MC': ('Mangrove Cay', 'district'),
       'MG': ('Mayaguana', 'district'),
       'MI': ("Moore's Island", 'district'),
       'NP': ('New Providence', 'island'),
       'NO': ('North Abaco', 'district'),
       'NS': ('North Andros', 'district'),
       'NE': ('North Eleuthera', 'district'),
       'RI': ('Ragged Island', 'district'), 'RC': ('Rum Cay', 'district'),
       'SS': ('San Salvador', 'district'),
       'SO': ('South Abaco', 'district'),
       'SA': ('South Andros', 'district'),
       'SE': ('South Eleuthera', 'district'),
       'SW': ('Spanish Wells', 'district'),
       'WG': ('West Grand Bahama', 'district')}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw)
check('areas-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-32', len(areas) == 32, str(len(areas)))
got = {r['code']: (r['name'], r['type']) for r in areas}
check('iso-32', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
check('level-1', all(r['level'] == '1' for r in areas))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
