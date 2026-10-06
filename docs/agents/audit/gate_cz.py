import csv, re, sys
from collections import Counter
# Czechia gate. B11 fix-and-fill 2026-10-04: 90 areas / 2694 codes / 2738 links.
# Current GeoNames CZ dump (15507 rows / 2694 distinct codes) matches the
# bundled code set exactly (zero diff both ways, range 100 00-798 62,
# NNN NN per UPU). All 2694 primaries equal the GeoNames row-majority
# district, incl. the ties 507 91 Jicin 4:4 (Stara Paka office, OSM
# boundary okres Jicin), 544 43 Trutnov 1:1 (Kuks office, OSM boundary
# okres Trutnov), 569 94 Svitavy 1:1 (OSM boundary Teleci, okres
# Svitavy). 44 dual links: the 29 kept (minorities >=2 rows, plus the
# two 1:1 boundary-confirmed ties) + 15 added secondaries, each with a
# GeoNames x1 minority row AND a RUIAN-referenced Wikidata P281
# (273 51 Praha-zapad, 289 14 Kolin, 294 13 Liberec, 321 00 Plzen-jih,
# 334 52 Domazlice, 357 35 Karlovy Vary, 364 64 Sokolov, 380 01 Trebic,
# 385 01 Klatovy, 507 13 Semily, 517 61 Usti nad Orlici, 539 44 Svitavy,
# 563 01 Svitavy, 675 26 Jihlava, 783 42 Prostejov). 32 x1 minorities
# stay dropped (RUIAN-ref true-code differs, in-dump duplicates,
# GeoNames admin2 mislabels, OSM true-code differs, or unresolved
# conflicts 285 09 / 331 62 / 353 01). 384 01 stays Prachatice-only:
# the Kutin Hora row is Chlistovice with Nebahovy's exact coordinates,
# cross-routing-prefix, duplicated in-dump under 285 22. Tree: 14/14
# ISO 3166-2:CZ regions (Prague L1-only capital_city, no Prague
# district in current ISO) + 76/76 districts, codes/names/parents
# exact. Prague block: 58 codes 100 00-199 00, zero cross-rows either
# way in GeoNames. Pre-fix this gate FAILS exactly on the 15 added
# rows (links-2738, links-l2-2680, secondary-44, dual-set-44-exact,
# dual-pairs-exact, added-15-exact); everything else passes pre-fix.
# Run from repo root: python3 docs/agents/audit/gate_cz.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/czech-republic-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/czech-republic-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/czech-republic-postal-code-areas.csv'
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
check('codes-CRLF-only', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 2695)
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF-only', raw_l.count(b'\r\n') == raw_l.count(b'\n'))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 14 L1 (13 region + Prague capital_city) + 76 districts ---
check('areas-90', len(rows) == 90, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-14', len(l1) == 14 and all(not r['parent_source_id'] for r in l1))
check('l1-type-split', Counter(r['type'] for r in l1) == {'region': 13, 'capital_city': 1}, str(Counter(r['type'] for r in l1)))
check('l1-iso-codes', sorted(r['code'] for r in l1) == ['10', '20', '31', '32', '41', '42', '51', '52', '53', '63', '64', '71', '72', '80'])
check('prague-capital-city', byid['cz:capital_city:praha-hlavni-mesto']['code'] == '10' and byid['cz:capital_city:praha-hlavni-mesto']['name'] == 'Praha, Hlavní město' and byid['cz:capital_city:praha-hlavni-mesto']['level'] == '1')
check('l2-76-districts', len(l2) == 76 and all(r['type'] == 'district' for r in l2), str(len(l2)))
check('l2-codes', sorted(r['code'] for r in l2) == ['201', '202', '203', '204', '205', '206', '207', '208', '209', '20A', '20B', '20C', '311', '312', '313', '314', '315', '316', '317', '321', '322', '323', '324', '325', '326', '327', '411', '412', '413', '421', '422', '423', '424', '425', '426', '427', '511', '512', '513', '514', '521', '522', '523', '524', '525', '531', '532', '533', '534', '631', '632', '633', '634', '635', '641', '642', '643', '644', '645', '646', '647', '711', '712', '713', '714', '715', '721', '722', '723', '724', '801', '802', '803', '804', '805', '806'])
check('areas-unique-ids', len(byid) == 90)
check('areas-country-CZ', all(r['country_code'] == 'CZ' for r in rows))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' and byid[r['parent_source_id']]['type'] == 'region' for r in l2))
check('no-prague-district', all(r['parent_source_id'] != 'cz:capital_city:praha-hlavni-mesto' for r in l2))
mem = Counter(r['parent_source_id'] for r in l2)
check('region-membership', dict(mem) == {'cz:region:jihocesky-kraj': 7, 'cz:region:jihomoravsky-kraj': 7, 'cz:region:karlovarsky-kraj': 3, 'cz:region:kraj-vysocina': 5, 'cz:region:kralovehradecky-kraj': 5, 'cz:region:liberecky-kraj': 4, 'cz:region:moravskoslezsky-kraj': 6, 'cz:region:olomoucky-kraj': 5, 'cz:region:pardubicky-kraj': 4, 'cz:region:plzensky-kraj': 7, 'cz:region:stredocesky-kraj': 12, 'cz:region:ustecky-kraj': 7, 'cz:region:zlinsky-kraj': 4}, str(dict(mem)))
check('iso-name-spots', byid['cz:district:brno-mesto']['name'] == 'Brno-město' and byid['cz:district:ostrava-mesto']['name'] == 'Ostrava-město' and byid['cz:district:plzen-sever']['name'] == 'Plzeň-sever' and byid['cz:district:praha-zapad']['code'] == '20A' and byid['cz:district:pribram']['code'] == '20B' and byid['cz:district:rakovnik']['code'] == '20C' and byid['cz:region:kraj-vysocina']['name'] == 'Kraj Vysočina')
# --- postal: 2694 codes / 2738 links ---
check('codes-2694', len(codes) == 2694, str(len(codes)))
check('links-2738', len(links) == 2738, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 2694)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{3} \d{2}$', c)]
check('code-format-NNN-NN', not bad, str(bad[:3]))
check('codes-range', min(clist) == '100 00' and max(clist) == '798 62', f'{min(clist)}-{max(clist)}')
check('codes-country-CZ', all(c['country_code'] == 'CZ' for c in codes))
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
check('every-code-one-primary', len(prim) == 2694 and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('secondary-44', sum(1 for l in links if l['is_primary'] == 'false') == 44)
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('links-l1-58', sum(1 for l in links if byid[l['area_source_id']]['level'] == '1') == 58)
check('links-l2-2680', sum(1 for l in links if byid[l['area_source_id']]['level'] == '2') == 2680)
# --- Prague block: 58 codes 100 00-199 00, capital_city only ---
pg = [c for c in clist if c.startswith('1')]
check('prague-58', len(pg) == 58 and min(pg) == '100 00' and max(pg) == '199 00')
check('prague-all-capital', all(prim[c] == 'cz:capital_city:praha-hlavni-mesto' for c in pg))
check('prague-single-linked', all(len(by[c]) == 1 for c in pg))
check('capital-only-prague', all(p.startswith('1') for p, s in prim.items() if s == 'cz:capital_city:praha-hlavni-mesto'))
# --- 44 dual links: exact (primary, secondary) table ---
dual = {
 '250 82': ('cz:district:praha-vychod', 'cz:district:kolin'),
 '251 67': ('cz:district:praha-vychod', 'cz:district:benesov'),
 '252 08': ('cz:district:praha-zapad', 'cz:district:benesov'),
 '262 03': ('cz:district:pribram', 'cz:district:praha-zapad'),
 '262 42': ('cz:district:pribram', 'cz:district:strakonice'),
 '273 51': ('cz:district:kladno', 'cz:district:praha-zapad'),
 '278 01': ('cz:district:melnik', 'cz:district:praha-zapad'),
 '285 06': ('cz:district:kutna-hora', 'cz:district:benesov'),
 '289 11': ('cz:district:kolin', 'cz:district:nymburk'),
 '289 14': ('cz:district:nymburk', 'cz:district:kolin'),
 '294 13': ('cz:district:mlada-boleslav', 'cz:district:liberec'),
 '321 00': ('cz:district:plzen-mesto', 'cz:district:plzen-jih'),
 '330 23': ('cz:district:plzen-sever', 'cz:district:tachov'),
 '330 41': ('cz:district:plzen-sever', 'cz:district:karlovy-vary'),
 '331 41': ('cz:district:plzen-sever', 'cz:district:rokycany'),
 '334 01': ('cz:district:plzen-jih', 'cz:district:klatovy'),
 '334 52': ('cz:district:plzen-jih', 'cz:district:domazlice'),
 '340 12': ('cz:district:klatovy', 'cz:district:plzen-jih'),
 '349 52': ('cz:district:tachov', 'cz:district:plzen-sever'),
 '353 01': ('cz:district:cheb', 'cz:district:karlovy-vary'),
 '357 35': ('cz:district:sokolov', 'cz:district:karlovy-vary'),
 '364 64': ('cz:district:karlovy-vary', 'cz:district:sokolov'),
 '375 01': ('cz:district:ceske-budejovice', 'cz:district:pisek'),
 '379 01': ('cz:district:jindrichuv-hradec', 'cz:district:ceske-budejovice'),
 '380 01': ('cz:district:jindrichuv-hradec', 'cz:district:trebic'),
 '384 51': ('cz:district:prachatice', 'cz:district:cesky-krumlov'),
 '384 73': ('cz:district:prachatice', 'cz:district:strakonice'),
 '385 01': ('cz:district:prachatice', 'cz:district:klatovy'),
 '399 01': ('cz:district:pisek', 'cz:district:tabor'),
 '438 01': ('cz:district:louny', 'cz:district:chomutov'),
 '441 01': ('cz:district:louny', 'cz:district:chomutov'),
 '463 42': ('cz:district:liberec', 'cz:district:jablonec-nad-nisou'),
 '507 13': ('cz:district:jicin', 'cz:district:semily'),
 '507 91': ('cz:district:jicin', 'cz:district:semily'),
 '512 63': ('cz:district:semily', 'cz:district:jicin'),
 '517 61': ('cz:district:rychnov-nad-kneznou', 'cz:district:usti-nad-orlici'),
 '539 44': ('cz:district:chrudim', 'cz:district:svitavy'),
 '544 43': ('cz:district:trutnov', 'cz:district:nachod'),
 '563 01': ('cz:district:usti-nad-orlici', 'cz:district:svitavy'),
 '569 82': ('cz:district:svitavy', 'cz:district:chrudim'),
 '569 94': ('cz:district:svitavy', 'cz:district:zdar-nad-sazavou'),
 '675 26': ('cz:district:trebic', 'cz:district:jihlava'),
 '783 42': ('cz:district:olomouc', 'cz:district:prostejov'),
 '788 25': ('cz:district:sumperk', 'cz:district:jesenik'),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
wrongdual = [p for p, (pp, ss) in dual.items()
             if {l['area_source_id'] for l in havemulti.get(p, [])} != {pp, ss} or prim.get(p) != pp]
check('dual-set-44-exact', set(havemulti) == set(dual), str(sorted(set(havemulti) ^ set(dual))[:5]))
check('dual-pairs-exact', not wrongdual, str(wrongdual[:5]))
added15 = {'273 51', '289 14', '294 13', '321 00', '334 52', '357 35', '364 64', '380 01', '385 01', '507 13', '517 61', '539 44', '563 01', '675 26', '783 42'}
check('added-15-exact', all(len(by.get(p, [])) == 2 and [l['area_source_id'] for l in by[p] if l['is_primary'] == 'false'] == [dual[p][1]] for p in added15))
# --- tie + hold + thin-majority pins ---
check('tie-50791-jicin', prim.get('507 91') == 'cz:district:jicin' and len(by.get('507 91', [])) == 2)
check('tie-54443-trutnov', prim.get('544 43') == 'cz:district:trutnov' and len(by.get('544 43', [])) == 2)
check('tie-56994-svitavy', prim.get('569 94') == 'cz:district:svitavy' and len(by.get('569 94', [])) == 2)
check('hold-38401-prachatice-only', [l['area_source_id'] for l in by.get('384 01', [])] == ['cz:district:prachatice'])
check('hold-28509-kutnahora-only', [l['area_source_id'] for l in by.get('285 09', [])] == ['cz:district:kutna-hora'])
check('hold-33162-plzensever-only', [l['area_source_id'] for l in by.get('331 62', [])] == ['cz:district:plzen-sever'])
check('hold-35301-no-sokolov', [l['area_source_id'] for l in by.get('353 01', [])] == ['cz:district:cheb', 'cz:district:karlovy-vary'])
check('thin-46342-liberec', prim.get('463 42') == 'cz:district:liberec')
check('thin-78825-sumperk', prim.get('788 25') == 'cz:district:sumperk')
check('pin-10000-praha', prim.get('100 00') == 'cz:capital_city:praha-hlavni-mesto')
check('pin-19900-praha', prim.get('199 00') == 'cz:capital_city:praha-hlavni-mesto')
check('pin-79862-prostejov', prim.get('798 62') == 'cz:district:prostejov')
# --- every district's primaries share one routing prefix ---
dpref = {}
for p, s in prim.items():
    dpref.setdefault(s, set()).add(p[0])
check('routing-prefix-pure', all(len(v) == 1 for v in dpref.values()), str({k: v for k, v in dpref.items() if len(v) > 1}))
# --- per-region primary counts (14) ---
parent = {r['source_id']: (r['source_id'] if r['level'] == '1' else r['parent_source_id']) for r in rows}
expc = {'cz:capital_city:praha-hlavni-mesto': 58, 'cz:region:jihocesky-kraj': 241, 'cz:region:jihomoravsky-kraj': 289, 'cz:region:karlovarsky-kraj': 48, 'cz:region:kraj-vysocina': 213, 'cz:region:kralovehradecky-kraj': 234, 'cz:region:liberecky-kraj': 130, 'cz:region:moravskoslezsky-kraj': 217, 'cz:region:olomoucky-kraj': 174, 'cz:region:pardubicky-kraj': 191, 'cz:region:plzensky-kraj': 136, 'cz:region:stredocesky-kraj': 411, 'cz:region:ustecky-kraj': 218, 'cz:region:zlinsky-kraj': 134}
havec = Counter(parent[s] for s in prim.values())
check('per-region-primaries', dict(havec) == expc, str([(k, havec.get(k, 0), v) for k, v in expc.items() if havec.get(k, 0) != v]))
# --- per-area primary counts, full 77-row table (primaries unchanged by the fill) ---
expm = {
 'cz:capital_city:praha-hlavni-mesto': 58, 'cz:district:benesov': 35, 'cz:district:beroun': 31,
 'cz:district:blansko': 39, 'cz:district:breclav': 56, 'cz:district:brno-mesto': 27,
 'cz:district:brno-venkov': 52, 'cz:district:bruntal': 29, 'cz:district:ceska-lipa': 39,
 'cz:district:ceske-budejovice': 50, 'cz:district:cesky-krumlov': 29, 'cz:district:cheb': 9,
 'cz:district:chomutov': 29, 'cz:district:chrudim': 51, 'cz:district:decin': 36,
 'cz:district:domazlice': 19, 'cz:district:frydek-mistek': 40, 'cz:district:havlickuv-brod': 45,
 'cz:district:hodonin': 52, 'cz:district:hradec-kralove': 48, 'cz:district:jablonec-nad-nisou': 30,
 'cz:district:jesenik': 19, 'cz:district:jicin': 37, 'cz:district:jihlava': 36,
 'cz:district:jindrichuv-hradec': 42, 'cz:district:karlovy-vary': 26, 'cz:district:karvina': 26,
 'cz:district:kladno': 42, 'cz:district:klatovy': 10, 'cz:district:kolin': 29,
 'cz:district:kromeriz': 25, 'cz:district:kutna-hora': 38, 'cz:district:liberec': 27,
 'cz:district:litomerice': 41, 'cz:district:louny': 38, 'cz:district:melnik': 33,
 'cz:district:mlada-boleslav': 40, 'cz:district:most': 17, 'cz:district:nachod': 49,
 'cz:district:novy-jicin': 38, 'cz:district:nymburk': 29, 'cz:district:olomouc': 42,
 'cz:district:opava': 58, 'cz:district:ostrava-mesto': 26, 'cz:district:pardubice': 40,
 'cz:district:pelhrimov': 40, 'cz:district:pisek': 29, 'cz:district:plzen-jih': 35,
 'cz:district:plzen-mesto': 12, 'cz:district:plzen-sever': 34, 'cz:district:prachatice': 27,
 'cz:district:praha-vychod': 31, 'cz:district:praha-zapad': 37, 'cz:district:prerov': 36,
 'cz:district:pribram': 36, 'cz:district:prostejov': 46, 'cz:district:rakovnik': 30,
 'cz:district:rokycany': 17, 'cz:district:rychnov-nad-kneznou': 40, 'cz:district:semily': 34,
 'cz:district:sokolov': 13, 'cz:district:strakonice': 26, 'cz:district:sumperk': 31,
 'cz:district:svitavy': 46, 'cz:district:tabor': 38, 'cz:district:tachov': 9,
 'cz:district:teplice': 33, 'cz:district:trebic': 46, 'cz:district:trutnov': 60,
 'cz:district:uherske-hradiste': 44, 'cz:district:usti-nad-labem': 24, 'cz:district:usti-nad-orlici': 54,
 'cz:district:vsetin': 34, 'cz:district:vyskov': 16, 'cz:district:zdar-nad-sazavou': 46,
 'cz:district:zlin': 31, 'cz:district:znojmo': 47,
}
havem = Counter(prim.values())
wrongm = [(sid, havem.get(sid, 0), n) for sid, n in expm.items() if havem.get(sid, 0) != n]
check('per-area-primaries-77', not wrongm, str(wrongm[:4]))
check('covered-77', sum(1 for sid in expm if havem.get(sid, 0) > 0) == 77)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
