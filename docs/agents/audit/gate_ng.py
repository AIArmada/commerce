import csv, re, sys
from collections import Counter
# Nigeria gate. M7 revisit: VERIFIED CLEAN, no data fixes.
# Tree = constitutional 774 (768 LGAs + 6 FCT area councils) with
# Fifth-Alteration names; every WP-LGA-page diff adjudicated ours-right
# (Ogun junk rows, Oyo Kajola/Surulere, Sokoto Kebbe/Shagari/Yabo,
# Ori Ire canonical, Okrika/Bursari/Bakura vs WP typos, Aiyekire
# constitutional). Postal: 1926 codes / 215 unanimous dispatch
# prefixes; 1893 corroborated by same-state 56ok ranges, 33 explained
# (56ok misfiles/slop) with mirror corroboration each (verdict.md).
# HQ xxx001 codes systematically absent (UPU Garki 900001 unshipped).
# Run from repo root: python3 docs/agents/audit/gate_ng.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/nigeria-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/nigeria-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/nigeria-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-811', len(rows) == 811, str(len(rows)))
check('states-37', sum(1 for r in rows if r['type'] == 'state') == 37)
check('lgas-768', sum(1 for r in rows if r['type'] == 'lga') == 768)
check('area-councils-6', sum(1 for r in rows if r['type'] == 'area_council') == 6)
check('l1-37', sum(1 for r in rows if r['level'] == '1') == 37)
# full L1 table: (source_id, name, code).
l1exp = [
 ('ng:state:abia', 'Abia', 'AB'),
 ('ng:state:abuja-federal-capital-territory', 'Abuja Federal Capital Territory', 'FC'),
 ('ng:state:adamawa', 'Adamawa', 'AD'),
 ('ng:state:akwa-ibom', 'Akwa Ibom', 'AK'),
 ('ng:state:anambra', 'Anambra', 'AN'),
 ('ng:state:bauchi', 'Bauchi', 'BA'),
 ('ng:state:bayelsa', 'Bayelsa', 'BY'),
 ('ng:state:benue', 'Benue', 'BE'),
 ('ng:state:borno', 'Borno', 'BO'),
 ('ng:state:cross-river', 'Cross River', 'CR'),
 ('ng:state:delta', 'Delta', 'DE'),
 ('ng:state:ebonyi', 'Ebonyi', 'EB'),
 ('ng:state:edo', 'Edo', 'ED'),
 ('ng:state:ekiti', 'Ekiti', 'EK'),
 ('ng:state:enugu', 'Enugu', 'EN'),
 ('ng:state:gombe', 'Gombe', 'GO'),
 ('ng:state:imo', 'Imo', 'IM'),
 ('ng:state:jigawa', 'Jigawa', 'JI'),
 ('ng:state:kaduna', 'Kaduna', 'KD'),
 ('ng:state:kano', 'Kano', 'KN'),
 ('ng:state:katsina', 'Katsina', 'KT'),
 ('ng:state:kebbi', 'Kebbi', 'KE'),
 ('ng:state:kogi', 'Kogi', 'KO'),
 ('ng:state:kwara', 'Kwara', 'KW'),
 ('ng:state:lagos', 'Lagos', 'LA'),
 ('ng:state:nasarawa', 'Nasarawa', 'NA'),
 ('ng:state:niger', 'Niger', 'NI'),
 ('ng:state:ogun', 'Ogun', 'OG'),
 ('ng:state:ondo', 'Ondo', 'ON'),
 ('ng:state:osun', 'Osun', 'OS'),
 ('ng:state:oyo', 'Oyo', 'OY'),
 ('ng:state:plateau', 'Plateau', 'PL'),
 ('ng:state:rivers', 'Rivers', 'RI'),
 ('ng:state:sokoto', 'Sokoto', 'SO'),
 ('ng:state:taraba', 'Taraba', 'TA'),
 ('ng:state:yobe', 'Yobe', 'YO'),
 ('ng:state:zamfara', 'Zamfara', 'ZA'),
]
bad = []
for sid, name, code in l1exp:
    r = byid.get(sid)
    if (not r or r['name'] != name or r['code'] != code
            or r['type'] != 'state' or r['level'] != '1'
            or r['parent_source_id']):
        bad.append(sid)
