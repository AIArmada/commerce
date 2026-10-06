import csv, re, sys
from collections import Counter
# Ireland gate. Pins the B7 verify-only pass (139 Eircode routing keys /
# 141 links) plus the ISO 3166-2:IE tree re-verification (4 provinces +
# 26 counties, single ie:county:dublin). Oracles: ISO 3166-2:IE table
# (prefetched + live Wikipedia), Wikipedia routing-areas table (139 keys,
# 153 post-town rows), Autoaddress routing-keys article (139 keys) + docs
# count anchor (139), WooCommerce issue roster (139 keys), UPU irlEn.pdf,
# OSM spot checks (Blackrock/Kells/Kingscourt). Run from repo root:
# python3 docs/agents/audit/gate_ie.py
A = './packages/addressing/resources/geography/ireland-address-areas.csv'
C = './packages/addressing/resources/geography/ireland-postal-codes.csv'
L = './packages/addressing/resources/geography/ireland-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 4 provinces + 26 counties (ISO 3166-2:IE) ---
check('areas-30', len(rows) == 30, str(len(rows)))
check('provinces-4', sum(1 for r in rows if r['type'] == 'province') == 4)
check('counties-26', sum(1 for r in rows if r['type'] == 'county') == 26)
iso_prov = {'ie:province:connacht': ('Connacht', 'C'), 'ie:province:leinster': ('Leinster', 'L'),
 'ie:province:munster': ('Munster', 'M'), 'ie:province:ulster': ('Ulster', 'U')}
for sid, (name, code) in iso_prov.items():
    r = byid.get(sid)
    check(f'iso-prov-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
iso_cty = {'ie:county:carlow': ('Carlow', 'CW', 'leinster'), 'ie:county:cavan': ('Cavan', 'CN', 'ulster'),
 'ie:county:clare': ('Clare', 'CE', 'munster'), 'ie:county:cork': ('Cork', 'CO', 'munster'),
 'ie:county:donegal': ('Donegal', 'DL', 'ulster'), 'ie:county:dublin': ('Dublin', 'D', 'leinster'),
 'ie:county:galway': ('Galway', 'G', 'connacht'), 'ie:county:kerry': ('Kerry', 'KY', 'munster'),
 'ie:county:kildare': ('Kildare', 'KE', 'leinster'), 'ie:county:kilkenny': ('Kilkenny', 'KK', 'leinster'),
 'ie:county:laois': ('Laois', 'LS', 'leinster'), 'ie:county:leitrim': ('Leitrim', 'LM', 'connacht'),
 'ie:county:limerick': ('Limerick', 'LK', 'munster'), 'ie:county:longford': ('Longford', 'LD', 'leinster'),
 'ie:county:louth': ('Louth', 'LH', 'leinster'), 'ie:county:mayo': ('Mayo', 'MO', 'connacht'),
 'ie:county:meath': ('Meath', 'MH', 'leinster'), 'ie:county:monaghan': ('Monaghan', 'MN', 'ulster'),
 'ie:county:offaly': ('Offaly', 'OY', 'leinster'), 'ie:county:roscommon': ('Roscommon', 'RN', 'connacht'),
 'ie:county:sligo': ('Sligo', 'SO', 'connacht'), 'ie:county:tipperary': ('Tipperary', 'TA', 'munster'),
 'ie:county:waterford': ('Waterford', 'WD', 'munster'), 'ie:county:westmeath': ('Westmeath', 'WH', 'leinster'),
 'ie:county:wexford': ('Wexford', 'WX', 'leinster'), 'ie:county:wicklow': ('Wicklow', 'WW', 'leinster')}
for sid, (name, code, prov) in iso_cty.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '2' and r['parent_source_id'] == f'ie:province:{prov}', str(r))
# Single-Dublin shape hold: no Fingal / South Dublin / Dun Laoghaire split.
check('dublin-single', sum(1 for r in rows if 'dublin' in r['source_id'] or 'Dun Laoghaire' in r['name']
      or 'Fingal' in r['name'] or 'South Dublin' in r['name']) == 1)
for prov, n in [('leinster', 12), ('munster', 6), ('connacht', 5), ('ulster', 3)]:
    have = [r for r in rows if r['parent_source_id'] == f'ie:province:{prov}']
    check(f'prov-{prov}-{n}', len(have) == n, str(len(have)))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal counts: 139 routing keys / 141 links ---
