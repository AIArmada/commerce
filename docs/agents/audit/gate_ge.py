import csv, re, sys
from collections import Counter
# Georgia (GE) gate. Pins the B12 re-verification pass: 97 areas (12 L1: 9
# regions + 2 autonomous republics + Tbilisi; 85 L2: 64 municipalities + 17
# districts + 4 cities) + 74 codes / 83 links. Fix vs pre-state: Gali retyped
# municipality→district (Organic Law registers only Akhalgori/Eredvi/Kurta/
# Tighva/Azhara in the occupied territories; Gali matches its 5 pre-2006
# Abkhaz district siblings) + the 6600 leg id follows. Zero link moves, zero
# fills. Oracles: Organic Law on Local Self-Government (matsne.gov.ge),
# municipality register table, ISO 3166-2:GE 12/12, ~370 live gpost.ge finder
# queries (~1,400 cards), Civil Registry office list, addressed sightings,
# 5 wiki ranges. Rejected: GN GE postal dump (404), WPC Georgia (broken),
# zipcode.com.ng/ntdtvjp sub-codes (contradicted by live gpost). Run from repo
# root: python3 docs/agents/audit/gate_ge.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/georgia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/georgia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/georgia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes) ---
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
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-97', len(rows) == 97, str(len(rows)))
check('areas-unique-ids', len(byid) == len(rows))
check('areas-country-GE', all(r['country_code'] == 'GE' for r in rows))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-12', len(l1) == 12)
check('l1-type-split', Counter(r['type'] for r in l1) == {'region': 9, 'autonomous_republic': 2, 'city': 1})
check('l1-parentless', all(not r['parent_source_id'] for r in l1))
check('l2-85', len(l2) == 85, str(len(l2)))
check('l2-type-split', Counter(r['type'] for r in l2) == {'municipality': 64, 'district': 17, 'city': 4}, str(Counter(r['type'] for r in l2)))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
# ISO 3166-2:GE 12/12 (codes, names, categories).
iso = {'ge:autonomous_republic:abkhazia': ('Abkhazia', 'AB'), 'ge:autonomous_republic:adjara': ('Adjara', 'AJ'),
 'ge:region:guria': ('Guria', 'GU'), 'ge:region:imereti': ('Imereti', 'IM'),
 'ge:region:kakheti': ('Kakheti', 'KA'), 'ge:region:kvemo-kartli': ('Kvemo Kartli', 'KK'),
 'ge:region:mtskheta-mtianeti': ('Mtskheta-Mtianeti', 'MM'),
 'ge:region:racha-lechkhumi-and-kvemo-svaneti': ('Racha-Lechkhumi and Kvemo Svaneti', 'RL'),
 'ge:region:samegrelo-zemo-svaneti': ('Samegrelo-Zemo Svaneti', 'SZ'),
 'ge:region:samtskhe-javakheti': ('Samtskhe-Javakheti', 'SJ'),
 'ge:region:shida-kartli': ('Shida Kartli', 'SK'), 'ge:city:tbilisi': ('Tbilisi', 'TB')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code, str(r))
# Per-parent L2 membership counts.
parent = {r['source_id']: r['parent_source_id'] for r in l2}
mem = Counter(parent[r['source_id']] for r in l2)
check('per-parent-l2', dict(mem) == {'ge:autonomous_republic:abkhazia': 7, 'ge:autonomous_republic:adjara': 6,
 'ge:city:tbilisi': 10, 'ge:region:guria': 3, 'ge:region:imereti': 12, 'ge:region:kakheti': 8,
 'ge:region:kvemo-kartli': 7, 'ge:region:mtskheta-mtianeti': 5,
 'ge:region:racha-lechkhumi-and-kvemo-svaneti': 4, 'ge:region:samegrelo-zemo-svaneti': 9,
 'ge:region:samtskhe-javakheti': 6, 'ge:region:shida-kartli': 8}, str(dict(mem)))
# B12 fix pins: Gali is a district; old municipality id gone.
check('pin-gali-district', byid.get('ge:district:gali', {}).get('type') == 'district'
    and byid.get('ge:district:gali', {}).get('parent_source_id') == 'ge:autonomous_republic:abkhazia')
