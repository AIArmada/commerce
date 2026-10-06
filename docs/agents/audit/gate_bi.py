import csv, os, sys
# Burundi gate. B8 revisit: verify-only, zero data changes.
# Tree 47/47 on the 2025 structure: 5 provinces (Loi Organique
# 1/05 du 16 mars 2023, effective with the 2025 elections; ISO
# 3166-2:BI still lists the 18 former provinces) + 42 communes
# exact vs the enacted law Article 5 (OCR of the CENI scan:
# Buhumuza 7, Bujumbura 11, Burunga 7, Butanyerera 8, Gitega 9)
# and citypopulation. No postcode system: UPU bdi profile
# example carries no code + WP List "no codes" + absent from
# UPU Aug-2022 type table + no BI.zip in the GeoNames index,
# so no postal files exist. Run from repo root:
# python3 docs/agents/audit/gate_bi.py
A = './packages/addressing/resources/geography/burundi-address-areas.csv'
C = './packages/addressing/resources/geography/burundi-postal-codes.csv'
L = './packages/addressing/resources/geography/burundi-postal-code-areas.csv'
L1 = {'01': 'Buhumuza', '02': 'Bujumbura', '03': 'Burunga',
      '04': 'Butanyerera', '05': 'Gitega'}
COMMS = {'bi:province:buhumuza': ['Butaganzwa', 'Butihinda', 'Cankuzo',
                                 'Gisagara', 'Gisuru', 'Muyinga',
                                 'Ruyigi'],
         'bi:province:bujumbura': ['Bubanza', 'Bukinanyana', 'Cibitoke',
                                  'Isare', 'Mpanda', 'Mugere', 'Mugina',
                                  'Muhuta', 'Mukaza', 'Ntahangwa',
                                  'Rwibaga'],
         'bi:province:burunga': ['Bururi', 'Makamba', 'Matana',
                                'Musongati', 'Nyanza', 'Rumonge',
                                'Rutana'],
         'bi:province:butanyerera': ['Busoni', 'Kayanza', 'Kiremba',
                                    'Kirundo', 'Matongo', 'Muhanga',
                                    'Ngozi', 'Tangara'],
         'bi:province:gitega': ['Bugendana', 'Gishubi', 'Gitega',
                               'Karusi', 'Kiganda', 'Muramvya', 'Mwaro',
                               'Nyabihanga', 'Shombo']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw)
check('areas-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-47', len(areas) == 47, str(len(areas)))
l1 = {r['code']: r['name'] for r in areas if r['level'] == '1'}
check('l1-5', l1 == L1, str({k for k in L1 if l1.get(k) != L1[k]}))
got = {}
for r in areas:
    if r['level'] == '2':
        got.setdefault(r['parent_source_id'], []).append(r['name'])
got = {k: sorted(v) for k, v in got.items()}
want = {k: sorted(v) for k, v in COMMS.items()}
check('communes-42', got == want, str({k for k in want if got.get(k) != want[k]}))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
