import csv, sys
# Palau gate. B5 revisit: verify-only, zero data changes.
# Tree 16/16 vs ISO 3166-2:PW (3-digit codes). Overlay 2/16: UPU
# plw covers two codes (US system); 96939 is Ngerulmud-only per
# WP (capital settlement in Melekeok), 96940 the rest with Koror
# primary (USPS bulletin build source). GeoNames PW carries only
# 96940 (weak single row). Run from repo root:
# python3 docs/agents/audit/gate_pw.py
A = './packages/addressing/resources/geography/palau-address-areas.csv'
C = './packages/addressing/resources/geography/palau-postal-codes.csv'
L = './packages/addressing/resources/geography/palau-postal-code-areas.csv'
ISO = {'002': 'Aimeliik', '004': 'Airai', '010': 'Angaur',
       '050': 'Hatohobei', '100': 'Kayangel', '150': 'Koror',
       '212': 'Melekeok', '214': 'Ngaraard', '218': 'Ngarchelong',
       '222': 'Ngardmau', '224': 'Ngatpang', '226': 'Ngchesar',
       '227': 'Ngeremlengui', '228': 'Ngiwal', '350': 'Peleliu',
       '370': 'Sonsorol'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-16', len(areas) == 16, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('iso-16', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-2', set(codes) == {'96939', '96940'}, str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-16', len(links) == 16, str(len(links)))
by = {}
for r in links:
    by.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('96939-melekeok', by.get('96939') == [('pw:state:melekeok', 'true')],
      str(by.get('96939')))
rest = by.get('96940', [])
check('96940-koror-primary', rest[:1] == [('pw:state:koror', 'true')])
check('96940-15', len(rest) == 15, str(len(rest)))
check('96940-no-melekeok', all(a != 'pw:state:melekeok' for a, _ in rest))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