check('codes-139', len(codes) == 139, str(len(codes)))
check('links-141', len(links) == 141, str(len(links)))
check('country-IE', all(c['country_code'] == 'IE' for c in codes))
bad = [c['code'] for c in codes if not re.match(r'^[A-Z]\d[A-Z0-9]$', c['code'])]
check('key-format', not bad, str(bad[:3]))
check('d6w-sole-exception', sorted(c['code'] for c in codes if re.match(r'^[A-Z]\d[A-Z]$', c['code'])) == ['D6W'])
check('codes-unique', len({c['code'] for c in codes}) == 139)
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
badrel = [l for l in links if l['relationship_type'] != 'served_by' or l['is_primary'] not in ('true', 'false')]
check('link-shape', not badrel, str(badrel[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 139 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('every-code-linked', {c['code'] for c in codes} == {l['postcode'] for l in links})
# --- per-county primary counts (26 counties, none codeless) ---
expect = {'carlow': 2, 'cavan': 3, 'clare': 3, 'cork': 23, 'donegal': 3, 'dublin': 34,
 'galway': 6, 'kerry': 4, 'kildare': 7, 'kilkenny': 1, 'laois': 1, 'leitrim': 1,
 'limerick': 3, 'longford': 1, 'louth': 2, 'mayo': 6, 'meath': 6, 'monaghan': 4,
 'offaly': 3, 'roscommon': 3, 'sligo': 2, 'tipperary': 8, 'waterford': 3,
 'westmeath': 2, 'wexford': 4, 'wicklow': 4}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
check('counts-sum-139', sum(have.values()) == 139, str(sum(have.values())))
# --- the two straddler duals (wiki post-county + Autoaddress descriptors) ---
plink = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
got82 = sorted((l['area_source_id'], l['is_primary']) for l in links if l['postcode'] == 'A82')
check('dual-A82', got82 == [('ie:county:cavan', 'false'), ('ie:county:meath', 'true')], str(got82))
got92 = sorted((l['postcode'], l['area_source_id'], l['is_primary']) for l in links if l['postcode'] == 'A92')
check('dual-A92', [(a, p) for _, a, p in got92] == [('ie:county:louth', 'true'), ('ie:county:meath', 'false')], str(got92))
counts = Counter(l['postcode'] for l in links)
check('multi-codes-2', sorted(pc for pc, c in counts.items() if c > 1) == ['A82', 'A92'])
# --- routing-key -> county anchors (first-listed primary per oracle ladder) ---
anchors = {'A41': 'ie:county:dublin', 'A63': 'ie:county:wicklow', 'A75': 'ie:county:monaghan',
 'A91': 'ie:county:louth', 'A94': 'ie:county:dublin', 'A96': 'ie:county:dublin',
 'A98': 'ie:county:wicklow', 'C15': 'ie:county:meath', 'D01': 'ie:county:dublin',
 'D6W': 'ie:county:dublin', 'D24': 'ie:county:dublin', 'E91': 'ie:county:tipperary',
 'F56': 'ie:county:sligo', 'F92': 'ie:county:donegal', 'H18': 'ie:county:monaghan',
 'H91': 'ie:county:galway', 'K67': 'ie:county:dublin', 'K78': 'ie:county:dublin',
 'N41': 'ie:county:leitrim', 'P31': 'ie:county:cork', 'R14': 'ie:county:kildare',
 'R21': 'ie:county:carlow', 'R95': 'ie:county:kilkenny', 'T12': 'ie:county:cork',
 'T56': 'ie:county:cork', 'V35': 'ie:county:limerick', 'V95': 'ie:county:clare',
 'W91': 'ie:county:kildare', 'X91': 'ie:county:waterford', 'Y14': 'ie:county:wicklow',
 'Y35': 'ie:county:wexford'}
for pc, want in anchors.items():
    check(f'anchor-{pc}', plink.get(pc) == want, str(plink.get(pc)))
# --- EOL + trailing newlines (areas LF, postal files CRLF) ---
rawA, rawC, rawL = open(A, 'rb').read(), open(C, 'rb').read(), open(L, 'rb').read()
check('areas-lf-only', rawA.count(b'\r') == 0 and rawA.count(b'\n') == 31,
      f"cr={rawA.count(bytes([13]))} lf={rawA.count(bytes([10]))}")
for raw, label, n in [(rawC, 'codes-crlf', 140), (rawL, 'links-crlf', 142)]:
    crlf, lf = raw.count(b'\r\n'), raw.count(b'\n')
    check(label, crlf == n and crlf == lf, f'crlf={crlf} lf={lf}')
    check(f'{label}-no-lone-cr', raw.count(b'\r') == crlf)
check('areas-trailing-nl', rawA.endswith(b'\n') and not rawA.endswith(b'\r\n'))
check('codes-trailing-crlf', rawC.endswith(b'\r\n'))
check('links-trailing-crlf', rawL.endswith(b'\r\n'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
