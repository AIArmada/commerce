import csv, sys
from collections import Counter
# Australia gate. Pins the B19 worker pass (547 areas: 8 L1 + 539
# L2; 3165 codes / 4186 links; areas LF, postal pure CRLF):
# oracles ABS ASGS Ed3-2024 gazetted coding index + GN
# place-postcode + wiki rosters + SA geojson/polygons + SA govt
# list + ESCOSA/ABR (network down: offline oracles only; GN
# admin2 systematically wrong for AU, never an LGA oracle).
# TREE: T1 Grant->Southern Limestone Coast Council rename
# (1-Jul-2026, 4 signals; slug kept, transition in progress),
# T2 Lower Eyre Peninsula->Lower Eyre Council (ABR entity +
# govt list; operating-name policy per Roxby keep), T3/T4 ADD
# APY + Maralinga Tjarutja rows (4 signals each; Gerard
# correctly omitted). LINKS (29 cells): 7 flips (5150 Mitcham,
# 5273 Naracoorte, 7469 West Coast, 0862 Barkly, 2335 Singleton,
# 7215 Break O'Day, 0885 Groote primary), 13 secondary adds,
# 9 drops; 4182+13-9=4186. Holds: NT Coomalie/Litchfield types,
# ~89 n<10 primaries, 565 novote office codes, metro slivers,
# single-signal adds. Vintage: ASGS2024/GN predate SLCC+Groote.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_au.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/australia-address-areas.csv'
C = f'{G}/australia-postal-codes.csv'
L = f'{G}/australia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 3166, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 4187, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-547', len(rows) == 547, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-8', sum(1 for r in rows if r['level'] == '1') == 8)
check('L2-539', sum(1 for r in rows if r['level'] == '2') == 539)
iso = {'au:territory:australian-capital-territory': 'ACT', 'au:state:new-south-wales': 'NSW',
       'au:territory:northern-territory': 'NT', 'au:state:queensland': 'QLD',
       'au:state:south-australia': 'SA', 'au:state:tasmania': 'TAS',
       'au:state:victoria': 'VIC', 'au:state:western-australia': 'WA'}
check('iso-8', all(byid.get(s, {}).get('code') == c for s, c in iso.items()))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('T1-slcc', byid.get('au:council:district-council-of-grant', {}).get('name') == 'Southern Limestone Coast Council')
check('T2-lower-eyre', byid.get('au:council:district-council-of-lower-eyre-peninsula', {}).get('name') == 'Lower Eyre Council')
check('T3-apy', byid.get('au:council:anangu-pitjantjatjara-yankunytjatjara', {}).get('parent_source_id') == 'au:state:south-australia')
check('T4-maralinga', byid.get('au:council:maralinga-tjarutja', {}).get('parent_source_id') == 'au:state:south-australia')
check('keep-gerard-out', not any('gerard' in s for s in byid))
check('keep-roxby', byid.get('au:council:roxby-council', {}).get('name') == 'Roxby Council')
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-3165', len(codes) == 3165, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-4186', len(legs) == 4186, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], {})[r['area_source_id']] = r['is_primary']
check('F1-5150', bycode['5150'] == {'au:city:city-of-mitcham': 'true'})
check('F2-5273', bycode['5273'] == {'au:council:naracoorte-lucindale-council': 'true'})
check('F3-7469', bycode['7469'] == {'au:council:west-coast-council': 'true'})
check('F4-0862', bycode['0862']['au:region:barkly-region'] == 'true' and bycode['0862']['au:region:roper-gulf-region'] == 'false')
check('F5-5255', bycode['5255'].get('au:rural_city:rural-city-of-murray-bridge') == 'false')
check('F6-5440', bycode['5440'].get('au:council:district-council-of-peterborough') == 'false')
check('F7-5611', bycode['5611'].get('au:council:district-council-of-kimba') == 'false')
check('F8+F24-4605', bycode['4605'].get('au:shire:aboriginal-shire-of-cherbourg') == 'false' and 'au:region:gympie-region' not in bycode['4605'])
check('F9a-0885', bycode['0885'] == {'au:region:groote-archipelago-region': 'true'})
check('F9b+F12+F13+F14-0822', bycode['0822'].get('au:region:groote-archipelago-region') == 'false' and bycode['0822'].get('au:city:city-of-palmerston') == 'false' and bycode['0822'].get('au:town:town-of-katherine') == 'false' and 'au:city:city-of-darwin' not in bycode['0822'])
check('F10-0872', bycode['0872'].get('au:council:anangu-pitjantjatjara-yankunytjatjara') == 'false')
check('F11-5690', bycode['5690'].get('au:council:maralinga-tjarutja') == 'false')
check('F15-2406', 'au:region:goondiwindi-region' not in bycode['2406'])
check('F16-2870', bycode['2870'].get('au:shire:cabonne-shire') == 'false')
check('F17-3480', bycode['3480'].get('au:shire:shire-of-yarriambiack') == 'false')
check('F18-4740', bycode['4740'].get('au:region:gladstone-region') == 'false')
check('F19-4816', bycode['4816'].get('au:shire:shire-of-mckinlay') == 'false')
check('F20+F21-7030', 'au:council:derwent-valley-council' not in bycode['7030'] and 'au:council:northern-midlands-council' not in bycode['7030'] and bycode['7030'].get('au:council:brighton-council') == 'true')
check('F22-4650', 'au:region:gympie-region' not in bycode['4650'])
check('F23-2631', 'au:shire:bega-valley-shire' not in bycode['2631'])
check('F25-2335', bycode['2335'].get('au:council:singleton-council') == 'true' and bycode['2335'].get('au:city:city-of-cessnock') == 'false')
check('F26-7215', bycode['7215'].get('au:council:break-o-day-council') == 'true' and bycode['7215'].get('au:council:glamorganspring-bay-council') == 'false')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
