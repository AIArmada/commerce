import csv, re, sys
from collections import Counter
# Cambodia gate. Pins the B16 inline pass (5-leg Samraong
# collision fix; 235 areas / 1633 codes / 1633 legs / 0
# multis): 24 provinces + Phnom Penh L1 (codes 1-25 ISO
# exact) + 210 L2 (163 district + 33 municipality + 14
# section) exact vs the en.wiki NIS-sourced district list
# (210/210 codes, names, types, parents). FIX: 240401-05
# moved Takeo Samraong district (2107) -> Oddar Meanchey
# Samraong municipality (2204): NIS communes 220401-05 are
# district-04 communes (COD-AB 2018 confirms 2204), and a
# full district-part audit shows these 5 as the ONLY legs
# not fitting the postcode=NIS+22/23/24-remap rule (other
# 1628 fit, incl. 060705 Kraya->Santuk). Postal: 1537/1633
# communes overlap COD-AB 2018 (96 post-2018 creations in
# new districts 0509/0510/0609/0709/0812/0813/1007/1213/
# 1214/1507/1715/1805/1806/1906; Bokor 070901-03 + Kamboul
# 121401-07 CPC-transcription exact); Ou Krasar gap is
# 230102/220102 (COD-AB: Ou Krasar=230203 in Kaeb, Damnak
# holds 230101+230103). Excluded: 141006 stale NIS-2009,
# all XX00 district bases. Holds: Chrouy-vs-Chroy Changvar
# (en.wiki split: list display Chrouy = bundle, URL Chroy),
# Ratanakiri single-k (en.wiki; COD-AB double-k).
# EOL: areas LF-only, postal files CRLF (pre-existing mix).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_kh.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/cambodia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/cambodia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/cambodia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
def is_crlf(raw):
    return b'\r\n' in raw and b'\r' not in raw.replace(b'\r\n', b'') and b'\n' not in raw.replace(b'\r\n', b'')
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-CRLF', is_crlf(raw_c))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', is_crlf(raw_l))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-235', len(rows) == 235, str(len(rows)))
check('province-24', sum(1 for r in rows if r['type'] == 'province' and r['level'] == '1') == 24)
check('l1-municipality-1', sum(1 for r in rows if r['type'] == 'municipality' and r['level'] == '1') == 1)
check('district-163', sum(1 for r in rows if r['type'] == 'district') == 163)
check('l2-municipality-33', sum(1 for r in rows if r['type'] == 'municipality' and r['level'] == '2') == 33)
check('section-14', sum(1 for r in rows if r['type'] == 'section') == 14)
check('l1-codes-1-25', sorted((r['code'] for r in rows if r['level'] == '1'), key=int) ==
      [str(i) for i in range(1, 26)])
check('l2-codes-unique', len({r['code'] for r in rows if r['level'] == '2'}) == 210)
spots = {'kh:municipality:phnom-penh': ('Phnom Penh', '12'),
         'kh:province:tboung-khmum': ('Tboung Khmum', '25'),
         'kh:municipality:samraong': ('Samraong', '2204'),
         'kh:district:samraong': ('Samraong', '2107'),
         'kh:section:kamboul': ('Kamboul', '1214'),
         'kh:municipality:bokor': ('Bokor', '0709'),
         'kh:district:damnak-chang-aeur': ("Damnak Chang'aeur", '2301')}
for sid, (nm, cd) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('code') == cd,
          str((r.get('name'), r.get('code'))))
check('codes-1633', len(codes) == 1633, str(len(codes)))
check('links-1633', len(links) == 1633, str(len(links)))
check('codes-KH', all(c['country_code'] == 'KH' for c in codes))
clist = [c['code'] for c in codes]
check('codes-6digit', all(re.match(r'^\d{6}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 1633)
for c in ['141006', '220102', '120100', '070900', '230200']:
    check(f'excluded-{c}', c not in clist)
keeps = {'240401': 'kh:municipality:samraong', '240405': 'kh:municipality:samraong',
         '210701': 'kh:district:samraong', '060705': 'kh:district:santuk',
         '130801': 'kh:municipality:preah-vihear', '120209': 'kh:section:doun-penh',
         '220101': 'kh:district:damnak-chang-aeur', '220203': 'kh:municipality:kep',
         '230201': 'kh:district:sala-krau', '240101': 'kh:district:anlong-veaeng',
         '070901': 'kh:municipality:bokor', '121401': 'kh:section:kamboul',
         '030301': 'kh:district:cheung-prey'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
