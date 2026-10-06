import csv, re, sys
from collections import Counter
# Cuba gate. Pins the B15 inline pass (2 renames, 18 corrupt drops,
# 2 moves, 2 dual-links, 5 fills; 785 -> 772 codes, 788 -> 777 legs,
# 3 -> 5 multis): 184 areas (15 provinces + Isla special + 168
# municipios), 772 codes, 777 legs. Renames: Old Havana ->
# La Habana Vieja (COD-AB + eswiki + Mapanet), Songo en-dash ->
# Songo-La Maya (COD-AB + eswiki + Mapanet). Drops: 5 shape-broken
# (-, -8104, '000 3', 2, 7), 12 zero-garbled 000XX, 1 ghost 20600
# (Pinar block on Santa Clara, zero signals). Moves: 10200 Vieja->
# Centro (Mapanet + parish usage 5:2), 99420 San Antonio del Sur->
# Yateras (Mapanet + 6 directories). Duals: 10600 Cerro(P)+Plaza(S)
# (usage 8:4 + serviciosglobal + OSM vs operator + UPU zone-6),
# 11400 Playa(P)+Marianao(S) (usage 3:1 + OSM vs operator).
# Fills: 22600 La Palma, 53310 Sagua, 73200 Santa Cruz del Sur,
# 97310 Baracoa (all Mapanet + OSM), 19120 Habana del Este (usage
# + OSM). Keeps: Artemisa 35/37/38 scheme (operator lineage over
# stale Mapanet/OSM 32xxx; dual-validity unproven), 3 office
# duals, Habana del Este article (tie -> hold), Sagua 52310.
# Oracles: Correos office API (bundling provenance), Mapanet full
# CU crawl (144 muni / 186 codes), Nominatim (rural reliable,
# Habana-city noisy), Havana Archdiocese parish directory (100+
# usage postcodes), UPU CUB.pdf (5-digit + Habana-6 10600), 6
# Guantanamo directories, eswiki. Asserts POST-fix state; run
# from repo root: python3 docs/agents/audit/gate_cu.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/cuba-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/cuba-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/cuba-postal-code-areas.csv'
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
# --- tree: 15 provinces + 1 special + 168 municipios ---
check('areas-184', len(rows) == 184, str(len(rows)))
check('l1-16', sum(1 for r in rows if r['level'] == '1') == 16)
check('prov-15', sum(1 for r in rows if r['type'] == 'province') == 15)
check('special-1', sum(1 for r in rows if r['type'] == 'special_municipality') == 1)
check('muni-168', sum(1 for r in rows if r['level'] == '2') == 168)
iso = {'01', '03', '04', '05', '06', '07', '08', '09', '10', '11',
       '12', '13', '14', '15', '16', '99'}
check('iso-l1', set(r['code'] for r in rows if r['level'] == '1') == iso)
check('no-02', all(r['code'] != '02' for r in rows))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- B15 renames ---
check('vieja-renamed', byid.get('cu:municipality:la-habana-vieja', {}).get('name') == 'La Habana Vieja')
check('vieja-old-gone', 'cu:municipality:old-havana' not in byid)
check('songo-renamed', byid.get('cu:municipality:songo-la-maya', {}).get('name') == 'Songo-La Maya')
check('hde-kept', byid.get('cu:municipality:habana-del-este', {}).get('name') == 'Habana del Este')
# --- postal: 772 codes / 777 legs / 5 multis ---
check('codes-772', len(codes) == 772, str(len(codes)))
check('links-777', len(links) == 777, str(len(links)))
check('codes-CU', all(c['country_code'] == 'CU' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-5', multis == ['10600', '11400', '24280', '74370', '74440'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 772)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
# --- corrupt drops stay dropped ---
for c in ['-', '-8104', '000 3', '2', '7', '00008', '00015', '00018',
          '00032', '00035', '00037', '00040', '00050', '00060', '00078',
          '00085', '00095', '20600']:
    check(f'dropped-{c}', c not in clist)
# --- moves ---
check('10200-centro', legs_of('10200') == ['cu:municipality:centro-habana'], str(legs_of('10200')))
check('99420-yateras', legs_of('99420') == ['cu:municipality:yateras'], str(legs_of('99420')))
# --- duals ---
check('10600-dual', legs_of('10600') == ['cu:municipality:cerro', 'cu:municipality:plaza-de-la-revolucion'],
      str(legs_of('10600')))
check('10600-primary', prims.get('10600') == ['cu:municipality:cerro'])
check('11400-dual', legs_of('11400') == ['cu:municipality:marianao', 'cu:municipality:playa'],
      str(legs_of('11400')))
check('11400-primary', prims.get('11400') == ['cu:municipality:playa'])
# --- kept office duals ---
check('24280-keep', legs_of('24280') == ['cu:municipality:minas-de-matahambre', 'cu:municipality:vinales']
      and prims.get('24280') == ['cu:municipality:minas-de-matahambre'])
check('74370-keep', legs_of('74370') == ['cu:municipality:nuevitas', 'cu:municipality:sibanicu']
      and prims.get('74370') == ['cu:municipality:nuevitas'])
check('74440-keep', legs_of('74440') == ['cu:municipality:esmeralda', 'cu:municipality:florida']
      and prims.get('74440') == ['cu:municipality:esmeralda'])
# --- fills ---
fills = {'22600': 'cu:municipality:la-palma', '53310': 'cu:municipality:sagua-la-grande',
         '73200': 'cu:municipality:santa-cruz-del-sur', '97310': 'cu:municipality:baracoa',
         '19120': 'cu:municipality:habana-del-este'}
for pc, sid in fills.items():
    check(f'fill-{pc}', legs_of(pc) == [sid], str(legs_of(pc)))
# --- verified keeps (spot) ---
keeps = {'10100': 'cu:municipality:la-habana-vieja', '10400': 'cu:municipality:plaza-de-la-revolucion',
         '10500': 'cu:municipality:diez-de-octubre', '10700': 'cu:municipality:diez-de-octubre',
         '10800': 'cu:municipality:boyeros', '10900': 'cu:municipality:arroyo-naranjo',
         '11000': 'cu:municipality:san-miguel-del-padron', '11100': 'cu:municipality:guanabacoa',
         '11200': 'cu:municipality:regla', '11500': 'cu:municipality:marianao',
         '11700': 'cu:municipality:habana-del-este', '32700': 'cu:municipality:san-jose-de-las-lajas',
         '43300': 'cu:municipality:los-arabos', '52310': 'cu:municipality:sagua-la-grande',
         '55100': 'cu:municipality:cienfuegos', '97600': 'cu:municipality:san-antonio-del-sur'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
