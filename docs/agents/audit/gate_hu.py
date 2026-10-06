import csv, re, sys
from collections import Counter
# Hungary gate. Pins the B16 worker pass as integrated (2 leg
# drops + 3 fills; 240 areas / 3048 codes / 3066 legs / 18
# multis): 19 counties + 23 cities with county rights + 1
# capital (43/43 ISO 3166-2:HU) + 197 districts (174 county
# districts 3-oracle exact + 23 Budapest kerületek). FIX-1:
# 2943 single-leg Komárom (Tárkány is 2945/Kisbéri per
# hu.wiki + OSM; GN Tárkány@2943 neighbor error). FIX-2:
# 9764 single-leg Szombathely primary (Meggyeskovácsi is
# 9757/Sárvári per hu.wiki + OSM + WD; bundle 9757 kept).
# FILLs: 3244 Parádfürdő→Pétervására (GN + WD + hu.wiki),
# 3603 Sajóvárkony→Ózd (GN + WD; medium, hu.wiki/OSM code
# silent), 9719 Szentkirály→Szombathely (WD + hu.wiki).
# 18 surviving multis WD-attribution confirmed each leg;
# 8139 Enying kept (single-source, no removal on negative
# evidence); 2242/3071 GN-only held out (hu.wiki
# contradicts: Sülysáp 2241, Bátonyterenye 3070). EOL:
# areas LF-only, postal files CRLF (pre-existing mix kept).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_hu.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/hungary-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/hungary-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/hungary-postal-code-areas.csv'
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
check('areas-240', len(rows) == 240, str(len(rows)))
check('county-19', sum(1 for r in rows if r['type'] == 'county') == 19)
check('city-rights-23', sum(1 for r in rows if r['type'] == 'city_with_county_rights') == 23)
check('capital-1', sum(1 for r in rows if r['type'] == 'capital_city') == 1)
check('district-197', sum(1 for r in rows if r['type'] == 'district') == 197)
check('l1-codes-43', len({r['code'] for r in rows if r['level'] == '1'}) == 43)
check('l2-parents-20', len({r['parent_source_id'] for r in rows if r['level'] == '2'}) == 20)
check('budapest-23', sum(1 for r in rows if r['parent_source_id'] == 'hu:capital_city:budapest') == 23)
spots = {'hu:capital_city:budapest': ('Budapest', 'BU'),
         'hu:county:komarom-esztergom': ('Komárom-Esztergom', 'KE'),
         'hu:district:komarom': ('Komárom', ''), 'hu:district:kisber': ('Kisbér', ''),
         'hu:district:szombathely': ('Szombathely', ''), 'hu:district:sarvar': ('Sárvár', ''),
         'hu:district:petervasara': ('Pétervására', ''), 'hu:district:ozd': ('Ózd', '')}
for sid, (nm, cd) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('code', '') == cd,
          str((r.get('name'), r.get('code'))))
check('codes-3048', len(codes) == 3048, str(len(codes)))
check('links-3066', len(links) == 3066, str(len(links)))
check('codes-HU', all(c['country_code'] == 'HU' for c in codes))
clist = [c['code'] for c in codes]
check('codes-4digit', all(re.match(r'^\d{4}$', c) for c in clist))
check('codes-sorted', clist == sorted(clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-18', multis == ['3163', '3735', '3821', '7370', '7671', '7733', '7747', '7811', '7814',
      '7954', '7960', '8762', '8782', '8874', '9167', '9346', '9375', '9752'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 3048)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('2943-single', legs_of('2943') == ['hu:district:komarom'], str(legs_of('2943')))
check('2943-primary', prims.get('2943') == ['hu:district:komarom'])
check('9764-single', legs_of('9764') == ['hu:district:szombathely'], str(legs_of('9764')))
check('9764-primary', prims.get('9764') == ['hu:district:szombathely'])
check('9167-kept-dual', legs_of('9167') == ['hu:district:csorna', 'hu:district:mosonmagyarovar'],
      str(legs_of('9167')))
check('9167-primary', prims.get('9167') == ['hu:district:mosonmagyarovar'])
for c in ['2242', '3071']:
    check(f'excluded-{c}', c not in clist)
keeps = {'3244': 'hu:district:petervasara', '3603': 'hu:district:ozd',
         '9719': 'hu:district:szombathely', '2945': 'hu:district:kisber',
         '9757': 'hu:district:sarvar', '1007': 'hu:district:13th-district-of-budapest',
         '7016': 'hu:district:sarbogard', '8715': 'hu:district:marcali',
         '8139': 'hu:district:enying'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
