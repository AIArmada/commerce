import csv, re, sys
from collections import Counter
# Lithuania gate. B10 revisit 2026-10-04: verify-only, zero changes.
# Current GeoNames LT dump (21870 rows / 2023 distinct codes) matches the
# bundled set exactly (zero diff both ways, range 00001-99069). Per-code
# admin2 sets match on all 2020 non-stray codes; primaries equal the GeoNames
# row-majority admin2 on all 2023 codes; counties agree on all 2020. The 3
# dropped cross-county singletons are proven GeoNames duplicate-row misfiles
# (same place also filed under its true code: Padovinio k. 69016, Pakeliskes
# k. 69068, Klaipeda city 91001/94007) inside 148:1 / 35:1 / 50:1 majorities.
# Kept: 45 same-county dual links (16 thin x1 minorities stay per stability).
# Tree: 10/10 ISO 3166-2:LT counties; 60/60 municipalities codes 01-60 with
# 60/60 parents; Marijampole stays plain municipality (WP municipalities
# table + GeoNames 'Marijampoles sav.' + ISO page's own link target agree;
# the ISO page category cell is its error, already adjudicated in 05 text).
# Run from repo root: python3 /tmp/geo-verify/B10/LT/gate_lt.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/lithuania-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/lithuania-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/lithuania-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes; all LF) ---
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 10 counties + 60 municipalities ---
check('areas-70', len(rows) == 70, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-10-counties', len(l1) == 10 and all(r['type'] == 'county' and not r['parent_source_id'] for r in l1))
check('l1-iso-codes', sorted(r['code'] for r in l1) == ['AL', 'KL', 'KU', 'MR', 'PN', 'SA', 'TA', 'TE', 'UT', 'VL'])
check('l2-60', len(l2) == 60, str(len(l2)))
check('l2-type-split', Counter(r['type'] for r in l2) == {'district_municipality': 43, 'municipality': 10, 'city_municipality': 7}, str(Counter(r['type'] for r in l2)))
check('l2-codes-01-60', sorted(r['code'] for r in l2) == [f'{i:02d}' for i in range(1, 61)])
check('areas-unique-ids', len(byid) == 70)
check('areas-country-LT', all(r['country_code'] == 'LT' for r in rows))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
cities = {r['code']: r['name'] for r in l2 if r['type'] == 'city_municipality'}
check('cities-7-miestas', cities == {'02': 'Alytaus miestas', '15': 'Kauno miestas', '20': 'Klaipėdos miestas', '31': 'Palangos miestas', '32': 'Panevėžio miestas', '43': 'Šiaulių miestas', '57': 'Vilniaus miestas'}, str(cities))
check('marijampole-plain', byid['lt:municipality:marijampole']['type'] == 'municipality' and byid['lt:municipality:marijampole']['code'] == '25' and byid['lt:municipality:marijampole']['parent_source_id'] == 'lt:county:marijampole')
check('kazlu-ruda-name', byid['lt:municipality:kazlu-ruda']['name'] == 'Kazlų Rūda')
check('district-slug-suffixes', all(byid[s]['code'] == c for s, c in [('lt:district_municipality:alytus-03', '03'), ('lt:district_municipality:kaunas-16', '16'), ('lt:district_municipality:siauliai-44', '44'), ('lt:district_municipality:vilnius-58', '58')]))
# per-county L2 membership: 5/8/7/5/6/7/4/4/6/8
parent = {r['source_id']: r['parent_source_id'] for r in l2}
mem = Counter(parent[r['source_id']] for r in l2)
check('county-membership', mem == {'lt:county:alytus': 5, 'lt:county:kaunas': 8, 'lt:county:klaipeda': 7, 'lt:county:marijampole': 5, 'lt:county:panevezys': 6, 'lt:county:siauliai': 7, 'lt:county:taurage': 4, 'lt:county:telsiai': 4, 'lt:county:utena': 6, 'lt:county:vilnius': 8}, str(dict(mem)))
# --- postal: 2023 codes / 2068 links ---
check('codes-2023', len(codes) == 2023, str(len(codes)))
check('links-2068', len(links) == 2068, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 2023)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{5}$', c)]
check('code-format-5digit', not bad, str(bad[:3]))
check('codes-range', min(clist) == '00001' and max(clist) == '99069', f'{min(clist)}-{max(clist)}')
check('codes-country-LT', all(c['country_code'] == 'LT' for c in codes))
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
check('every-code-one-primary', len(prim) == 2023 and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('secondary-45', sum(1 for l in links if l['is_primary'] == 'false') == 45)
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('links-all-l2', all(byid[l['area_source_id']]['level'] == '2' for l in links))
# --- 45 dual links: exact (primary, secondary) table, all same-county ---
dual = {
 '00001': ('lt:city_municipality:palangos-miestas', 'lt:district_municipality:klaipeda'),
 '02017': ('lt:city_municipality:vilniaus-miestas', 'lt:district_municipality:vilnius-58'),
 '02021': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '13001': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '13031': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '13034': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '14001': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '14004': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '14007': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '14010': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '14028': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '21043': ('lt:municipality:elektrenai', 'lt:district_municipality:trakai'),
 '21058': ('lt:municipality:elektrenai', 'lt:district_municipality:trakai'),
 '21063': ('lt:district_municipality:trakai', 'lt:municipality:elektrenai'),
 '21072': ('lt:district_municipality:trakai', 'lt:municipality:elektrenai'),
 '25001': ('lt:district_municipality:trakai', 'lt:city_municipality:vilniaus-miestas'),
 '27001': ('lt:district_municipality:vilnius-58', 'lt:city_municipality:vilniaus-miestas'),
 '30045': ('lt:district_municipality:ignalina', 'lt:municipality:visaginas'),
 '35001': ('lt:district_municipality:panevezys', 'lt:city_municipality:panevezio-miestas'),
 '36010': ('lt:district_municipality:panevezys', 'lt:city_municipality:panevezio-miestas'),
 '37001': ('lt:city_municipality:panevezio-miestas', 'lt:district_municipality:panevezys'),
 '37010': ('lt:city_municipality:panevezio-miestas', 'lt:district_municipality:panevezys'),
 '38007': ('lt:district_municipality:panevezys', 'lt:city_municipality:panevezio-miestas'),
 '38052': ('lt:district_municipality:panevezys', 'lt:city_municipality:panevezio-miestas'),
 '38082': ('lt:district_municipality:panevezys', 'lt:city_municipality:panevezio-miestas'),
 '44001': ('lt:city_municipality:kauno-miestas', 'lt:district_municipality:kaunas-16'),
 '45009': ('lt:city_municipality:kauno-miestas', 'lt:district_municipality:kaunas-16'),
 '46001': ('lt:district_municipality:kaunas-16', 'lt:city_municipality:kauno-miestas'),
 '47015': ('lt:city_municipality:kauno-miestas', 'lt:district_municipality:kaunas-16'),
 '53030': ('lt:district_municipality:kaunas-16', 'lt:city_municipality:kauno-miestas'),
 '56001': ('lt:district_municipality:kaisiadorys', 'lt:city_municipality:kauno-miestas'),
 '59001': ('lt:district_municipality:prienai', 'lt:municipality:birstonas'),
 '62001': ('lt:district_municipality:alytus-03', 'lt:city_municipality:alytaus-miestas'),
 '62014': ('lt:district_municipality:alytus-03', 'lt:city_municipality:alytaus-miestas'),
 '67027': ('lt:municipality:druskininkai', 'lt:district_municipality:lazdijai'),
 '67067': ('lt:district_municipality:lazdijai', 'lt:district_municipality:alytus-03'),
 '69068': ('lt:municipality:kazlu-ruda', 'lt:municipality:marijampole'),
 '72028': ('lt:municipality:pagegiai', 'lt:district_municipality:taurage'),
 '76001': ('lt:district_municipality:siauliai-44', 'lt:city_municipality:siauliu-miestas'),
 '78007': ('lt:district_municipality:siauliai-44', 'lt:city_municipality:siauliu-miestas'),
 '79001': ('lt:city_municipality:siauliu-miestas', 'lt:district_municipality:siauliai-44'),
 '90021': ('lt:district_municipality:plunge', 'lt:municipality:rietavas'),
 '91001': ('lt:district_municipality:klaipeda', 'lt:city_municipality:klaipedos-miestas'),
 '94007': ('lt:city_municipality:klaipedos-miestas', 'lt:district_municipality:klaipeda'),
 '96041': ('lt:district_municipality:klaipeda', 'lt:city_municipality:klaipedos-miestas'),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
wrongdual = [p for p, (pp, ss) in dual.items()
             if {l['area_source_id'] for l in havemulti.get(p, [])} != {pp, ss} or prim.get(p) != pp]
check('dual-set-45-exact', set(havemulti) == set(dual), str(sorted(set(havemulti) ^ set(dual))[:5]))
check('dual-pairs-exact', not wrongdual, str(wrongdual[:5]))
check('duals-same-county', all(len({parent[l['area_source_id']] for l in v}) == 1 for v in havemulti.values()))
# --- 3 dropped strays stay single-linked to the majority municipality ---
check('stray-81001-siauliai44', [l['area_source_id'] for l in by.get('81001', [])] == ['lt:district_municipality:siauliai-44'])
check('stray-96001-klaipeda', [l['area_source_id'] for l in by.get('96001', [])] == ['lt:district_municipality:klaipeda'])
check('stray-96047-klaipeda', [l['area_source_id'] for l in by.get('96047', [])] == ['lt:district_municipality:klaipeda'])
# --- cluster pins ---
check('pin-01001-vilnius-city', prim.get('01001') == 'lt:city_municipality:vilniaus-miestas')
check('pin-00001-palanga', prim.get('00001') == 'lt:city_municipality:palangos-miestas')
check('pin-99069-silute', prim.get('99069') == 'lt:district_municipality:silute')
check('pin-62001-alytus-district', prim.get('62001') == 'lt:district_municipality:alytus-03')
check('pin-44001-kaunas-city-tie', prim.get('44001') == 'lt:city_municipality:kauno-miestas')
check('pin-69068-kazlu-ruda', prim.get('69068') == 'lt:municipality:kazlu-ruda')
check('pin-72028-pagegiai', prim.get('72028') == 'lt:municipality:pagegiai')
# --- per-county primary counts (10) ---
expc = {'lt:county:alytus': 51, 'lt:county:kaunas': 102, 'lt:county:klaipeda': 52, 'lt:county:marijampole': 59, 'lt:county:panevezys': 63, 'lt:county:siauliai': 71, 'lt:county:taurage': 51, 'lt:county:telsiai': 41, 'lt:county:utena': 74, 'lt:county:vilnius': 1459}
havec = Counter(parent[s] for s in prim.values())
check('per-county-primaries', dict(havec) == expc, str([(k, havec.get(k, 0), v) for k, v in expc.items() if havec.get(k, 0) != v]))
# --- per-municipality primary counts, full 60-row table ---
expm = {
 'lt:city_municipality:alytaus-miestas': 1, 'lt:city_municipality:kauno-miestas': 17,
 'lt:city_municipality:klaipedos-miestas': 1, 'lt:city_municipality:palangos-miestas': 2,
 'lt:city_municipality:panevezio-miestas': 4, 'lt:city_municipality:siauliu-miestas': 1,
 'lt:city_municipality:vilniaus-miestas': 1349,
 'lt:district_municipality:akmene': 7, 'lt:district_municipality:alytus-03': 17,
 'lt:district_municipality:anyksciai': 15, 'lt:district_municipality:birzai': 9,
 'lt:district_municipality:ignalina': 17, 'lt:district_municipality:jonava': 9,
 'lt:district_municipality:joniskis': 10, 'lt:district_municipality:jurbarkas': 21,
 'lt:district_municipality:kaisiadorys': 9, 'lt:district_municipality:kaunas-16': 22,
 'lt:district_municipality:kedainiai': 17, 'lt:district_municipality:kelme': 10,
 'lt:district_municipality:klaipeda': 13, 'lt:district_municipality:kretinga': 10,
 'lt:district_municipality:kupiskis': 7, 'lt:district_municipality:lazdijai': 12,
 'lt:district_municipality:mazeikiai': 14, 'lt:district_municipality:moletai': 16,
 'lt:district_municipality:pakruojis': 12, 'lt:district_municipality:panevezys': 20,
 'lt:district_municipality:pasvalys': 11, 'lt:district_municipality:plunge': 11,
 'lt:district_municipality:prienai': 11, 'lt:district_municipality:radviliskis': 13,
 'lt:district_municipality:raseiniai': 16, 'lt:district_municipality:rokiskis': 12,
 'lt:district_municipality:sakiai': 20, 'lt:district_municipality:salcininkai': 15,
 'lt:district_municipality:siauliai-44': 18, 'lt:district_municipality:silale': 15,
 'lt:district_municipality:silute': 12, 'lt:district_municipality:sirvintos': 11,
 'lt:district_municipality:skuodas': 12, 'lt:district_municipality:svencionys': 14,
 'lt:district_municipality:taurage': 10, 'lt:district_municipality:telsiai': 13,
 'lt:district_municipality:trakai': 11, 'lt:district_municipality:ukmerge': 19,
 'lt:district_municipality:utena': 13, 'lt:district_municipality:varena': 17,
 'lt:district_municipality:vilkaviskis': 15, 'lt:district_municipality:vilnius-58': 32,
 'lt:district_municipality:zarasai': 11,
 'lt:municipality:birstonas': 1, 'lt:municipality:druskininkai': 4,
 'lt:municipality:elektrenai': 8, 'lt:municipality:kalvarija': 5,
 'lt:municipality:kazlu-ruda': 8, 'lt:municipality:marijampole': 11,
 'lt:municipality:neringa': 2, 'lt:municipality:pagegiai': 5,
 'lt:municipality:rietavas': 3, 'lt:municipality:visaginas': 2,
}
havem = Counter(prim.values())
wrongm = [(sid, havem.get(sid, 0), n) for sid, n in expm.items() if havem.get(sid, 0) != n]
check('per-municipality-primaries-60', not wrongm, str(wrongm[:4]))
check('covered-60', sum(1 for sid in expm if havem.get(sid, 0) > 0) == 60)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
