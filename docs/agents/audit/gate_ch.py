import csv, re, sys
from collections import Counter
# Switzerland gate. Pins the B15 fix pass (2 renames, 3 leg moves, 3 leg
# drops, 2 primary flips; 3222 -> 3220 legs, 44 -> 42 multis): 172 areas
# (26 cantons + 146 districts), 3177 codes, 3220 legs.
# Oracles: BFS commune register 2026-01-01 (districts + Moutier transfer),
# swisstopo Ortschaftenverzeichnis AMTOVZ (per-code municipality vote),
# GeoNames CH dump (admin2 corroboration), EN/FR Wikipedia (mergers,
# locality pops), Nominatim (La Cibourg hamlet). Asserts POST-fix state;
# run from repo root: python3 docs/agents/audit/gate_ch.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/switzerland-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/switzerland-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/switzerland-postal-code-areas.csv'
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
# --- tree: 26 cantons + 146 districts ---
check('areas-172', len(rows) == 172, str(len(rows)))
check('l1-26', sum(1 for r in rows if r['level'] == '1') == 26)
check('l2-146', sum(1 for r in rows if r['level'] == '2') == 146)
# --- B15 renames ---
renames = {'ch:district:jura-north-vaudois': 'Jura-Nord vaudois',
           'ch:district:zurich': 'Zürich'}
for sid, name in renames.items():
    r = byid.get(sid)
    check(f'renamed-{sid.split(":")[-1]}', bool(r) and r['name'] == name, str(r))
check('moutier-name-kept', byid.get('ch:district:moutier-district', {}).get('name') == 'Moutier District')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 3177 codes / 3220 legs / 42 multis ---
check('codes-3177', len(codes) == 3177, str(len(codes)))
check('links-3220', len(links) == 3220, str(len(links)))
check('codes-CH', all(c['country_code'] == 'CH' for c in codes))
clist = [c['code'] for c in codes]
check('codes-4digit', all(re.match(r'^\d{4}$', c) for c in clist))
check('codes-range', min(clist) == '1000' and max(clist) == '9658', f'{min(clist)}..{max(clist)}')
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-42', sum(1 for v in have.values() if v > 1) == 42)
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 3177)
# --- B15 op anchors ---
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('2740-multi', legs_of('2740') == ['ch:district:jura-bernois', 'ch:district:moutier-district'],
      str(legs_of('2740')))
check('2740-primary', prims.get('2740') == ['ch:district:moutier-district'])
check('1595-see-lac', legs_of('1595') == ['ch:district:broye-vully', 'ch:district:see-lac'],
      str(legs_of('1595')))
check('1595-primary', prims.get('1595') == ['ch:district:broye-vully'])
check('1015-ouest', legs_of('1015') == ['ch:district:ouest-lausannois'], str(legs_of('1015')))
check('1911-single', legs_of('1911') == ['ch:district:martigny'], str(legs_of('1911')))
check('6825-single', legs_of('6825') == ['ch:district:mendrisio'], str(legs_of('6825')))
check('2333-single', legs_of('2333') == ['ch:district:jura-bernois'], str(legs_of('2333')))
check('3994-goms-primary', prims.get('3994') == ['ch:district:goms'])
check('1958-sierre-primary', prims.get('1958') == ['ch:district:sierre'])
# --- verified keeps (spot) ---
check('1290-keep', legs_of('1290') == ['ch:canton:geneva', 'ch:district:nyon']
      and prims.get('1290') == ['ch:canton:geneva'])
check('6809-keep', legs_of('6809') == ['ch:district:bellinzona', 'ch:district:lugano']
      and prims.get('6809') == ['ch:district:lugano'])
check('9050-triple', legs_of('9050') == ['ch:district:appenzell', 'ch:district:schlatt-haslen',
      'ch:district:schwende-rute'])
check('2037-keep', legs_of('2037') == ['ch:district:boudry', 'ch:district:val-de-ruz'])
check('2616-keep', legs_of('2616') == ['ch:district:jura-bernois', 'ch:district:la-chaux-de-fonds'])
check('8585-keep', prims.get('8585') == ['ch:district:kreuzlingen'])
# --- held-out codes stay absent (box/firm/city-base + Liechtenstein) ---
for c in ['1001', '1200', '3000', '4000', '6000', '8000', '9001', '9485', '9498', '5001', '8070', '9020']:
    check(f'held-out-{c}', c not in clist)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
