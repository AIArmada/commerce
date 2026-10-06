import csv, re, sys
from collections import Counter
# Ukraine gate. Pins the B15 integrator pass over the worker verdict (1313
# ops: 701 drops, 608 adds, 4 set_primary; 26674 -> 26581 legs, 92 -> 2
# multis): 163 areas (27 L1 + 136 raions), 26579 codes, 26581 legs.
# Integrator overrides: 82563 + 47431 HELD as genuine cross-raion codes
# (ukwiki infobox postcodes), 90124 FLIPPED to berehove-sole 2026-10-06
# (Ukrposhta operator street DB + PCIU + Siltse infobox + COD-AB; old
# Irshavskyi SPLIT — Kamianska->Berehove — so the B15 reform-wholly
# premise was false); all other worker multi drops + all 607 singles moves
# applied after 16/16 sample verification (8 infobox-pc exact, 8
# reform+block, zero contradictions).
# Oracles: ISO 3166-2:UA (27 L1), 2020 reform mapping (EN/UK Wikipedia old->
# new raion pages + VRU 807-IX hromada compositions), ukwiki village
# infoboxes (postcode + raion, 20+ pages), Nominatim reverse (acc=4 rows),
# GeoNames UA postal dump (place votes), citypopulation PCIU pages.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_ua.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/ukraine-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/ukraine-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/ukraine-postal-code-areas.csv'
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
# --- tree: 24 oblasts + 2 cities + 1 republic + 136 raions ---
check('areas-163', len(rows) == 163, str(len(rows)))
check('l1-27', sum(1 for r in rows if r['level'] == '1') == 27)
check('l2-136', sum(1 for r in rows if r['level'] == '2') == 136)
check('l1-types', Counter(r['type'] for r in rows if r['level'] == '1') == {'oblast': 24, 'city': 2, 'republic': 1},
      str(Counter(r['type'] for r in rows if r['level'] == '1')))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 26579 codes / 26581 legs / 2 multis ---
check('codes-26579', len(codes) == 26579, str(len(codes)))
check('links-26581', len(links) == 26581, str(len(links)))
check('codes-UA', all(c['country_code'] == 'UA' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-2', multis == ['47431', '82563'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 26579)
# --- integrator holds: genuine cross-raion codes keep both legs ---
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('82563-held', legs_of('82563') == ['ua:raion:sambir', 'ua:raion:stryi'], str(legs_of('82563')))
check('82563-primary', prims.get('82563') == ['ua:raion:stryi'])
check('47431-held', legs_of('47431') == ['ua:raion:kremenets', 'ua:raion:ternopil'], str(legs_of('47431')))
check('47431-primary', prims.get('47431') == ['ua:raion:ternopil'])
# --- integrator flip: 90124 -> berehove sole ---
check('90124-flip', legs_of('90124') == ['ua:raion:berehove'], str(legs_of('90124')))
# --- 2026-10-06 retry: 7 infobox gaps proven by the Ukrposhta operator API + GN ---
for pc, want in [('27019', 'ua:raion:novoukrainka'), ('26135', 'ua:raion:holovanivsk'),
                 ('26230', 'ua:raion:novoukrainka'), ('52029', 'ua:raion:dnipro'),
                 ('08146', 'ua:raion:fastiv'), ('84423', 'ua:raion:kramatorsk'),
                 ('64372', 'ua:raion:izium')]:
    check(f'gap-{pc}', legs_of(pc) == [want], str(legs_of(pc)))
# --- worker specials (integrator-verified) ---
check('41671-konotop', legs_of('41671') == ['ua:raion:konotop'], str(legs_of('41671')))
check('32011-khmelnytskyi', legs_of('32011') == ['ua:raion:khmelnytskyi'], str(legs_of('32011')))
check('32340-kampod', legs_of('32340') == ['ua:raion:kamianets-podilskyi'], str(legs_of('32340')))
check('81016-yavoriv', legs_of('81016') == ['ua:raion:yavoriv'], str(legs_of('81016')))
# --- singles-move anchors (infobox-pc exact sample) ---
anchors = {'08411': 'ua:raion:boryspil', '78119': 'ua:raion:kolomyia',
           '16262': 'ua:raion:novhorod-siverskyi', '28330': 'ua:raion:oleksandriia',
           '08623': 'ua:raion:fastiv', '24463': 'ua:raion:haisyn',
           '28625': 'ua:raion:kropyvnytskyi', '67633': 'ua:raion:odesa',
           '27019': 'ua:raion:novoukrainka', '26135': 'ua:raion:holovanivsk',
           '84423': 'ua:raion:kramatorsk', '63744': 'ua:raion:kupiansk',
           '26230': 'ua:raion:novoukrainka', '52029': 'ua:raion:dnipro',
           '64372': 'ua:raion:izium', '08146': 'ua:raion:fastiv'}
for pc, sid in anchors.items():
    check(f'move-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
# --- multi-drop anchors (reform-wholly + unique-anchor sample) ---
drops = {'10416': 'ua:raion:korosten', '26000': 'ua:raion:novoukrainka',
         '30334': 'ua:raion:shepetivka', '42600': 'ua:raion:okhtyrka',
         '93302': 'ua:raion:siverskodonetsk', '52414': 'ua:raion:dnipro',
         '52532': 'ua:raion:synelnykove', '81092': 'ua:raion:yavoriv',
         '80320': 'ua:raion:lviv', '32014': 'ua:raion:khmelnytskyi'}
for pc, sid in drops.items():
    check(f'drop-{pc}', legs_of(pc) == [sid], str(legs_of(pc)))
# --- verified keeps (untouched codes spot) ---
check('01001-kyiv', prims.get('01001') == ['ua:city:kyiv'])
check('79000-lviv', prims.get('79000') == ['ua:raion:lviv'], str(prims.get('79000')))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
