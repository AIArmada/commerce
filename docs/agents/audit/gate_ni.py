import csv, re, sys
from collections import Counter
# Nicaragua gate. Pins the B15 inline pass (2 renames, zero leg moves):
# 170 areas (15 departments + 2 autonomous regions + 153 municipios),
# 890 codes, 890 legs, 0 multis. Renames: San Juan de Rio Coco ->
# San Juan del Rio Coco (INIDE gazetteer x2 + COD-AB + enwiki
# article/lists + eswiki body), Waspan -> Waspam (2015 operator map +
# INIDE gazetteer + COD-AB + citypopulation wikilink).
# 2026-10-06: San Juan del Norte -> San Juan de Nicaragua (Ley 434
# Art. 1-2 + consolidated Ley 59 + INIDE anuario2023 + operator
# print-all x4 + enwiki; eswiki stale). Id keeps the pre-2002 slug
# (stable key, same as waspan id).
# Keeps: El Jicaro (operator NS map +
# eswiki + citypopulation), Kukra Hill (operator RAAS map +
# eswiki + citypopulation), Mulukuku accent, Los Remates casing.
# Postal truth: 16/17 archived operator dept maps confirm every
# non-Managua code (Madriz map 404; its 9 codes OSM-verified);
# Nominatim full non-Managua sweep 144/144 incl. 7 flagged codes
# resolved by codigo-postal.org for the bundle (42600 Masatepe,
# 46400 San Marcos, 46600 Santa Teresa, 48500 Tola, 38300
# Macuelizo, 52300 Teustepe, 62400 San Dionisio); Managua 738 =
# Mapanet 609/609 subset + OSM 4 + codigo-postal.org 18 + scheme;
# X0 district labels + 13003 triple-absent (no fill); UPU NIC.pdf
# 5-digit format. Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_ni.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/nicaragua-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/nicaragua-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/nicaragua-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
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
# --- tree: 15 departments + 2 autonomous regions + 153 municipios ---
check('areas-170', len(rows) == 170, str(len(rows)))
check('l1-17', sum(1 for r in rows if r['level'] == '1') == 17)
check('dept-15', sum(1 for r in rows if r['type'] == 'department') == 15)
check('ar-2', sum(1 for r in rows if r['type'] == 'autonomous_region') == 2)
check('muni-153', sum(1 for r in rows if r['level'] == '2') == 153)
iso = {'AN', 'AS', 'BO', 'CA', 'CI', 'CO', 'ES', 'GR', 'JI', 'LE',
       'MD', 'MN', 'MS', 'MT', 'NS', 'RI', 'SJ'}
check('iso-l1', set(r['code'] for r in rows if r['level'] == '1') == iso)
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- B15 renames ---
check('sjrc-renamed', byid.get('ni:municipality:san-juan-del-rio-coco', {}).get('name') == 'San Juan del Río Coco')
check('sjrc-old-gone', 'ni:municipality:san-juan-de-rio-coco' not in byid)
check('waspam-renamed', byid.get('ni:municipality:waspan', {}).get('name') == 'Waspam')
# --- B15 keeps ---
check('sjn-renamed', byid.get('ni:municipality:san-juan-del-norte', {}).get('name') == 'San Juan de Nicaragua')
check('jicaro-kept', byid.get('ni:municipality:el-jicaro', {}).get('name') == 'El Jícaro')
check('kukra-kept', byid.get('ni:municipality:kukra-hill', {}).get('name') == 'Kukra Hill')
# --- postal: 890 codes / 890 legs / 0 multis ---
check('codes-890', len(codes) == 890, str(len(codes)))
check('links-890', len(links) == 890, str(len(links)))
check('codes-NI', all(c['country_code'] == 'NI' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
prims = {l['postcode']: l['area_source_id'] for l in links}
# --- dept bases (operator maps + OSM) ---
bases = {'10000': 'ni:municipality:managua', '21000': 'ni:municipality:leon',
         '25000': 'ni:municipality:chinandega', '31000': 'ni:municipality:esteli',
         '34000': 'ni:municipality:somoto', '37000': 'ni:municipality:ocotal',
         '41000': 'ni:municipality:masaya', '43000': 'ni:municipality:granada',
         '45000': 'ni:municipality:jinotepe', '47000': 'ni:municipality:rivas',
         '51000': 'ni:municipality:boaco', '55000': 'ni:municipality:juigalpa',
         '61000': 'ni:municipality:matagalpa', '65000': 'ni:municipality:jinotega',
         '71000': 'ni:municipality:puerto-cabezas', '81000': 'ni:municipality:bluefields',
         '91000': 'ni:municipality:san-carlos'}
for pc, sid in bases.items():
    check(f'base-{pc}', prims.get(pc) == sid, str(prims.get(pc)))
# --- 7 flagged codes resolved for the bundle ---
flags = {'38300': 'ni:municipality:macuelizo', '42600': 'ni:municipality:masatepe',
         '46400': 'ni:municipality:san-marcos', '46600': 'ni:municipality:santa-teresa',
         '48500': 'ni:municipality:tola', '52300': 'ni:municipality:teustepe',
         '62400': 'ni:municipality:san-dionisio',
         '66600': 'ni:municipality:san-jose-de-bocay', '66700': 'ni:municipality:wiwili-de-jinotega'}
for pc, sid in flags.items():
    check(f'flag-{pc}', prims.get(pc) == sid, str(prims.get(pc)))
# --- rename legs retargeted ---
check('35800-sjrc', prims.get('35800') == 'ni:municipality:san-juan-del-rio-coco')
check('72100-waspam', prims.get('72100') == 'ni:municipality:waspan')
# --- Managua sector anchors (Mapanet/cpo/OSM) ---
sectors = {'11001': 'ni:municipality:managua', '11008': 'ni:municipality:managua',
           '12065': 'ni:municipality:managua', '14082': 'ni:municipality:managua',
           '14319': 'ni:municipality:managua', '11147': 'ni:municipality:managua',
           '12005': 'ni:municipality:managua'}
for pc, sid in sectors.items():
    check(f'sector-{pc}', prims.get(pc) == sid, str(prims.get(pc)))
# --- triple-absent holes stay absent ---
for c in ['13003', '11000', '11100', '11200', '12000', '12100', '13000', '13100', '14000', '14300', '27000']:
    check(f'held-out-{c}', c not in clist)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
