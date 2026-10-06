import csv, re, sys
from collections import Counter
# Serbia gate. Pins the B15 inline pass (4 cities added, 45
# district->city moves, 8 primary flips, 1 secondary added, 2
# L1-generic->muni moves, 7 fills; 189 -> 193 areas, 1334 ->
# 1341 codes, 1407 -> 1415 legs, 68 -> 69 multis) + the
# 2026-10-06 retry (7 verdicts + 91 L1->muni relegs; 1415 ->
# 1411 legs, 69 -> 67 multis): 193 areas
# (32 L1: Belgrade city + 29 districts + 2 provinces; 161 L2:
# 117 municipalities + 27 cities + 17 Belgrade city-munis),
# 1341 codes, 1415 legs. Tree: municipalities 117/117 exact
# (names + parents vs WP law-ordered list), cities 23/23 + 4
# added (Nis/Vranje/Pozarevac/Uzice at wiki-table positions
# 14/3/19/26), Belgrade 17/17. Moves: all 45 district-crutch
# legs to the 4 new cities (GN settlement + sr.wiki membership
# + operator PAK delivery rows, each >=2 signals; 31311 Bela
# Zemlja -> Uzice per operator Drijetanj/Ljubanje rows over GN
# algorithmic Cajetina hierarchy; 31203 Lunovo Selo -> Uzice per
# GN hierarchy + bundling attribution + 312xx cluster). Flips:
# 11118 Vracar, 11120 Zvezdara, 11158 Stari Grad, 11160
# Zvezdara (operator streets + OSM polygons), 15226 Koceljeva
# (Draginje: sr.wiki + GN web), 37202 Krusevac (Dunis: sr.wiki
# + en.wiki + operator row), 37233 Aleksandrovac (Velika
# Vrbnica: sr.wiki + GN web), 18411 Doljevac (Belotinac:
# sr.wiki + GN web). Added: 11102 Savski Venac secondary
# (operator x2 + OSM half-right). Moved off L1-generic: 11150
# Novi Beograd, 11167 Vracar (operator + OSM). Fills: 18101,
# 18103, 18104, 18105 Nis; 31109 Uzice; 11042 Vozdovac; 11197
# Novi Beograd (all operator streets + OSM postcode hits).
# Retry 2026-10-06: 11040 Savski Venac P + Vozdovac S (Rakovica
# drops), 11050 Zvezdara P + Vozdovac S (Vracar drops), 17508
# Vranje P + Trgoviste S (district drops), 18110 Nis P, 18251 Nis
# P + Nisava S span marker, 18252 Merosina-only, 18411
# Doljevac-only, 91 L1 branch codes to city-munis (PAK + PlanPlus
# + OSM per code). Holds: 11161 generic (zero signals), 18251
# Merosina slice + 17508 Trgoviste slice (WPC-only), 18252 drop
# caveat (WPC-single-oracle), 11113/11140 (DIR+OSM / 2v1),
# 11000 multi as-is. Oracles: Pošta
# Srbije PAK finder (60+ street probes + autocomplete), GN
# RS.txt (1149 codes, all in bundle) + GN web hierarchies,
# Nominatim postcode/settlement search, sr.wiki settlement
# membership (~120 villages), en.wiki law-ordered muni/city
# tables, DDG postcode snippets (17554 Korbul). Singles sample
# 24/24. Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_rs.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/serbia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/serbia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/serbia-postal-code-areas.csv'
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
# --- tree: 32 L1 + 161 L2 ---
check('areas-193', len(rows) == 193, str(len(rows)))
check('l1-32', sum(1 for r in rows if r['level'] == '1') == 32)
check('l1-city-1', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'city') == 1)
check('l1-district-29', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'district') == 29)
check('l1-province-2', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'province') == 2)
check('l2-161', sum(1 for r in rows if r['level'] == '2') == 161)
check('muni-117', sum(1 for r in rows if r['type'] == 'municipality') == 117)
check('city-28', sum(1 for r in rows if r['type'] == 'city') == 28)
check('citymuni-17', sum(1 for r in rows if r['type'] == 'city_municipality') == 17)
iso = {f'{i:02d}' for i in range(30)} | {'KM', 'VO'}
check('iso-l1', set(r['code'] for r in rows if r['level'] == '1') == iso)
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- B15 added cities ---
newc = {'rs:city:nis': ('Niš', 'rs:district:nisava'), 'rs:city:vranje': ('Vranje', 'rs:district:pcinja'),
        'rs:city:pozarevac': ('Požarevac', 'rs:district:branicevo'), 'rs:city:uzice': ('Užice', 'rs:district:zlatibor')}
