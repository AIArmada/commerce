import csv, os, sys
# Rwanda gate. B8 revisit: verify-only, zero data changes.
# Tree 35/35: 5 L1 vs ISO 3166-2:RW current (RW-01..05; RW-B..M
# are retired prefectures in Changes) + 30 districts exact vs
# WP Districts of Rwanda and citypopulation (East 7, Kigali 3,
# North 5, South 8, West 7). No postcode system: WP List of
# postal codes "no codes" + absent from UPU Aug-2022 type table
# + no RW.zip in the GeoNames index, so no postal files exist.
# Run from repo root:
# python3 docs/agents/audit/gate_rw.py
A = './packages/addressing/resources/geography/rwanda-address-areas.csv'
C = './packages/addressing/resources/geography/rwanda-postal-codes.csv'
L = './packages/addressing/resources/geography/rwanda-postal-code-areas.csv'
L1 = {'01': ('Kigali', 'city'), '02': ('Eastern', 'province'),
      '03': ('Northern', 'province'), '04': ('Western', 'province'),
      '05': ('Southern', 'province')}
DISTS = {'rw:province:eastern': ['Bugesera', 'Gatsibo', 'Kayonza',
                                'Kirehe', 'Ngoma', 'Nyagatare',
                                'Rwamagana'],
         'rw:city:kigali': ['Gasabo', 'Kicukiro', 'Nyarugenge'],
         'rw:province:northern': ['Burera', 'Gakenke', 'Gicumbi',
                                 'Musanze', 'Rulindo'],
         'rw:province:southern': ['Gisagara', 'Huye', 'Kamonyi',
                                 'Muhanga', 'Nyamagabe', 'Nyanza',
                                 'Nyaruguru', 'Ruhango'],
         'rw:province:western': ['Karongi', 'Ngororero', 'Nyabihu',
                                'Nyamasheke', 'Rubavu', 'Rusizi',
                                'Rutsiro']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw)
check('areas-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-35', len(areas) == 35, str(len(areas)))
l1 = {r['code']: (r['name'], r['type']) for r in areas if r['level'] == '1'}
check('l1-5', l1 == L1, str({k for k in L1 if l1.get(k) != L1[k]}))
got = {}
for r in areas:
    if r['level'] == '2':
        got.setdefault(r['parent_source_id'], []).append(r['name'])
got = {k: sorted(v) for k, v in got.items()}
want = {k: sorted(v) for k, v in DISTS.items()}
check('districts-30', got == want, str({k for k in want if got.get(k) != want[k]}))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
