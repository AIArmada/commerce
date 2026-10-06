import csv, sys
# Jersey gate. B9 revisit. Verify-only, zero data changes.
# Tree 68/68: 12 parishes (no ISO 3166-2:JE codes exist; St
# spellings per the JE/GG in-repo island convention) + 56
# vingtaines/cantons/cueillettes exact vs the WP Vingtaine master
# table (opendata.gov.je census sourcing). Postal 2/12: JE2
# (St Helier x2 sectors primary + St Clement + St Saviour) +
# JE3 (9 rural parishes, Grouville primary as lowest parish
# code among single-sector legs) exact vs the JE postcode-area
# table; JE1 large-users + JE4 PO-boxes + JE5 bespoke excluded
# as non-geographic. Run from repo root:
# python3 docs/agents/audit/gate_je.py
A = './packages/addressing/resources/geography/jersey-address-areas.csv'
C = './packages/addressing/resources/geography/jersey-postal-codes.csv'
L = './packages/addressing/resources/geography/jersey-postal-code-areas.csv'
PARISHES = {'01': 'Grouville', '02': 'St Brelade', '03': 'St Clement',
            '04': 'St Helier', '05': 'St John', '06': 'St Lawrence',
            '07': 'St Martin', '08': 'St Mary', '09': 'St Ouen',
            '10': 'St Peter', '11': 'St Saviour', '12': 'Trinity'}
L2COUNTS = {'je:parish:grouville': 4, 'je:parish:st-brelade': 4,
            'je:parish:st-clement': 3, 'je:parish:st-helier': 7,
            'je:parish:st-john': 3, 'je:parish:st-lawrence': 6,
            'je:parish:st-martin': 5, 'je:parish:st-mary': 2,
            'je:parish:st-ouen': 6, 'je:parish:st-peter': 5,
            'je:parish:st-saviour': 6, 'je:parish:trinity': 5}
MAP = {'JE2': [('je:parish:st-helier', 'true'),
               ('je:parish:st-clement', 'false'),
               ('je:parish:st-saviour', 'false')],
       'JE3': [('je:parish:grouville', 'true'),
               ('je:parish:st-brelade', 'false'),
               ('je:parish:st-john', 'false'),
               ('je:parish:st-lawrence', 'false'),
               ('je:parish:st-martin', 'false'),
               ('je:parish:st-mary', 'false'),
               ('je:parish:st-ouen', 'false'),
               ('je:parish:st-peter', 'false'),
               ('je:parish:trinity', 'false')]}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
for label, path in (('codes', C), ('links', L)):
    raw = open(path, 'rb').read()
    check(f'{label}-crlf', b'\r\n' in raw and raw.count(b'\r') == raw.count(b'\n'))
    check(f'{label}-trailing-crlf', raw.endswith(b'\r\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-68', len(areas) == 68, str(len(areas)))
got_p = {r['code']: r['name'] for r in areas if r['level'] == '1'}
check('parishes-12', got_p == PARISHES, str({k for k in PARISHES if got_p.get(k) != PARISHES[k]}))
got_c = {}
for r in areas:
    if r['level'] == '2':
        got_c[r['parent_source_id']] = got_c.get(r['parent_source_id'], 0) + 1
check('l2-counts', got_c == L2COUNTS, str({k for k in L2COUNTS if got_c.get(k) != L2COUNTS[k]}))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-2', codes == ['JE2', 'JE3'], str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-12', len(links) == 12, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('per-code-mapping', got == MAP, str({k for k in MAP if got.get(k) != MAP[k]}))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