for sid, (nm, par) in newc.items():
    r = byid.get(sid, {})
    check(f'city-{sid}', r.get('name') == nm and r.get('parent_source_id') == par and r.get('level') == '2')
order = [r['source_id'] for r in rows]
check('city-order', order.index('rs:city:valjevo') < order.index('rs:city:vranje') < order.index('rs:city:vrsac')
      and order.index('rs:city:loznica') < order.index('rs:city:nis') < order.index('rs:city:novi-pazar')
      and order.index('rs:city:pirot') < order.index('rs:city:pozarevac') < order.index('rs:city:prokuplje')
      and order.index('rs:city:subotica') < order.index('rs:city:uzice') < order.index('rs:city:cacak'))
# --- postal: 1341 codes / 1411 legs / 67 multis ---
check('codes-1341', len(codes) == 1341, str(len(codes)))
check('links-1411', len(links) == 1411, str(len(links)))
check('codes-RS', all(c['country_code'] == 'RS' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('codes-sorted', clist == sorted(clist))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-67', len(multis) == 67, str(len(multis)))
check('11102-multi', '11102' in multis)
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 1341)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
# --- district->city moves (spot + full-set counts) ---
moved = {'rs:city:pozarevac': ['12000', '12206', '12207', '12208', '12209', '12227', '12372'],
         'rs:city:vranje': ['17501', '17507', '17508', '17541', '17542', '17543', '17545', '17554', '17521'],
         'rs:city:nis': ['18000', '18106', '18110', '18202', '18203', '18204', '18205', '18206', '18207', '18209',
                         '18211', '18250', '18251', '18254', '18256', '18311', '18312'],
         'rs:city:uzice': ['31000', '31102', '31103', '31104', '31106', '31107', '31108', '31203', '31204',
                           '31205', '31206', '31241', '31242', '31243', '31311']}
for sid, plist in moved.items():
    got = sorted(pc for pc, v in prims.items() if v == [sid] and pc in plist)
    check(f'moved-{sid.split(":")[-1]}', got == sorted(plist), str(sorted(set(plist) - set(got))[:3]))
check('17521-bujanovac-kept', legs_of('17521') == ['rs:city:vranje', 'rs:municipality:bujanovac'])
check('31241-bb-kept', legs_of('31241') == ['rs:city:uzice', 'rs:municipality:bajina-basta'])
# --- 2026-10-06 verdicts (district legs resolved) ---
check('17508-vranje', prims.get('17508') == ['rs:city:vranje']
      and legs_of('17508') == ['rs:city:vranje', 'rs:municipality:trgoviste'])
check('18110-nis', prims.get('18110') == ['rs:city:nis'] and legs_of('18110') == ['rs:city:nis'])
check('18251-nis', prims.get('18251') == ['rs:city:nis']
      and legs_of('18251') == ['rs:city:nis', 'rs:district:nisava'])
check('18252-merosina-sole', prims.get('18252') == ['rs:municipality:merosina']
      and legs_of('18252') == ['rs:municipality:merosina'])
# --- flips ---
flips = {'11118': 'rs:city_municipality:vracar', '11120': 'rs:city_municipality:zvezdara',
         '11158': 'rs:city_municipality:stari-grad', '11160': 'rs:city_municipality:zvezdara',
         '15226': 'rs:municipality:koceljeva', '37202': 'rs:city:krusevac',
         '37233': 'rs:municipality:aleksandrovac', '18411': 'rs:municipality:doljevac'}
for pc, sid in flips.items():
    check(f'flip-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
check('18411-doljevac-sole', legs_of('18411') == ['rs:municipality:doljevac'])
# --- 2026-10-06 primaries ---
check('11040-sv', prims.get('11040') == ['rs:city_municipality:savski-venac']
      and legs_of('11040') == ['rs:city_municipality:savski-venac', 'rs:city_municipality:vozdovac'])
check('11050-zv', prims.get('11050') == ['rs:city_municipality:zvezdara']
      and legs_of('11050') == ['rs:city_municipality:vozdovac', 'rs:city_municipality:zvezdara'])
# --- 2026-10-06 L1 tail: 91 branch codes to city-munis, 11161 held generic ---
l1muni = {'11011': 'vozdovac', '11032': 'cukarica', '11051': 'zvezdara', '11052': 'zvezdara',
          '11061': 'palilula', '11071': 'zemun', '11072': 'novi-beograd', '11075': 'novi-beograd',
          '11076': 'novi-beograd', '11078': 'novi-beograd', '11084': 'zemun', '11091': 'rakovica',
          '11092': 'rakovica', '11093': 'rakovica', '11101': 'palilula', '11105': 'savski-venac',
          '11106': 'stari-grad', '11107': 'vozdovac', '11109': 'zvezdara', '11110': 'vracar',
          '11112': 'savski-venac', '11113': 'savski-venac', '11114': 'zvezdara', '11115': 'vracar',
          '11116': 'palilula', '11117': 'stari-grad', '11119': 'vracar', '11121': 'stari-grad',
          '11122': 'palilula', '11123': 'savski-venac', '11124': 'savski-venac', '11125': 'stari-grad',
          '11127': 'zvezdara', '11129': 'savski-venac', '11131': 'cukarica', '11133': 'cukarica',
          '11134': 'cukarica', '11135': 'cukarica', '11136': 'cukarica', '11137': 'rakovica',
          '11138': 'cukarica', '11139': 'cukarica', '11140': 'rakovica', '11141': 'vozdovac',
          '11142': 'savski-venac', '11143': 'vozdovac', '11144': 'vozdovac', '11145': 'palilula',
          '11146': 'savski-venac', '11148': 'vozdovac', '11149': 'vozdovac', '11151': 'vozdovac',
          '11152': 'vozdovac', '11154': 'savski-venac', '11156': 'vozdovac', '11159': 'cukarica',
          '11162': 'palilula', '11163': 'vozdovac', '11164': 'palilula', '11165': 'rakovica',
          '11166': 'stari-grad', '11168': 'savski-venac', '11169': 'savski-venac', '11170': 'novi-beograd',
          '11172': 'novi-beograd', '11173': 'novi-beograd', '11174': 'novi-beograd', '11175': 'novi-beograd',
          '11176': 'novi-beograd', '11177': 'novi-beograd', '11178': 'novi-beograd', '11179': 'novi-beograd',
          '11182': 'zemun', '11183': 'zemun', '11184': 'zemun', '11187': 'novi-beograd',
          '11188': 'novi-beograd', '11189': 'novi-beograd', '11190': 'zemun', '11191': 'rakovica',
          '11192': 'rakovica', '11193': 'rakovica', '11195': 'rakovica', '11196': 'novi-beograd',
          '11198': 'novi-beograd', '11199': 'novi-beograd', '11236': 'rakovica', '11252': 'novi-beograd',
          '11254': 'cukarica', '11278': 'zemun', '11281': 'zemun'}
bad_l1 = [(pc, prims.get(pc)) for pc, m in l1muni.items()
          if prims.get(pc) != [f'rs:city_municipality:{m}'] or len(legs_of(pc)) != 1]
check('l1-91-muni', not bad_l1, str(bad_l1[:3]))
check('11161-generic', legs_of('11161') == ['rs:city:belgrade'])
# --- 11102 dual + L1-generic moves ---
check('11102-dual', legs_of('11102') == ['rs:city_municipality:savski-venac', 'rs:city_municipality:stari-grad'])
check('11102-primary', prims.get('11102') == ['rs:city_municipality:stari-grad'])
check('11150-nbg', legs_of('11150') == ['rs:city_municipality:novi-beograd'])
check('11167-vracar', legs_of('11167') == ['rs:city_municipality:vracar'])
# --- fills ---
fills = {'11042': 'rs:city_municipality:vozdovac', '11197': 'rs:city_municipality:novi-beograd',
         '18101': 'rs:city:nis', '18103': 'rs:city:nis', '18104': 'rs:city:nis',
         '18105': 'rs:city:nis', '31109': 'rs:city:uzice'}
for pc, sid in fills.items():
    check(f'fill-{pc}', legs_of(pc) == [sid], str(legs_of(pc)))
# --- verified keeps (spot) ---
keeps = {'11000': 'rs:city_municipality:savski-venac', '11030': 'rs:city_municipality:cukarica',
         '11060': 'rs:city_municipality:palilula', '11090': 'rs:city_municipality:rakovica',
         '11108': 'rs:city_municipality:palilula', '11231': 'rs:city_municipality:rakovica',
         '11194': 'rs:city_municipality:cukarica', '11272': 'rs:city_municipality:surcin',
         '11460': 'rs:city_municipality:barajevo', '24414': 'rs:city:subotica',
         '12222': 'rs:municipality:golubac', '12304': 'rs:municipality:petrovac-na-mlavi',
         '17512': 'rs:municipality:vladicin-han', '17522': 'rs:municipality:bujanovac',
         '17532': 'rs:municipality:surdulica', '37215': 'rs:municipality:razanj',
         '37246': 'rs:municipality:trstenik', '31310': 'rs:municipality:cajetina',
         '31325': 'rs:municipality:nova-varos', '11080': 'rs:city_municipality:zemun'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
