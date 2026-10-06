import csv, re, sys
from collections import Counter
# Slovenia gate. Pins the B16 inline verify-only pass (zero
# changes; 212 areas / 468 codes / 469 legs / 1 multi): 200
# municipalities + 12 urban municipalities, names + ISO
# 3166-2:SI codes + types 212/212 exact vs the ISO table.
# Postal: 467/468 codes exist in GN SI.txt (9246 Razkrižje
# OSM-confirmed, GN's only gap); all 89 GN extras are
# PO-box/large-user/internal/seasonal (9 explicit predali +
# Ljubljana 15xx/1600, Maribor 25xx/26xx, Celje 35xx/3600,
# Kranj 45xx/4600, NG 5600, Koper 65xx/6600, NM 85xx/8600,
# MS 95xx/9600 blocks + 1371/4501/9502 covered-town
# internals), correctly excluded. The single multi 3231
# Grobelno is dual-linked Šentjur primary + Šmarje pri
# Jelšah secondary (sl.wiki lists both Grobelno settlements).
# 30-code Nominatim attribution sample 30/30 (Kanal ob Soči
# abbreviated Kanal by OSM). Hold: 6323 Strunjan seasonal
# post (single GN signal, excluded). Asserts POST-fix state;
# run from repo root: python3 docs/agents/audit/gate_si.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/slovenia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/slovenia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/slovenia-postal-code-areas.csv'
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
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-212', len(rows) == 212, str(len(rows)))
check('muni-200', sum(1 for r in rows if r['type'] == 'municipality') == 200)
check('urban-12', sum(1 for r in rows if r['type'] == 'urban_municipality') == 12)
check('all-l1', all(r['level'] == '1' for r in rows))
check('codes-3digit', all(re.match(r'^\d{3}$', r['code']) for r in rows))
check('codes-unique', len({r['code'] for r in rows}) == 212)
spots = {'si:municipality:dobrovapolhov-gradec': ('Dobrova-Polhov Gradec', '021'),
         'si:urban_municipality:ljubljana': ('Ljubljana', '061'),
         'si:urban_municipality:maribor': ('Maribor', '070'),
         'si:municipality:ankaran': ('Ankaran', '213'),
         'si:municipality:kanal-ob-soci': ('Kanal ob Soči', None)}
for sid, (nm, cd) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and (cd is None or r.get('code') == cd),
          str((r.get('name'), r.get('code'))))
check('codes-468', len(codes) == 468, str(len(codes)))
check('links-469', len(links) == 469, str(len(links)))
check('codes-SI', all(c['country_code'] == 'SI' for c in codes))
clist = [c['code'] for c in codes]
check('codes-4digit', all(re.match(r'^\d{4}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-1', multis == ['3231'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 468)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('3231-dual', legs_of('3231') == ['si:municipality:sentjur', 'si:municipality:smarje-pri-jelsah'],
      str(legs_of('3231')))
check('3231-primary', prims.get('3231') == ['si:municipality:sentjur'])
for c in ['1001', '1500', '1515', '2001', '2500', '3001', '3502', '4001', '4501', '5001', '5600', '6001',
          '6501', '8001', '8501', '9001', '9501', '1371', '6323']:
    check(f'excluded-{c}', c not in clist)
keeps = {'1000': 'si:urban_municipality:ljubljana', '9246': 'si:municipality:razkrizje',
         '9263': 'si:municipality:kuzma', '6320': 'si:municipality:piran',
         '5215': 'si:municipality:kanal-ob-soci', '2273': 'si:municipality:ormoz',
         '1292': 'si:municipality:ig', '4283': 'si:municipality:kranjska-gora'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
