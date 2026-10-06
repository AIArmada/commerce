import csv, re, sys
# Denmark gate. B12 verify-only 2026-10-04: 103 areas / 1159 codes / 1159 links.
# Fresh GeoNames DK dump (1159 rows, 2026-10-03) matches the bundled code set
# exactly (zero diff both ways, range 0800-9990, NNNN per UPU DNK 05/2024)
# and all 1159 primaries equal the GeoNames admin2 kommunekode join, incl.
# the da-WP-stale five 1311/4942/5943/8981/8983 (OSM-confirmed live) and the
# reinstated islands 4244/4245/4945 (2017). GeoNames admin1 cross-checks the
# bundled region parents 1159/1159. Tree: 5/5 ISO 3166-2:DK regions (81-85)
# + 98/98 post-2007 municipalities, WP code/name/parent-exact, SDS official
# codes 98/98, per-region 29/22/19/17/11. No post-2007 mergers; 260 is
# current Halsnaes (ren. 2008). Excluded by design: outlet/service/company/
# terminal codes (937 da-WP actives), GL 39xx, FO 38xx (separate countries),
# unassigned 10xx-19xx block reserves. Zero secondaries: every code is a
# single-municipality delivery district. 2027-01-01 watch: Capital + Zealand
# merge into Region Ostdanmark (4 regions); municipalities unaffected.
# Run from repo root: python3 docs/agents/audit/gate_dk.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/denmark-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/denmark-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/denmark-postal-code-areas.csv'
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
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n') and not raw_c.endswith(b'\r\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n') and not raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 5 regions + 98 municipalities ---
check('areas-103', len(rows) == 103, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-5', len(l1) == 5 and all(not r['parent_source_id'] for r in l1))
check('l1-all-region', all(r['type'] == 'region' for r in l1))
check('l1-iso-codes', sorted(r['code'] for r in l1) == ['81', '82', '83', '84', '85'])
check('l1-names', {r['code']: r['name'] for r in l1} == {'81': 'North Denmark', '82': 'Central Denmark', '83': 'Southern Denmark', '84': 'Capital Region', '85': 'Zealand'})
check('l2-98-municipalities', len(l2) == 98 and all(r['type'] == 'municipality' for r in l2), str(len(l2)))
check('l2-codes', sorted(r['code'] for r in l2) == ['101', '147', '151', '153', '155', '157', '159', '161', '163', '165', '167', '169', '173', '175', '183', '185', '187', '190', '201', '210', '217', '219', '223', '230', '240', '250', '253', '259', '260', '265', '269', '270', '306', '316', '320', '326', '329', '330', '336', '340', '350', '360', '370', '376', '390', '400', '410', '420', '430', '440', '450', '461', '479', '480', '482', '492', '510', '530', '540', '550', '561', '563', '573', '575', '580', '607', '615', '621', '630', '657', '661', '665', '671', '706', '707', '710', '727', '730', '740', '741', '746', '751', '756', '760', '766', '773', '779', '787', '791', '810', '813', '820', '825', '840', '846', '849', '851', '860'])
check('areas-unique-ids', len(byid) == 103)
check('areas-country-DK', all(r['country_code'] == 'DK' for r in rows))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
from collections import Counter
mem = Counter(r['parent_source_id'] for r in l2)
check('region-membership', dict(mem) == {'dk:region:capital-region': 29, 'dk:region:southern-denmark': 22, 'dk:region:central-denmark': 19, 'dk:region:zealand': 17, 'dk:region:north-denmark': 11}, str(dict(mem)))
check('name-halsnaes-260', byid['dk:municipality:halsnaes']['code'] == '260' and byid['dk:municipality:halsnaes']['name'] == 'Halsnæs')
check('name-accents', byid['dk:municipality:naestved']['name'] == 'Næstved' and byid['dk:municipality:hjorring']['name'] == 'Hjørring' and byid['dk:municipality:sonderborg']['name'] == 'Sønderborg' and byid['dk:municipality:aero']['name'] == 'Ærø' and byid['dk:municipality:koge']['name'] == 'Køge' and byid['dk:municipality:faaborg-midtfyn']['name'] == 'Faaborg-Midtfyn')
check('bornholm-capital', byid['dk:municipality:bornholm']['parent_source_id'] == 'dk:region:capital-region' and byid['dk:municipality:bornholm']['code'] == '400')
check('no-christianso-area', all('christians' not in r['source_id'] for r in rows))
# --- postal: 1159 codes / 1159 links, all single-primary ---
check('codes-1159', len(codes) == 1159, str(len(codes)))
check('links-1159', len(links) == 1159, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 1159)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{4}$', c)]
check('code-format-NNNN', not bad, str(bad[:3]))
check('codes-range', min(clist) == '0800' and max(clist) == '9990', f'{min(clist)}-{max(clist)}')
check('codes-country-DK', all(c['country_code'] == 'DK' for c in codes))
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
check('every-code-one-primary', len(prim) == 1159 and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('zero-secondaries', all(len(v) == 1 for v in by.values()))
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('links-all-l2', all(byid[l['area_source_id']]['level'] == '2' for l in links))
check('covered-98', len(set(prim.values())) == 98, str(len(set(prim.values()))))
# --- specials: 6 non-geographic codes joined to the operating municipality ---
check('special-0800-hojetaastrup', prim.get('0800') == 'dk:municipality:hoje-taastrup')
check('special-0900-copenhagen', prim.get('0900') == 'dk:municipality:copenhagen')
check('special-0917-brondby', prim.get('0917') == 'dk:municipality:brondby')
check('special-0960-copenhagen', prim.get('0960') == 'dk:municipality:copenhagen')
check('special-0999-copenhagen', prim.get('0999') == 'dk:municipality:copenhagen')
check('special-1513-copenhagen', prim.get('1513') == 'dk:municipality:copenhagen')
check('sub1000-five', sorted(c for c in clist if c < '1000') == ['0800', '0900', '0917', '0960', '0999'])
# --- da-WP-stale keeps: live per fresh GeoNames + OSM ---
check('keep-1311-copenhagen', prim.get('1311') == 'dk:municipality:copenhagen')
check('keep-4942-lolland', prim.get('4942') == 'dk:municipality:lolland')
check('keep-5943-langeland', prim.get('5943') == 'dk:municipality:langeland')
check('keep-8981-randers', prim.get('8981') == 'dk:municipality:randers')
check('keep-8983-randers', prim.get('8983') == 'dk:municipality:randers')
# --- reinstated islands (2017) ---
check('reinstated-4244-agerso', prim.get('4244') == 'dk:municipality:slagelse')
check('reinstated-4245-omo', prim.get('4245') == 'dk:municipality:slagelse')
check('reinstated-4945-femo', prim.get('4945') == 'dk:municipality:lolland')
# --- tiny-island delivery codes da-WP omits ---
check('island-5602-avernako', prim.get('5602') == 'dk:municipality:faaborg-midtfyn')
check('island-5603-bjorno', prim.get('5603') == 'dk:municipality:faaborg-midtfyn')
check('island-5965-birkholm', prim.get('5965') == 'dk:municipality:aero')
check('island-6210-barso', prim.get('6210') == 'dk:municipality:aabenraa')
# --- Bornholm block: 9 delivery districts, outlet 3761 held out ---
check('bornholm-9', sorted(p for p, s in prim.items() if s == 'dk:municipality:bornholm') == ['3700', '3720', '3730', '3740', '3751', '3760', '3770', '3782', '3790'])
# --- exclusions: outlet/service/terminal/company codes + GL/FO + reserves ---
excluded = ['0555', '0704', '0705', '0710', '0801', '0806', '0910', '0963', '1000', '1027', '1049', '1108', '1190', '1395', '1527', '1566', '1597', '2001', '2412', '3761', '3800', '3850', '3900', '3992', '8980', '8984', '9998', '9999']
check('excluded-28-absent', all(c not in set(clist) for c in excluded), str([c for c in excluded if c in set(clist)]))
check('no-38xx-39xx', not any(c.startswith(('38', '39')) for c in clist))
check('pin-3700-bornholm', prim.get('3700') == 'dk:municipality:bornholm')
check('pin-8000-aarhus', prim.get('8000') == 'dk:municipality:aarhus')
check('pin-9000-aalborg', prim.get('9000') == 'dk:municipality:aalborg')
check('pin-5000-odense', prim.get('5000') == 'dk:municipality:odense')
check('pin-9990-frederikshavn', prim.get('9990') == 'dk:municipality:frederikshavn')
# --- per-region primary counts (5) ---
parent = {r['source_id']: (r['source_id'] if r['level'] == '1' else r['parent_source_id']) for r in rows}
expc = {'dk:region:capital-region': 641, 'dk:region:zealand': 129, 'dk:region:southern-denmark': 167, 'dk:region:central-denmark': 146, 'dk:region:north-denmark': 76}
havec = Counter(parent[s] for s in prim.values())
check('per-region-primaries', dict(havec) == expc, str([(k, havec.get(k, 0), v) for k, v in expc.items() if havec.get(k, 0) != v]))
# --- per-municipality primary counts, full 98-row table ---
expm = {
 'dk:municipality:aabenraa': 8, 'dk:municipality:aalborg': 19, 'dk:municipality:aarhus': 24,
 'dk:municipality:aero': 4, 'dk:municipality:albertslund': 1, 'dk:municipality:allerod': 2,
 'dk:municipality:assens': 7, 'dk:municipality:ballerup': 3, 'dk:municipality:billund': 5,
 'dk:municipality:bornholm': 9, 'dk:municipality:brondby': 3, 'dk:municipality:bronderslev': 5,
 'dk:municipality:copenhagen': 450, 'dk:municipality:dragor': 1, 'dk:municipality:egedal': 4,
 'dk:municipality:esbjerg': 10, 'dk:municipality:faaborg-midtfyn': 12, 'dk:municipality:fano': 1,
 'dk:municipality:favrskov': 6, 'dk:municipality:faxe': 5, 'dk:municipality:fredensborg': 4,
 'dk:municipality:fredericia': 2, 'dk:municipality:frederiksberg': 107, 'dk:municipality:frederikshavn': 8,
 'dk:municipality:frederikssund': 4, 'dk:municipality:fureso': 2, 'dk:municipality:gentofte': 5,
 'dk:municipality:gladsaxe': 2, 'dk:municipality:glostrup': 1, 'dk:municipality:greve': 3,
 'dk:municipality:gribskov': 6, 'dk:municipality:guldborgsund': 14, 'dk:municipality:haderslev': 5,
 'dk:municipality:halsnaes': 5, 'dk:municipality:hedensted': 12, 'dk:municipality:helsingor': 8,
 'dk:municipality:herlev': 1, 'dk:municipality:herning': 10, 'dk:municipality:hillerod': 3,
 'dk:municipality:hjorring': 7, 'dk:municipality:hoje-taastrup': 3, 'dk:municipality:holbaek': 12,
 'dk:municipality:holstebro': 4, 'dk:municipality:horsens': 6, 'dk:municipality:horsholm': 2,
 'dk:municipality:hvidovre': 1, 'dk:municipality:ikast-brande': 8, 'dk:municipality:ishoj': 1,
 'dk:municipality:jammerbugt': 6, 'dk:municipality:kalundborg': 10, 'dk:municipality:kerteminde': 8,
 'dk:municipality:koge': 6, 'dk:municipality:kolding': 11, 'dk:municipality:laeso': 1,
 'dk:municipality:langeland': 5, 'dk:municipality:lejre': 4, 'dk:municipality:lemvig': 5,
 'dk:municipality:lolland': 17, 'dk:municipality:lyngby-taarbaek': 2, 'dk:municipality:mariagerfjord': 4,
 'dk:municipality:middelfart': 7, 'dk:municipality:morso': 6, 'dk:municipality:naestved': 8,
 'dk:municipality:norddjurs': 8, 'dk:municipality:nordfyn': 6, 'dk:municipality:nyborg': 4,
 'dk:municipality:odder': 3, 'dk:municipality:odense': 12, 'dk:municipality:odsherred': 10,
 'dk:municipality:randers': 10, 'dk:municipality:rebild': 6, 'dk:municipality:ringkobing-skjern': 9,
 'dk:municipality:ringsted': 2, 'dk:municipality:rodovre': 1, 'dk:municipality:roskilde': 4,
 'dk:municipality:rudersdal': 5, 'dk:municipality:samso': 1, 'dk:municipality:silkeborg': 9,
 'dk:municipality:skanderborg': 5, 'dk:municipality:skive': 5, 'dk:municipality:slagelse': 9,
 'dk:municipality:solrod': 2, 'dk:municipality:sonderborg': 7, 'dk:municipality:soro': 7,
 'dk:municipality:stevns': 5, 'dk:municipality:struer': 3, 'dk:municipality:svendborg': 9,
 'dk:municipality:syddjurs': 9, 'dk:municipality:tarnby': 3, 'dk:municipality:thisted': 8,
 'dk:municipality:tonder': 9, 'dk:municipality:vallensbaek': 2, 'dk:municipality:varde': 14,
 'dk:municipality:vejen': 10, 'dk:municipality:vejle': 11, 'dk:municipality:vesthimmerland': 6,
 'dk:municipality:viborg': 9, 'dk:municipality:vordingborg': 11,
}
havem = Counter(prim.values())
wrongm = [(sid, havem.get(sid, 0), n) for sid, n in expm.items() if havem.get(sid, 0) != n]
check('per-area-primaries-98', not wrongm, str(wrongm[:4]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
