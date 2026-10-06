import csv, re, sys
from collections import Counter
# Bhutan gate. Pins the B16 inline fix pass (2 district renames,
# zero count changes — 225 areas / 38 codes / 38 legs, 0 multis):
# 20 districts + 205 gewogs. Renames: Chukha -> Chhukha and
# Lhuntse -> Lhuentse (ISO 3166-2:BT + WP Districts list +
# district-government domains chhukha.gov.bt / lhuentse.gov.bt;
# slugs renamed too). Keeps: Mongar + Pemagatshel spellings
# (district govs mongar.gov.bt / pemagatshel.gov.bt + WP beat
# ISO romanizations Monggar / Pema Gatshel), Trashi Yangtse
# (ISO + bundle beat WP article Trashiyangtse). Gewogs 205/205
# names + parents exact vs WP Gewogs list (Election Commission
# sourced); per-district counts match WP table. Postal: 38
# office base codes, district-level legs by design (finder
# Gewog column held office towns); 37/37 youbianku-listed
# codes exist with matching district attribution (incl.
# cross-block 21104 Lhamoizingkha -> Dagana); 11/38 OSM
# postcode hits all match. 36001 Tsirang kept on bundling
# operator provenance + x0001 HQ pattern (youbianku lacks a
# Tsirang section; OSM/DDG empty; Bhutan Post legacy finder
# 404, UPU BTN.pdf redirects home). Asserts POST-fix state;
# run from repo root: python3 docs/agents/audit/gate_bt.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/bhutan-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/bhutan-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/bhutan-postal-code-areas.csv'
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
check('areas-225', len(rows) == 225, str(len(rows)))
check('districts-20', sum(1 for r in rows if r['level'] == '1') == 20)
check('gewogs-205', sum(1 for r in rows if r['type'] == 'gewog') == 205)
iso = {'33': 'Bumthang', '12': 'Chhukha', '22': 'Dagana', 'GA': 'Gasa', '13': 'Haa', '44': 'Lhuentse',
       '42': 'Mongar', '11': 'Paro', '43': 'Pemagatshel', '23': 'Punakha', '45': 'Samdrup Jongkhar',
       '14': 'Samtse', '31': 'Sarpang', '15': 'Thimphu', '41': 'Trashigang', 'TY': 'Trashi Yangtse',
       '32': 'Trongsa', '21': 'Tsirang', '24': 'Wangdue Phodrang', '34': 'Zhemgang'}
got = {r['code']: r['name'] for r in rows if r['level'] == '1'}
check('iso-l1', got == iso, str({k: got.get(k) for k in iso if got.get(k) != iso[k]}))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- B16 renames (name + slug) ---
check('chhukha-renamed', byid.get('bt:district:chhukha', {}).get('name') == 'Chhukha')
check('chukha-gone', 'bt:district:chukha' not in byid)
check('lhuentse-renamed', byid.get('bt:district:lhuentse', {}).get('name') == 'Lhuentse')
check('lhuntse-gone', 'bt:district:lhuntse' not in byid)
check('chhukha-gewogs-11', sum(1 for r in rows if r['parent_source_id'] == 'bt:district:chhukha') == 11)
check('lhuentse-gewogs-8', sum(1 for r in rows if r['parent_source_id'] == 'bt:district:lhuentse') == 8)
check('mongar-kept', byid.get('bt:district:mongar', {}).get('name') == 'Mongar')
check('pemagatshel-kept', byid.get('bt:district:pemagatshel', {}).get('name') == 'Pemagatshel')
check('ty-kept', byid.get('bt:district:trashi-yangtse', {}).get('name') == 'Trashi Yangtse')
# --- postal: 38 codes / 38 legs / 0 multis ---
check('codes-38', len(codes) == 38, str(len(codes)))
check('links-38', len(links) == 38, str(len(links)))
check('codes-BT', all(c['country_code'] == 'BT' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
check('legs-district-level', all(byid[l['area_source_id']]['level'] == '1' for l in links))
prims = {l['postcode']: l['area_source_id'] for l in links}
spots = {'11001': 'bt:district:thimphu', '12002': 'bt:district:paro', '13001': 'bt:district:punakha',
         '21005': 'bt:district:chhukha', '21101': 'bt:district:chhukha', '21104': 'bt:district:dagana',
         '31101': 'bt:district:sarpang', '35001': 'bt:district:dagana', '36001': 'bt:district:tsirang',
         '41104': 'bt:district:samdrup-jongkhar', '42002': 'bt:district:trashigang',
         '44102': 'bt:district:pemagatshel', '45001': 'bt:district:lhuentse',
         '46002': 'bt:district:trashi-yangtse'}
for pc, sid in spots.items():
    check(f'spot-{pc}', prims.get(pc) == sid, str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