check('l1-table-37', len(l1exp) == 37 and not bad, str(bad[:3]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# per-state LGA counts = constitutional distribution (FCT 6 area councils).
exp_l2 = {
 'ng:state:abia': 17,
 'ng:state:abuja-federal-capital-territory': 6,
 'ng:state:adamawa': 21,
 'ng:state:akwa-ibom': 31,
 'ng:state:anambra': 21,
 'ng:state:bauchi': 20,
 'ng:state:bayelsa': 8,
 'ng:state:benue': 23,
 'ng:state:borno': 27,
 'ng:state:cross-river': 18,
 'ng:state:delta': 25,
 'ng:state:ebonyi': 13,
 'ng:state:edo': 18,
 'ng:state:ekiti': 16,
 'ng:state:enugu': 17,
 'ng:state:gombe': 11,
 'ng:state:imo': 27,
 'ng:state:jigawa': 27,
 'ng:state:kaduna': 23,
 'ng:state:kano': 44,
 'ng:state:katsina': 34,
 'ng:state:kebbi': 21,
 'ng:state:kogi': 21,
 'ng:state:kwara': 16,
 'ng:state:lagos': 20,
 'ng:state:nasarawa': 13,
 'ng:state:niger': 25,
 'ng:state:ogun': 20,
 'ng:state:ondo': 18,
 'ng:state:osun': 30,
 'ng:state:oyo': 33,
 'ng:state:plateau': 17,
 'ng:state:rivers': 23,
 'ng:state:sokoto': 23,
 'ng:state:taraba': 16,
 'ng:state:yobe': 17,
 'ng:state:zamfara': 14,
}
have_l2 = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
check('per-state-l2', dict(have_l2) == exp_l2,
      str([k for k in exp_l2 if have_l2.get(k) != exp_l2[k]][:3]))
# adjudicated LGA pins (ours-right vs WP LGA page; see verdict.md).
for sid, name, psid in [
 ('ng:lga:ebonyi:afikpo', 'Afikpo', 'ng:state:ebonyi'),
 ('ng:lga:ebonyi:edda', 'Edda', 'ng:state:ebonyi'),
 ('ng:lga:kano:ghari', 'Ghari', 'ng:state:kano'),
 ('ng:lga:ogun:yewa-north', 'Yewa North', 'ng:state:ogun'),
 ('ng:lga:ogun:yewa-south', 'Yewa South', 'ng:state:ogun'),
 ('ng:lga:oyo:atisbo', 'Atisbo', 'ng:state:oyo'),
 ('ng:lga:rivers:obio-akpor', 'Obio-Akpor', 'ng:state:rivers'),
 ('ng:lga:ekiti:aiyekire', 'Aiyekire', 'ng:state:ekiti'),
 ('ng:lga:oyo:ori-ire', 'Ori Ire', 'ng:state:oyo'),
 ('ng:lga:oyo:kajola', 'Kajola', 'ng:state:oyo'),
 ('ng:lga:oyo:surulere', 'Surulere', 'ng:state:oyo'),
 ('ng:lga:rivers:okrika', 'Okrika', 'ng:state:rivers'),
 ('ng:lga:yobe:bursari', 'Bursari', 'ng:state:yobe'),
 ('ng:lga:zamfara:bakura', 'Bakura', 'ng:state:zamfara'),
 ('ng:lga:sokoto:kebbe', 'Kebbe', 'ng:state:sokoto'),
 ('ng:lga:sokoto:shagari', 'Shagari', 'ng:state:sokoto'),
 ('ng:lga:sokoto:yabo', 'Yabo', 'ng:state:sokoto'),
 ('ng:lga:ondo:ile-oluji-okeigbo', 'Ile-Oluji/Okeigbo', 'ng:state:ondo'),
 ('ng:lga:akwa-ibom:ikot-abasi', 'Ikot Abasi', 'ng:state:akwa-ibom'),
]:
    r = byid.get(sid)
    check(f'lga-{sid}', r is not None and r['name'] == name and r['parent_source_id'] == psid, str(r))
# Ogun has exactly the constitutional 20 (WP junk Ilishan-Remo/Isara absent).
ogun = [r for r in rows if r['parent_source_id'] == 'ng:state:ogun']
check('ogun-20-no-junk', len(ogun) == 20 and not [r for r in ogun if r['name'] in ('Ilishan-Remo', 'Isara')], str(len(ogun)))
# FCT area councils: bare names, typed area_council.
fct = sorted(r['name'] for r in rows if r['parent_source_id'] == 'ng:state:abuja-federal-capital-territory')
check('fct-6', fct == ['Abaji', 'Abuja Municipal', 'Bwari', 'Gwagwalada', 'Kuje', 'Kwali'], str(fct))
check('codes-1926', len(codes) == 1926, str(len(codes)))
check('links-1926', len(links) == 1926, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
cset = {c['code'] for c in codes}
lset = {l['postcode'] for l in links}
check('codes-linked-both-ways', cset == lset,
      f'unlinked={sorted(cset - lset)[:3]} dangling={sorted(lset - cset)[:3]}')
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 1926 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('zero-multilink', all(c == 1 for c in counts.values()))
check('all-l1', all(byid[l['area_source_id']]['level'] == '1' for l in links))
# 215 dispatch prefixes, each unanimous to one state.
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
exp_pref = {
 '100': 'ng:state:lagos',
 '101': 'ng:state:lagos',
 '102': 'ng:state:lagos',
 '103': 'ng:state:lagos',
 '104': 'ng:state:lagos',
 '105': 'ng:state:lagos',
 '106': 'ng:state:lagos',
 '110': 'ng:state:ogun',
 '111': 'ng:state:ogun',
 '112': 'ng:state:ogun',
 '120': 'ng:state:ogun',
 '121': 'ng:state:ogun',
 '122': 'ng:state:ogun',
 '200': 'ng:state:oyo',
 '201': 'ng:state:oyo',
 '203': 'ng:state:oyo',
 '211': 'ng:state:oyo',
 '212': 'ng:state:oyo',
 '221': 'ng:state:osun',
 '231': 'ng:state:osun',
 '232': 'ng:state:osun',
 '233': 'ng:state:osun',
 '240': 'ng:state:kwara',
 '241': 'ng:state:kwara',
 '242': 'ng:state:kwara',
 '243': 'ng:state:kwara',
 '251': 'ng:state:kwara',
 '252': 'ng:state:kwara',
 '260': 'ng:state:kogi',
 '261': 'ng:state:kogi',
 '263': 'ng:state:kogi',
 '264': 'ng:state:kogi',
 '270': 'ng:state:kogi',
 '271': 'ng:state:kogi',
 '272': 'ng:state:kogi',
 '300': 'ng:state:edo',
 '310': 'ng:state:edo',
 '311': 'ng:state:edo',
 '312': 'ng:state:edo',
 '320': 'ng:state:delta',
 '321': 'ng:state:delta',
 '330': 'ng:state:delta',
 '331': 'ng:state:delta',
 '332': 'ng:state:delta',
 '333': 'ng:state:delta',
 '334': 'ng:state:delta',
 '340': 'ng:state:ondo',
 '342': 'ng:state:ondo',
 '350': 'ng:state:ondo',
 '351': 'ng:state:ondo',
 '352': 'ng:state:ondo',
 '360': 'ng:state:ekiti',
 '361': 'ng:state:ekiti',
 '362': 'ng:state:ekiti',
 '370': 'ng:state:ekiti',
 '371': 'ng:state:ekiti',
 '372': 'ng:state:ekiti',
 '400': 'ng:state:enugu',
 '401': 'ng:state:enugu',
 '402': 'ng:state:enugu',
 '411': 'ng:state:enugu',
 '412': 'ng:state:enugu',
 '413': 'ng:state:enugu',
 '420': 'ng:state:anambra',
 '421': 'ng:state:anambra',
 '422': 'ng:state:anambra',
 '432': 'ng:state:anambra',
 '433': 'ng:state:anambra',
 '434': 'ng:state:anambra',
 '435': 'ng:state:anambra',
 '440': 'ng:state:abia',
 '441': 'ng:state:abia',
 '442': 'ng:state:abia',
 '450': 'ng:state:abia',
 '451': 'ng:state:abia',
 '453': 'ng:state:abia',
 '461': 'ng:state:imo',
 '462': 'ng:state:imo',
 '463': 'ng:state:imo',
 '471': 'ng:state:imo',
 '472': 'ng:state:imo',
 '474': 'ng:state:imo',
 '475': 'ng:state:imo',
 '480': 'ng:state:ebonyi',
 '481': 'ng:state:ebonyi',
 '482': 'ng:state:ebonyi',
 '490': 'ng:state:ebonyi',
 '491': 'ng:state:ebonyi',
 '501': 'ng:state:rivers',
 '503': 'ng:state:rivers',
 '504': 'ng:state:rivers',
 '510': 'ng:state:rivers',
 '511': 'ng:state:rivers',
 '512': 'ng:state:rivers',
 '520': 'ng:state:akwa-ibom',
 '521': 'ng:state:akwa-ibom',
 '522': 'ng:state:akwa-ibom',
 '524': 'ng:state:akwa-ibom',
 '530': 'ng:state:akwa-ibom',
 '532': 'ng:state:akwa-ibom',
 '534': 'ng:state:akwa-ibom',
 '540': 'ng:state:cross-river',
 '541': 'ng:state:cross-river',
 '542': 'ng:state:cross-river',
 '543': 'ng:state:cross-river',
 '550': 'ng:state:cross-river',
 '551': 'ng:state:cross-river',
 '552': 'ng:state:cross-river',
 '560': 'ng:state:bayelsa',
 '561': 'ng:state:bayelsa',
 '562': 'ng:state:bayelsa',
 '569': 'ng:state:bayelsa',
 '601': 'ng:state:borno',
 '602': 'ng:state:borno',
 '603': 'ng:state:borno',
 '610': 'ng:state:borno',
 '611': 'ng:state:borno',
 '612': 'ng:state:borno',
 '620': 'ng:state:yobe',
 '621': 'ng:state:yobe',
 '622': 'ng:state:yobe',
 '630': 'ng:state:yobe',
 '631': 'ng:state:yobe',
 '632': 'ng:state:yobe',
 '640': 'ng:state:adamawa',
 '641': 'ng:state:adamawa',
 '642': 'ng:state:adamawa',
 '643': 'ng:state:adamawa',
 '650': 'ng:state:adamawa',
 '651': 'ng:state:adamawa',
 '652': 'ng:state:adamawa',
 '660': 'ng:state:taraba',
 '661': 'ng:state:taraba',
 '662': 'ng:state:taraba',
 '670': 'ng:state:taraba',
 '672': 'ng:state:taraba',
 '701': 'ng:state:kano',
 '702': 'ng:state:kano',
 '703': 'ng:state:kano',
 '710': 'ng:state:kano',
 '711': 'ng:state:kano',
 '712': 'ng:state:kano',
 '713': 'ng:state:kano',
 '720': 'ng:state:jigawa',
 '721': 'ng:state:jigawa',
 '731': 'ng:state:jigawa',
 '732': 'ng:state:jigawa',
 '733': 'ng:state:jigawa',
 '740': 'ng:state:bauchi',
 '741': 'ng:state:bauchi',
 '742': 'ng:state:bauchi',
 '743': 'ng:state:bauchi',
 '750': 'ng:state:bauchi',
 '751': 'ng:state:bauchi',
 '752': 'ng:state:bauchi',
 '760': 'ng:state:gombe',
 '761': 'ng:state:gombe',
 '762': 'ng:state:gombe',
 '770': 'ng:state:gombe',
 '771': 'ng:state:gombe',
 '800': 'ng:state:kaduna',
 '801': 'ng:state:kaduna',
 '802': 'ng:state:kaduna',
 '810': 'ng:state:kaduna',
 '812': 'ng:state:kaduna',
 '820': 'ng:state:katsina',
 '821': 'ng:state:katsina',
 '822': 'ng:state:katsina',
 '824': 'ng:state:katsina',
 '830': 'ng:state:katsina',
 '831': 'ng:state:katsina',
 '841': 'ng:state:sokoto',
 '842': 'ng:state:sokoto',
 '843': 'ng:state:sokoto',
 '850': 'ng:state:sokoto',
 '852': 'ng:state:sokoto',
 '853': 'ng:state:sokoto',
 '860': 'ng:state:kebbi',
 '861': 'ng:state:kebbi',
 '862': 'ng:state:kebbi',
 '863': 'ng:state:kebbi',
 '871': 'ng:state:kebbi',
 '872': 'ng:state:kebbi',
 '882': 'ng:state:zamfara',
 '883': 'ng:state:sokoto',
 '888': 'ng:state:taraba',
 '900': 'ng:state:abuja-federal-capital-territory',
 '901': 'ng:state:abuja-federal-capital-territory',
 '902': 'ng:state:abuja-federal-capital-territory',
 '903': 'ng:state:abuja-federal-capital-territory',
 '904': 'ng:state:abuja-federal-capital-territory',
 '905': 'ng:state:abuja-federal-capital-territory',
 '910': 'ng:state:niger',
 '911': 'ng:state:niger',
 '912': 'ng:state:niger',
 '913': 'ng:state:niger',
 '920': 'ng:state:niger',
 '923': 'ng:state:niger',
 '930': 'ng:state:plateau',
 '931': 'ng:state:plateau',
 '932': 'ng:state:plateau',
 '933': 'ng:state:plateau',
 '941': 'ng:state:plateau',
 '942': 'ng:state:plateau',
 '950': 'ng:state:nasarawa',
 '951': 'ng:state:nasarawa',
 '960': 'ng:state:nasarawa',
 '961': 'ng:state:nasarawa',
 '962': 'ng:state:nasarawa',
 '970': 'ng:state:benue',
 '971': 'ng:state:benue',
 '972': 'ng:state:benue',
 '973': 'ng:state:benue',
 '980': 'ng:state:benue',
 '982': 'ng:state:benue',
}
off = {pc: a for pc, a in pin.items() if exp_pref.get(pc[:3]) != a}
check('prefix-unanimous-215', len(exp_pref) == 215 and not off,
      f'n={len(exp_pref)} off={list(off.items())[:3]}')
# UPU NGA anchors shipped: Mokola 200212 Oyo, Karshi 900103 FCT.
for pc, sid in [('200212', 'ng:state:oyo'),
 ('900103', 'ng:state:abuja-federal-capital-territory')]:
    check(f'upu-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# HQ xxx001 codes systematically absent (UPU Garki 900001 + capitals).
for pc in ['900001', '880001', '840001', '660001', '100001', '500001', '700001']:
    check(f'hq-gap-{pc}', pc not in pin, str(pin.get(pc)))
# UPU-example singletons absent (Aisegba 370104, Oyo town 211001).
for pc in ['370104', '211001']:
    check(f'singleton-gap-{pc}', pc not in pin, str(pin.get(pc)))
# off-zone keeps (mirror-corroborated; verdict.md): Isa 883101 Sokoto
# (56ok + nigeriapostal 15-locality page), Karim Lamido 888222 Taraba
# (56ok range + WPC listing + Mapanet filing).
for pc, sid in [('883101', 'ng:state:sokoto'), ('888222', 'ng:state:taraba')]:
    check(f'offzone-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# Zamfara 8822xx Kauran Namoda block (13; street mirrors mycyber 882271,
# headlines-today 882261; WPC/56ok Taraba filings are the known conflation).
kn13 = ['882212', '882213', '882214', '882221', '882231', '882241', '882261', '882271', '882281', '882282', '882283', '882284', '882285']
for pc in kn13:
    check(f'kn-{pc}', pin.get(pc) == 'ng:state:zamfara', str(pin.get(pc)))
# Benue Kwande cluster 982101-982104 (nigeriapostal Adikpo-area rows).
for pc in ['982101', '982102', '982103', '982104']:
    check(f'kwande-{pc}', pin.get(pc) == 'ng:state:benue', str(pin.get(pc)))
# Yobe 620-632 / Taraba 660-672 unanimous blocks (56ok Damaturu/Nguru/
# Jalingo + nigeriapostal town prefixes agree; no competing attribution).
for pc in ['620101', '620103', '621101', '621102', '622104', '622105', '630103', '631101', '631102', '632101']:
    check(f'yobe-{pc}', pin.get(pc) == 'ng:state:yobe', str(pin.get(pc)))
for pc in ['660102', '661104', '661105', '662104', '670102', '670104', '670107', '670108', '672101', '672102']:
    check(f'taraba-{pc}', pin.get(pc) == 'ng:state:taraba', str(pin.get(pc)))
# per-state primary counts.
exp_counts = {
 'ng:state:abia': 82,
 'ng:state:abuja-federal-capital-territory': 41,
 'ng:state:adamawa': 55,
 'ng:state:akwa-ibom': 40,
 'ng:state:anambra': 85,
 'ng:state:bauchi': 39,
 'ng:state:bayelsa': 18,
 'ng:state:benue': 48,
 'ng:state:borno': 42,
 'ng:state:cross-river': 88,
 'ng:state:delta': 55,
 'ng:state:ebonyi': 83,
 'ng:state:edo': 75,
 'ng:state:ekiti': 79,
 'ng:state:enugu': 79,
 'ng:state:gombe': 53,
 'ng:state:imo': 88,
 'ng:state:jigawa': 20,
 'ng:state:kaduna': 73,
 'ng:state:kano': 10,
 'ng:state:katsina': 21,
 'ng:state:kebbi': 38,
 'ng:state:kogi': 36,
 'ng:state:kwara': 51,
 'ng:state:lagos': 135,
 'ng:state:nasarawa': 37,
 'ng:state:niger': 19,
 'ng:state:ogun': 80,
 'ng:state:ondo': 77,
 'ng:state:osun': 38,
 'ng:state:oyo': 71,
 'ng:state:plateau': 73,
 'ng:state:rivers': 16,
 'ng:state:sokoto': 12,
 'ng:state:taraba': 26,
 'ng:state:yobe': 30,
 'ng:state:zamfara': 13,
}
have = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
wrong = [sid for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-state-counts-37', not wrong, str(wrong))
check('covered-37', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 37)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