check('gone-ge-municipality-gali', 'ge:municipality:gali' not in byid)
# --- postal: 74 codes / 83 links ---
check('codes-74', len(codes) == 74, str(len(codes)))
check('links-83', len(links) == 83, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == len(clist))
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{4}$', c)]
check('code-format-4digit', not bad, str(bad[:3]))
check('codes-country-GE', all(c['country_code'] == 'GE' for c in codes))
by = {}
prim = {}
seenc = []
for l in links:
    by.setdefault(l['postcode'], []).append(l)
    if l['postcode'] != (seenc[-1] if seenc else None):
        seenc.append(l['postcode'])
    if l['is_primary'] == 'true':
        prim[l['postcode']] = l['area_source_id']
check('links-grouped-codes-order', seenc == clist)
check('every-code-one-primary', len(prim) == len(codes) and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
# Dual links: exact table (6600 ×6 Sokhumi-primary, 7300 ×5 Akhalgori-primary).
dual = {
 '6600': ('ge:district:sokhumi', ['ge:district:gagra', 'ge:district:gali', 'ge:district:gudauta',
    'ge:district:gulripshi', 'ge:district:ochamchire']),
 '7300': ('ge:municipality:akhalgori', ['ge:district:java-district', 'ge:municipality:eredvi',
    'ge:municipality:kurta', 'ge:municipality:tighva']),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
wrongdual = [p for p, (pp, sss) in dual.items()
             if prim.get(p) != pp or sorted(l['area_source_id'] for l in havemulti.get(p, []) if l['is_primary'] == 'false') != sorted(sss)]
check('dual-set-2-exact', set(havemulti) == set(dual), str(sorted(set(havemulti) ^ set(dual))))
check('dual-pairs-exact', not wrongdual, str(wrongdual[:5]))
check('secondary-9', sum(1 for l in links if l['is_primary'] == 'false') == 9)
# Pins: rural one-code-per-municipality anchors, city codes, Tbilisi ×8 → L1.
pins = {'1800': 'ge:municipality:dusheti', '5700': 'ge:municipality:khashuri',
 '4700': 'ge:municipality:kazbegi', '4800': 'ge:municipality:qvareli', '5800': 'ge:municipality:khobi',
 '6004': 'ge:city:batumi', '6010': 'ge:city:batumi', '4608': 'ge:city:kutaisi',
 '4400': 'ge:city:poti', '3700': 'ge:city:rustavi'}
for code, sid in pins.items():
    check(f'pin-{code}', prim.get(code) == sid, str(prim.get(code)))
for code in ['0114', '0159', '0163', '0167', '0178', '0179', '0186', '0190']:
    check(f'pin-tbilisi-{code}', prim.get(code) == 'ge:city:tbilisi', str(prim.get(code)))
check('pin-6600-sokhumi', prim.get('6600') == 'ge:district:sokhumi')
check('pin-7300-akhalgori', prim.get('7300') == 'ge:municipality:akhalgori')
# Per-L1 primary counts (Tbilisi's 8 attach to the L1 city itself).
expc = {'ge:autonomous_republic:abkhazia': 1, 'ge:autonomous_republic:adjara': 7, 'ge:city:tbilisi': 8,
 'ge:region:guria': 3, 'ge:region:imereti': 12, 'ge:region:kakheti': 8, 'ge:region:kvemo-kartli': 7,
 'ge:region:mtskheta-mtianeti': 5, 'ge:region:racha-lechkhumi-and-kvemo-svaneti': 4,
 'ge:region:samegrelo-zemo-svaneti': 9, 'ge:region:samtskhe-javakheti': 6, 'ge:region:shida-kartli': 4}
def top(sid):
    while byid[sid]['level'] != '1':
        sid = byid[sid]['parent_source_id']
    return sid
havec = Counter(top(s) for s in prim.values())
check('per-l1-primaries', dict(havec) == expc, str([(k, havec.get(k, 0), v) for k, v in expc.items() if havec.get(k, 0) != v]))
# Linkless L2 holds: 10 Tbilisi districts (codes link L1) + Azhara (held).
linked = {l['area_source_id'] for l in links}
linkless = [r['source_id'] for r in l2 if r['source_id'] not in linked]
want_linkless = ['ge:district:chughureti', 'ge:district:didube', 'ge:district:gldani',
 'ge:district:isani', 'ge:district:krtsanisi', 'ge:district:mtatsminda', 'ge:district:nadzaladevi',
 'ge:district:saburtalo', 'ge:district:samgori', 'ge:district:vake',
 'ge:municipality:azhara-upper-abkhazia']
check('linkless-11', sorted(linkless) == sorted(want_linkless), str(sorted(set(linkless) ^ set(want_linkless))))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
