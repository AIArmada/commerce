import csv, re, sys
from collections import Counter
# Austria gate. B12 fix-and-fill 2026-10-04: 102 areas / 2501 codes / 2623 links.
# Fresh GeoNames AT dump (19225 rows / 2501 distinct codes) matches the
# bundled code set exactly (zero diff both ways, 1000-9992, 4-digit per
# UPU AUT). 16 primary retargets + 1 added secondary off the bundled
# row-majority build: the St. Poelten batch (3100/3104/3105/3107/3109/
# 3140/3151 -> statutory city; WP city infobox + Nominatim + GN 18:0/
# 5:1/2:0/4:0/1:0/4:2/13:0), the Wiener Neustadt batch (2700/2703/2705/
# 2706/2707 -> statutory city; de.wp infobox lists 2700+2705, Nominatim
# 2700 = city, GN city-unanimous, district rows displaced to true codes
# 2721/2722/2801/2493), 1140+1210 -> Vienna state (Nominatim Penzing/
# Floridsdorf, 1xxx=Vienna system, GN district rows displaced to true
# codes 3001/3002/2100-2103), 2231 -> Gaenserndorf (Nominatim Strasshof,
# de.wp Bockfliess=2213), 2680 -> Neunkirchen (Nominatim Semmering-Kurort,
# GN Styrian rows displaced to true codes 8680/8684/8685). Added link:
# 3140 district:st-polten secondary (2 genuine Boeheimkirchen-village
# rows). Holds: 2751/2752 + 3385 district (Nominatim + GN majority beat
# loose de.wp city lists), genuine-tie PLZ-map breaks (2381/2413/2460/
# 2473/2485/2663/3973/4550/5562/6182/6314/6850/7033/7212/8291/8293/8924/
# 8974), 2702-closed/2704-de.wp-only absent by the unverified-exclusion
# precedent. Tree 93/93 L2 exact vs WP Districts of Austria (code/name/
# city-type) + STAT Murtal/Bruck-Muerzzuschlag confirmations; 901 Vienna
# is L1 by design. Pre-fix this gate FAILS exactly on the 16 moved
# primaries + added link (links-2623, secondary-122, dual-set-117-exact,
# dual-pairs-exact, added-3140-secondary, move pins, per-state, per-area
# counts for the 10 touched areas); everything else passes pre-fix.
# Run from repo root: python3 docs/agents/audit/gate_at.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/austria-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/austria-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/austria-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes) ---
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-CRLF-only', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 2502)
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF-only', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 2624)
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 9 states + 79 districts + 14 statutory cities ---
check('areas-102', len(rows) == 102, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-9-states', len(l1) == 9 and all(r['type'] == 'state' and not r['parent_source_id'] for r in l1))
check('l1-iso-codes', sorted(r['code'] for r in l1) == ['1', '2', '3', '4', '5', '6', '7', '8', '9'])
check('l1-iso-names', sorted(r['name'] for r in l1) == ['Burgenland', 'Carinthia', 'Lower Austria', 'Salzburg', 'Styria', 'Tyrol', 'Upper Austria', 'Vienna', 'Vorarlberg'], str(sorted(r['name'] for r in l1)))
check('l2-93', len(l2) == 93 and Counter(r['type'] for r in l2) == {'district': 79, 'statutory_city': 14}, str(Counter(r['type'] for r in l2)))
check('l2-codes', sorted(r['code'] for r in l2) == ['101', '102', '103', '104', '105', '106', '107', '108', '109', '201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '301', '302', '303', '304', '305', '306', '307', '308', '309', '310', '311', '312', '313', '314', '315', '316', '317', '318', '319', '320', '321', '322', '323', '325', '401', '402', '403', '404', '405', '406', '407', '408', '409', '410', '411', '412', '413', '414', '415', '416', '417', '418', '501', '502', '503', '504', '505', '506', '601', '603', '606', '610', '611', '612', '614', '616', '617', '620', '621', '622', '623', '701', '702', '703', '704', '705', '706', '707', '708', '709', '801', '802', '803', '804'])
check('no-wien-umgebung-324', '324' not in {r['code'] for r in l2})
check('no-pre2012-styria', not ({r['code'] for r in l2} & {'602', '604', '605', '607', '608', '609', '613', '615'}))
check('styria-merged-620-623', all(byid[s]['parent_source_id'] == 'at:state:styria' for s in ['at:district:murtal', 'at:district:bruck-murzzuschlag', 'at:district:hartberg-furstenfeld', 'at:district:sudoststeiermark']))
check('areas-unique-ids', len(byid) == 102)
check('areas-country-AT', all(r['country_code'] == 'AT' for r in rows))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
check('vienna-childless', all(r['parent_source_id'] != 'at:state:vienna' for r in l2))
mem = Counter(r['parent_source_id'] for r in l2)
check('state-membership', dict(mem) == {'at:state:burgenland': 9, 'at:state:carinthia': 10, 'at:state:lower-austria': 24, 'at:state:upper-austria': 18, 'at:state:salzburg': 6, 'at:state:styria': 13, 'at:state:tyrol': 9, 'at:state:vorarlberg': 4}, str(dict(mem)))
check('city-type-spots', byid['at:statutory_city:st-polten']['code'] == '302' and byid['at:statutory_city:wiener-neustadt']['code'] == '304' and byid['at:district:st-polten']['code'] == '319' and byid['at:district:wiener-neustadt']['code'] == '323' and byid['at:district:leoben']['type'] == 'district' and byid['at:statutory_city:rust']['code'] == '102')
check('iso-name-spots', byid['at:district:sudoststeiermark']['name'] == 'Südoststeiermark' and byid['at:district:bruck-murzzuschlag']['name'] == 'Bruck-Mürzzuschlag' and byid['at:district:hartberg-furstenfeld']['name'] == 'Hartberg-Fürstenfeld' and byid['at:statutory_city:klagenfurt-am-worthersee']['name'] == 'Klagenfurt am Wörthersee')
# --- postal: 2501 codes / 2623 links ---
check('codes-2501', len(codes) == 2501, str(len(codes)))
check('links-2623', len(links) == 2623, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 2501)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{4}$', c)]
check('code-format-4-digit', not bad, str(bad[:3]))
check('codes-range', min(clist) == '1000' and max(clist) == '9992', f'{min(clist)}-{max(clist)}')
check('codes-country-AT', all(c['country_code'] == 'AT' for c in codes))
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
check('primary-first', all(v[0]['is_primary'] == 'true' for v in by.values()))
check('every-code-one-primary', len(prim) == 2501 and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('secondary-122', sum(1 for l in links if l['is_primary'] == 'false') == 122)
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('links-l1-127', sum(1 for l in links if byid[l['area_source_id']]['level'] == '1') == 127)
check('links-l2-2496', sum(1 for l in links if byid[l['area_source_id']]['level'] == '2') == 2496)
check('l1-links-vienna-only', {l['area_source_id'] for l in links if byid[l['area_source_id']]['level'] == '1'} == {'at:state:vienna'})
# --- Vienna 1xxx block: 128 codes, only the 1300 airport code escapes ---
ones = [c for c in clist if c.startswith('1')]
check('vienna-block-128', len(ones) == 128 and min(ones) == '1000' and max(ones) == '1610')
check('vienna-block-primary', all(prim[c] == 'at:state:vienna' for c in ones if c != '1300') and prim['1300'] == 'at:district:bruck-an-der-leitha')
check('vienna-single-linked', all(len(by[c]) == 1 for c in ones if prim[c] == 'at:state:vienna'))
check('vienna-only-1xxx', all(p.startswith('1') for p, s in prim.items() if s == 'at:state:vienna'))
# --- 117 multi-linked codes: exact (primary, secondaries) table ---
dual = {
 '2094': ('at:district:horn', ('at:district:waidhofen-an-der-thaya',)),
 '2095': ('at:district:horn', ('at:district:waidhofen-an-der-thaya',)),
 '2115': ('at:district:korneuburg', ('at:district:mistelbach',)),
 '2116': ('at:district:mistelbach', ('at:district:korneuburg',)),
 '2440': ('at:district:bruck-an-der-leitha', ('at:district:baden',)),
 '2813': ('at:district:wiener-neustadt', ('at:district:neunkirchen',)),
 '2822': ('at:district:wiener-neustadt', ('at:district:neunkirchen',)),
 '2851': ('at:district:neunkirchen', ('at:district:wiener-neustadt',)),
 '2852': ('at:district:wiener-neustadt', ('at:district:oberwart', 'at:district:neunkirchen')),
 '2871': ('at:district:neunkirchen', ('at:district:hartberg-furstenfeld',)),
 '3004': ('at:district:tulln', ('at:district:st-polten',)),
 '3122': ('at:district:melk', ('at:district:krems',)),
 '3140': ('at:statutory_city:st-polten', ('at:district:st-polten',)),
 '3150': ('at:district:st-polten', ('at:district:lilienfeld',)),
 '3214': ('at:district:scheibbs', ('at:district:st-polten',)),
 '3223': ('at:district:scheibbs', ('at:district:lilienfeld',)),
 '3242': ('at:district:melk', ('at:district:scheibbs',)),
 '3252': ('at:district:scheibbs', ('at:district:melk',)),
 '3340': ('at:statutory_city:waidhofen-an-der-ybbs', ('at:district:amstetten',)),
 '3341': ('at:district:amstetten', ('at:district:scheibbs',)),
 '3430': ('at:district:tulln', ('at:district:korneuburg',)),
 '3443': ('at:district:tulln', ('at:district:st-polten',)),
 '3454': ('at:district:tulln', ('at:district:st-polten',)),
 '3500': ('at:statutory_city:krems-an-der-donau', ('at:district:krems',)),
 '3524': ('at:district:zwettl', ('at:district:krems',)),
 '3622': ('at:district:krems', ('at:district:zwettl',)),
 '3701': ('at:district:tulln', ('at:district:hollabrunn',)),
 '3713': ('at:district:horn', ('at:district:hollabrunn',)),
 '3834': ('at:district:waidhofen-an-der-thaya', ('at:district:gmund',)),
 '3923': ('at:district:zwettl', ('at:district:gmund',)),
 '3925': ('at:district:zwettl', ('at:district:freistadt',)),
 '3932': ('at:district:gmund', ('at:district:zwettl',)),
 '4083': ('at:district:eferding', ('at:district:grieskirchen',)),
 '4085': ('at:district:scharding', ('at:district:rohrbach',)),
 '4175': ('at:district:urfahr-umgebung', ('at:district:rohrbach',)),
 '4183': ('at:district:urfahr-umgebung', ('at:district:rohrbach',)),
 '4184': ('at:district:rohrbach', ('at:district:urfahr-umgebung',)),
 '4192': ('at:district:urfahr-umgebung', ('at:district:freistadt',)),
 '4193': ('at:district:urfahr-umgebung', ('at:district:freistadt',)),
 '4210': ('at:district:urfahr-umgebung', ('at:district:freistadt',)),
 '4281': ('at:district:freistadt', ('at:district:perg',)),
 '4282': ('at:district:freistadt', ('at:district:perg',)),
 '4284': ('at:district:freistadt', ('at:district:perg',)),
 '4311': ('at:district:perg', ('at:district:freistadt',)),
 '4372': ('at:district:perg', ('at:district:zwettl',)),
 '4521': ('at:district:steyr-land', ('at:district:linz-land',)),
 '4531': ('at:district:linz-land', ('at:district:steyr-land',)),
 '4532': ('at:district:steyr-land', ('at:district:linz-land',)),
 '4594': ('at:district:steyr-land', ('at:district:kirchdorf',)),
 '4600': ('at:statutory_city:wels', ('at:district:wels-land',)),
 '4612': ('at:district:eferding', ('at:district:wels-land',)),
 '4632': ('at:district:wels-land', ('at:district:grieskirchen',)),
 '4680': ('at:district:grieskirchen', ('at:district:ried',)),
 '4692': ('at:district:vocklabruck', ('at:district:grieskirchen',)),
 '4702': ('at:district:grieskirchen', ('at:district:wels-land',)),
 '4730': ('at:district:grieskirchen', ('at:district:eferding',)),
 '4731': ('at:district:eferding', ('at:district:grieskirchen',)),
 '4732': ('at:district:grieskirchen', ('at:district:eferding',)),
 '4772': ('at:district:ried', ('at:district:scharding',)),
 '4866': ('at:district:vocklabruck', ('at:district:salzburg-umgebung',)),
 '4932': ('at:district:ried', ('at:district:braunau',)),
 '4980': ('at:district:ried', ('at:district:scharding',)),
 '5204': ('at:district:salzburg-umgebung', ('at:district:vocklabruck',)),
 '5651': ('at:district:zell-am-see', ('at:district:st-johann-im-pongau',)),
 '6020': ('at:statutory_city:innsbruck', ('at:district:innsbruck-land',)),
 '8010': ('at:statutory_city:graz', ('at:district:graz-umgebung',)),
 '8044': ('at:district:graz-umgebung', ('at:statutory_city:graz',)),
 '8047': ('at:district:graz-umgebung', ('at:statutory_city:graz',)),
 '8052': ('at:district:graz-umgebung', ('at:statutory_city:graz',)),
 '8054': ('at:district:graz-umgebung', ('at:statutory_city:graz',)),
 '8061': ('at:district:graz-umgebung', ('at:district:weiz',)),
 '8072': ('at:district:graz-umgebung', ('at:district:leibnitz',)),
 '8074': ('at:district:graz-umgebung', ('at:statutory_city:graz',)),
 '8081': ('at:district:sudoststeiermark', ('at:district:leibnitz', 'at:district:graz-umgebung')),
 '8102': ('at:district:graz-umgebung', ('at:district:weiz',)),
 '8113': ('at:district:graz-umgebung', ('at:district:voitsberg',)),
 '8152': ('at:district:voitsberg', ('at:district:graz-umgebung',)),
 '8153': ('at:district:voitsberg', ('at:district:graz-umgebung',)),
 '8200': ('at:district:weiz', ('at:district:graz-umgebung',)),
 '8262': ('at:district:hartberg-furstenfeld', ('at:district:weiz',)),
 '8265': ('at:district:hartberg-furstenfeld', ('at:district:weiz',)),
 '8292': ('at:district:hartberg-furstenfeld', ('at:district:gussing',)),
 '8311': ('at:district:weiz', ('at:district:sudoststeiermark',)),
 '8312': ('at:district:sudoststeiermark', ('at:district:hartberg-furstenfeld', 'at:district:weiz')),
 '8322': ('at:district:sudoststeiermark', ('at:district:weiz',)),
 '8362': ('at:district:hartberg-furstenfeld', ('at:district:sudoststeiermark',)),
 '8410': ('at:district:leibnitz', ('at:district:graz-umgebung',)),
 '8421': ('at:district:leibnitz', ('at:district:sudoststeiermark',)),
 '8443': ('at:district:leibnitz', ('at:district:deutschlandsberg',)),
 '8444': ('at:district:leibnitz', ('at:district:deutschlandsberg',)),
 '8455': ('at:district:leibnitz', ('at:district:deutschlandsberg',)),
 '8504': ('at:district:deutschlandsberg', ('at:district:graz-umgebung', 'at:district:leibnitz')),
 '8521': ('at:district:deutschlandsberg', ('at:district:leibnitz',)),
 '8544': ('at:district:deutschlandsberg', ('at:district:leibnitz',)),
 '8561': ('at:district:voitsberg', ('at:district:graz-umgebung',)),
 '8563': ('at:district:voitsberg', ('at:district:deutschlandsberg',)),
 '8673': ('at:district:weiz', ('at:district:hartberg-furstenfeld',)),
 '8822': ('at:district:murau', ('at:district:st-veit-an-der-glan',)),
 '8850': ('at:district:murau', ('at:district:st-veit-an-der-glan',)),
 '8934': ('at:district:liezen', ('at:district:steyr-land',)),
 '9020': ('at:statutory_city:klagenfurt-am-worthersee', ('at:district:klagenfurt-land',)),
 '9061': ('at:district:klagenfurt-land', ('at:statutory_city:klagenfurt-am-worthersee',)),
 '9063': ('at:district:klagenfurt-land', ('at:district:st-veit-an-der-glan',)),
 '9064': ('at:district:klagenfurt-land', ('at:district:st-veit-an-der-glan', 'at:district:volkermarkt')),
 '9073': ('at:district:klagenfurt-land', ('at:statutory_city:klagenfurt-am-worthersee',)),
 '9112': ('at:district:volkermarkt', ('at:district:wolfsberg',)),
 '9131': ('at:district:klagenfurt-land', ('at:district:volkermarkt',)),
 '9220': ('at:district:villach-land', ('at:district:klagenfurt-land',)),
 '9371': ('at:district:st-veit-an-der-glan', ('at:district:volkermarkt',)),
 '9463': ('at:district:wolfsberg', ('at:district:murtal',)),
 '9551': ('at:district:feldkirchen', ('at:district:villach-land',)),
 '9555': ('at:district:feldkirchen', ('at:district:st-veit-an-der-glan',)),
 '9556': ('at:district:st-veit-an-der-glan', ('at:district:feldkirchen',)),
 '9560': ('at:district:feldkirchen', ('at:district:klagenfurt-land',)),
 '9571': ('at:district:feldkirchen', ('at:district:st-veit-an-der-glan',)),
 '9586': ('at:district:villach-land', ('at:statutory_city:villach',)),
 '9701': ('at:district:spittal-an-der-drau', ('at:district:villach-land',)),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
wrongdual = [p for p, (pp, ss) in dual.items()
             if {l['area_source_id'] for l in havemulti.get(p, [])} != ({pp} | set(ss)) or prim.get(p) != pp]
check('dual-set-117-exact', set(havemulti) == set(dual), str(sorted(set(havemulti) ^ set(dual))[:5]))
check('dual-pairs-exact', not wrongdual, str(wrongdual[:5]))
check('triples-5', sorted(p for p, v in havemulti.items() if len(v) > 2) == ['2852', '8081', '8312', '8504', '9064'])
check('added-3140-secondary', [l['area_source_id'] for l in by.get('3140', [])] == ['at:statutory_city:st-polten', 'at:district:st-polten'] and by['3140'][0]['is_primary'] == 'true' and by['3140'][1]['is_primary'] == 'false')
# --- B12 move pins: 16 retargeted primaries ---
moves = {
 '3100': 'at:statutory_city:st-polten', '3104': 'at:statutory_city:st-polten',
 '3105': 'at:statutory_city:st-polten', '3107': 'at:statutory_city:st-polten',
 '3109': 'at:statutory_city:st-polten', '3140': 'at:statutory_city:st-polten',
 '3151': 'at:statutory_city:st-polten', '2700': 'at:statutory_city:wiener-neustadt',
 '2703': 'at:statutory_city:wiener-neustadt', '2705': 'at:statutory_city:wiener-neustadt',
 '2706': 'at:statutory_city:wiener-neustadt', '2707': 'at:statutory_city:wiener-neustadt',
 '1140': 'at:state:vienna', '1210': 'at:state:vienna',
 '2231': 'at:district:ganserndorf', '2680': 'at:district:neunkirchen',
}
for pc, sid in moves.items():
    check(f'move-{pc}', prim.get(pc) == sid, f'{pc} -> {prim.get(pc)}')
check('moved-single-except-3140', all(len(by[pc]) == 1 for pc in moves if pc != '3140') and len(by['3140']) == 2)
# --- holds: methodology-consistent assignments kept on stability ---
check('hold-2751-district', [l['area_source_id'] for l in by.get('2751', [])] == ['at:district:wiener-neustadt'])
check('hold-2752-district', [l['area_source_id'] for l in by.get('2752', [])] == ['at:district:wiener-neustadt'])
check('hold-3385-district', [l['area_source_id'] for l in by.get('3385', [])] == ['at:district:st-polten'])
check('hold-tie-2381-stpolten', prim.get('2381') == 'at:district:st-polten' and len(by.get('2381', [])) == 1)
check('hold-tie-7212-wrneustadt', prim.get('7212') == 'at:district:wiener-neustadt' and len(by.get('7212', [])) == 1)
check('hold-tie-5562-tamsweg', prim.get('5562') == 'at:district:tamsweg' and len(by.get('5562', [])) == 1)
check('hold-tie-7033-mattersburg', prim.get('7033') == 'at:district:mattersburg' and len(by.get('7033', [])) == 1)
check('hold-tie-8291-hartberg', prim.get('8291') == 'at:district:hartberg-furstenfeld' and len(by.get('8291', [])) == 1)
check('hold-tie-8293-hartberg', prim.get('8293') == 'at:district:hartberg-furstenfeld' and len(by.get('8293', [])) == 1)
check('hold-tie-8924-liezen', prim.get('8924') == 'at:district:liezen' and len(by.get('8924', [])) == 1)
check('hold-tie-8974-liezen', prim.get('8974') == 'at:district:liezen' and len(by.get('8974', [])) == 1)
check('hold-tie-4550-kirchdorf', prim.get('4550') == 'at:district:kirchdorf' and len(by.get('4550', [])) == 1)
check('hold-2702-2704-absent', '2702' not in prim and '2704' not in prim)
check('hold-8471-8565-9104-absent', '8471' not in prim and '8565' not in prim and '9104' not in prim)
# --- seat + quirk pins ---
check('pin-7000-eisenstadt', prim.get('7000') == 'at:statutory_city:eisenstadt')
check('pin-3101-stpolten-city', prim.get('3101') == 'at:statutory_city:st-polten')
check('pin-1300-bruck', prim.get('1300') == 'at:district:bruck-an-der-leitha')
check('pin-9000-villach', prim.get('9000') == 'at:statutory_city:villach')
check('pin-9992-lienz', prim.get('9992') == 'at:district:lienz')
check('pin-1000-vienna', prim.get('1000') == 'at:state:vienna')
# --- cross-range exceptions: every out-of-range primary, exact set ---
parent = {r['source_id']: (r['source_id'] if r['level'] == '1' else r['parent_source_id']) for r in rows}
state_of = {p: parent[s] for p, s in prim.items()}
exp_range = {'1': 'at:state:vienna', '2': 'at:state:lower-austria', '3': 'at:state:lower-austria',
 '4': 'at:state:upper-austria', '7': 'at:state:burgenland', '8': 'at:state:styria'}
odd = sorted((p, s) for p, s in state_of.items()
             if p[0] in exp_range and s != exp_range[p[0]]
             or p[0] == '5' and s not in {'at:state:salzburg', 'at:state:upper-austria'}
             or p[0] == '6' and s not in {'at:state:tyrol', 'at:state:vorarlberg'}
             or p[0] == '9' and s not in {'at:state:carinthia', 'at:state:tyrol'})
exp_odd = [('1300', 'at:state:lower-austria'), ('2421', 'at:state:burgenland'), ('2422', 'at:state:burgenland'), ('2423', 'at:state:burgenland'), ('2424', 'at:state:burgenland'), ('2425', 'at:state:burgenland'), ('2443', 'at:state:burgenland'), ('2445', 'at:state:burgenland'), ('2462', 'at:state:burgenland'), ('2474', 'at:state:burgenland'), ('2475', 'at:state:burgenland'), ('2491', 'at:state:burgenland'), ('3334', 'at:state:upper-austria'), ('3335', 'at:state:upper-austria'), ('4300', 'at:state:lower-austria'), ('4303', 'at:state:lower-austria'), ('4392', 'at:state:lower-austria'), ('4431', 'at:state:lower-austria'), ('4432', 'at:state:lower-austria'), ('4441', 'at:state:lower-austria'), ('4482', 'at:state:lower-austria'), ('7212', 'at:state:lower-austria'), ('7421', 'at:state:styria'), ('8380', 'at:state:burgenland'), ('8382', 'at:state:burgenland'), ('8383', 'at:state:burgenland'), ('8384', 'at:state:burgenland'), ('8385', 'at:state:burgenland'), ('9323', 'at:state:styria')]
check('cross-range-29-exact', odd == exp_odd, str([x for x in odd if x not in exp_odd][:4] + [x for x in exp_odd if x not in odd][:4]))
# --- per-state primary counts (9) ---
expc = {'at:state:burgenland': 153, 'at:state:carinthia': 215, 'at:state:lower-austria': 647, 'at:state:salzburg': 143, 'at:state:styria': 376, 'at:state:tyrol': 290, 'at:state:upper-austria': 449, 'at:state:vienna': 127, 'at:state:vorarlberg': 101}
havec = Counter(parent[s] for s in prim.values())
check('per-state-primaries', dict(havec) == expc, str([(k, havec.get(k, 0), v) for k, v in expc.items() if havec.get(k, 0) != v]))
# --- per-area primary counts, full 94-row table ---
expm = {
 'at:district:amstetten': 37, 'at:district:baden': 33, 'at:district:bludenz': 33,
 'at:district:braunau': 45, 'at:district:bregenz': 41, 'at:district:bruck-an-der-leitha': 34,
 'at:district:bruck-murzzuschlag': 40, 'at:district:deutschlandsberg': 19, 'at:district:dornbirn': 7,
 'at:district:eferding': 11, 'at:district:eisenstadt-umgebung': 21, 'at:district:feldkirch': 20,
 'at:district:feldkirchen': 12, 'at:district:freistadt': 26, 'at:district:ganserndorf': 45,
 'at:district:gmund': 20, 'at:district:gmunden': 27, 'at:district:graz-umgebung': 42,
 'at:district:grieskirchen': 27, 'at:district:gussing': 19, 'at:district:hallein': 14,
 'at:district:hartberg-furstenfeld': 38, 'at:district:hermagor': 17, 'at:district:hollabrunn': 34,
 'at:district:horn': 23, 'at:district:imst': 29, 'at:district:innsbruck-land': 64,
 'at:district:jennersdorf': 11, 'at:district:kirchdorf': 24, 'at:district:kitzbuhel': 21,
 'at:district:klagenfurt-land': 25, 'at:district:korneuburg': 21, 'at:district:krems': 33,
 'at:district:kufstein': 33, 'at:district:landeck': 30, 'at:district:leibnitz': 29,
 'at:district:leoben': 18, 'at:district:lienz': 33, 'at:district:liezen': 44,
 'at:district:lilienfeld': 18, 'at:district:linz-land': 28, 'at:district:mattersburg': 17,
 'at:district:melk': 42, 'at:district:mistelbach': 43, 'at:district:modling': 25,
 'at:district:murau': 21, 'at:district:murtal': 25, 'at:district:neunkirchen': 33,
 'at:district:neusiedl-am-see': 26, 'at:district:oberpullendorf': 27, 'at:district:oberwart': 28,
 'at:district:perg': 26, 'at:district:reutte': 29, 'at:district:ried': 32,
 'at:district:rohrbach': 32, 'at:district:salzburg-umgebung': 41, 'at:district:scharding': 30,
 'at:district:scheibbs': 22, 'at:district:schwaz': 38, 'at:district:spittal-an-der-drau': 40,
 'at:district:st-johann-im-pongau': 29, 'at:district:st-polten': 51, 'at:district:st-veit-an-der-glan': 28,
 'at:district:steyr-land': 24, 'at:district:sudoststeiermark': 32, 'at:district:tamsweg': 16,
 'at:district:tulln': 33, 'at:district:urfahr-umgebung': 25, 'at:district:villach-land': 36,
 'at:district:vocklabruck': 44, 'at:district:voitsberg': 18, 'at:district:volkermarkt': 18,
 'at:district:waidhofen-an-der-thaya': 17, 'at:district:weiz': 26, 'at:district:wels-land': 22,
 'at:district:wiener-neustadt': 33, 'at:district:wolfsberg': 18, 'at:district:zell-am-see': 31,
 'at:district:zwettl': 29, 'at:state:vienna': 127, 'at:statutory_city:eisenstadt': 3,
 'at:statutory_city:graz': 24, 'at:statutory_city:innsbruck': 13, 'at:statutory_city:klagenfurt-am-worthersee': 11,
 'at:statutory_city:krems-an-der-donau': 5, 'at:statutory_city:linz': 13, 'at:statutory_city:rust': 1,
 'at:statutory_city:salzburg': 12, 'at:statutory_city:st-polten': 10, 'at:statutory_city:steyr': 4,
 'at:statutory_city:villach': 10, 'at:statutory_city:waidhofen-an-der-ybbs': 1, 'at:statutory_city:wels': 9,
 'at:statutory_city:wiener-neustadt': 5,
}
havem = Counter(prim.values())
wrongm = [(sid, havem.get(sid, 0), n) for sid, n in expm.items() if havem.get(sid, 0) != n]
check('per-area-primaries-94', not wrongm, str(wrongm[:4]))
check('covered-94', sum(1 for sid in expm if havem.get(sid, 0) > 0) == 94)
check('zero-primary-8-states', sorted(set(byid) - set(havem)) == ['at:state:burgenland', 'at:state:carinthia', 'at:state:lower-austria', 'at:state:salzburg', 'at:state:styria', 'at:state:tyrol', 'at:state:upper-austria', 'at:state:vorarlberg'])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
