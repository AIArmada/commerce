import csv, re, sys
from collections import Counter
# Azerbaijan gate. M7 revisit: VERIFIED CLEAN, no data changes.
# Tree = ISO 3166-2:AZ exactly (78 codes: NX + 66 rayons + 11 cities);
# names below are the bundled display forms. Systematic ISO-en/WP
# variants recorded, not applied: Agdam/Aghdam, Agstafa/Aghstafa,
# Agsu/Aghsu, Qabala/Gabala, Qakh/Gakh, Qazakh/Gazakh, Quba/Guba,
# Qubadli/Gubadli, Qusar/Gusar, Zaqatala/Zagatala, Siazan/Siyazan,
# Sumqayit/Sumgayit, Yardymli/Yardimli (ours match WP article titles
# except Aghdam/Aghstafa/Yardimli, kept as conventional spellings;
# gdby slug pinned by existing test). Postal = GeoNames AZ dump code
# set, byte-identical (1186/1186 both ways); city/district splits hold
# NN00+eponym+N Sayli branches. Nakhchivan exclave (10 L1) + Jabrayil
# have no codes in GN either (UPU AZ6715 Babek, AZ7000 city, AZ1400
# Jabrayil all real-world, unshipped: documented gap). Run from repo root:
# python3 docs/agents/audit/gate_az.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/azerbaijan-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/azerbaijan-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/azerbaijan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-763', len(rows) == 763, str(len(rows)))
check('autonomous-republic-1', sum(1 for r in rows if r['type'] == 'autonomous_republic') == 1)
check('districts-66', sum(1 for r in rows if r['type'] == 'district') == 66)
check('municipalities-11', sum(1 for r in rows if r['type'] == 'municipality') == 11)
check('local-municipalities-685', sum(1 for r in rows if r['type'] == 'local_municipality') == 685)
check('l1-78', sum(1 for r in rows if r['level'] == '1') == 78)
# full L1 table: (source_id, name, ISO code, type). code set == ISO 3166-2:AZ.
l1exp = [
 ('az:autonomous_republic:nakhchivan', 'Nakhchivan', 'NX', 'autonomous_republic'),
 ('az:district:absheron', 'Absheron', 'ABS', 'district'),
 ('az:district:agdam', 'Agdam', 'AGM', 'district'),
 ('az:district:agdash', 'Agdash', 'AGS', 'district'),
 ('az:district:aghjabadi', 'Aghjabadi', 'AGC', 'district'),
 ('az:district:agstafa', 'Agstafa', 'AGA', 'district'),
 ('az:district:agsu', 'Agsu', 'AGU', 'district'),
 ('az:district:astara', 'Astara', 'AST', 'district'),
 ('az:district:babek', 'Babek', 'BAB', 'district'),
 ('az:district:balakan', 'Balakan', 'BAL', 'district'),
 ('az:district:barda', 'Barda', 'BAR', 'district'),
 ('az:district:beylagan', 'Beylagan', 'BEY', 'district'),
 ('az:district:bilasuvar', 'Bilasuvar', 'BIL', 'district'),
 ('az:district:dashkasan', 'Dashkasan', 'DAS', 'district'),
 ('az:district:fuzuli', 'Fuzuli', 'FUZ', 'district'),
 ('az:district:gdby', 'Gadabay', 'GAD', 'district'),
 ('az:district:gobustan', 'Gobustan', 'QOB', 'district'),
 ('az:district:goranboy', 'Goranboy', 'GOR', 'district'),
 ('az:district:goychay', 'Goychay', 'GOY', 'district'),
 ('az:district:goygol', 'Goygol', 'GYG', 'district'),
 ('az:district:hajigabul', 'Hajigabul', 'HAC', 'district'),
 ('az:district:imishli', 'Imishli', 'IMI', 'district'),
 ('az:district:ismayilli', 'Ismayilli', 'ISM', 'district'),
 ('az:district:jabrayil', 'Jabrayil', 'CAB', 'district'),
 ('az:district:jalilabad', 'Jalilabad', 'CAL', 'district'),
 ('az:district:julfa', 'Julfa', 'CUL', 'district'),
 ('az:district:kalbajar', 'Kalbajar', 'KAL', 'district'),
 ('az:district:kangarli', 'Kangarli', 'KAN', 'district'),
 ('az:district:khachmaz', 'Khachmaz', 'XAC', 'district'),
 ('az:district:khizi', 'Khizi', 'XIZ', 'district'),
 ('az:district:khojaly', 'Khojaly', 'XCI', 'district'),
 ('az:district:khojavend', 'Khojavend', 'XVD', 'district'),
 ('az:district:kurdamir', 'Kurdamir', 'KUR', 'district'),
 ('az:district:lachin', 'Lachin', 'LAC', 'district'),
 ('az:district:lankaran', 'Lankaran', 'LAN', 'district'),
 ('az:district:lerik', 'Lerik', 'LER', 'district'),
 ('az:district:masally', 'Masally', 'MAS', 'district'),
 ('az:district:neftchala', 'Neftchala', 'NEF', 'district'),
 ('az:district:oghuz', 'Oghuz', 'OGU', 'district'),
 ('az:district:ordubad', 'Ordubad', 'ORD', 'district'),
 ('az:district:qabala', 'Qabala', 'QAB', 'district'),
 ('az:district:qakh', 'Qakh', 'QAX', 'district'),
 ('az:district:qazakh', 'Qazakh', 'QAZ', 'district'),
 ('az:district:quba', 'Quba', 'QBA', 'district'),
 ('az:district:qubadli', 'Qubadli', 'QBI', 'district'),
 ('az:district:qusar', 'Qusar', 'QUS', 'district'),
 ('az:district:saatly', 'Saatly', 'SAT', 'district'),
 ('az:district:sabirabad', 'Sabirabad', 'SAB', 'district'),
 ('az:district:sadarak', 'Sadarak', 'SAD', 'district'),
 ('az:district:salyan', 'Salyan', 'SAL', 'district'),
 ('az:district:samukh', 'Samukh', 'SMX', 'district'),
 ('az:district:shabran', 'Shabran', 'SBN', 'district'),
 ('az:district:shahbuz', 'Shahbuz', 'SAH', 'district'),
 ('az:district:shaki', 'Shaki', 'SAK', 'district'),
 ('az:district:shamakhi', 'Shamakhi', 'SMI', 'district'),
 ('az:district:shamkir', 'Shamkir', 'SKR', 'district'),
 ('az:district:sharur', 'Sharur', 'SAR', 'district'),
 ('az:district:shusha', 'Shusha', 'SUS', 'district'),
 ('az:district:siazan', 'Siazan', 'SIY', 'district'),
 ('az:district:tartar', 'Tartar', 'TAR', 'district'),
 ('az:district:tovuz', 'Tovuz', 'TOV', 'district'),
 ('az:district:ujar', 'Ujar', 'UCA', 'district'),
 ('az:district:yardymli', 'Yardymli', 'YAR', 'district'),
 ('az:district:yevlakh', 'Yevlakh', 'YEV', 'district'),
 ('az:district:zangilan', 'Zangilan', 'ZAN', 'district'),
 ('az:district:zaqatala', 'Zaqatala', 'ZAQ', 'district'),
 ('az:district:zardab', 'Zardab', 'ZAR', 'district'),
 ('az:municipality:baku', 'Baku', 'BA', 'municipality'),
 ('az:municipality:ganja', 'Ganja', 'GA', 'municipality'),
 ('az:municipality:khankendi', 'Khankendi', 'XA', 'municipality'),
 ('az:municipality:lankaran', 'Lankaran', 'LA', 'municipality'),
 ('az:municipality:mingachevir', 'Mingachevir', 'MI', 'municipality'),
 ('az:municipality:naftalan', 'Naftalan', 'NA', 'municipality'),
 ('az:municipality:nakhchivan', 'Nakhchivan', 'NV', 'municipality'),
 ('az:municipality:shaki', 'Shaki', 'SA', 'municipality'),
 ('az:municipality:shirvan', 'Shirvan', 'SR', 'municipality'),
 ('az:municipality:sumqayit', 'Sumqayit', 'SM', 'municipality'),
 ('az:municipality:yevlakh', 'Yevlakh', 'YE', 'municipality'),
]
bad = []
for sid, name, code, typ in l1exp:
    r = byid.get(sid)
    if (not r or r['name'] != name or r['code'] != code or r['type'] != typ
            or r['level'] != '1' or r['parent_source_id']):
        bad.append(sid)
check('l1-table-78', len(l1exp) == 78 and not bad, str(bad[:3]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# per-parent L2 counts (SSC classification; 7 liberated-territory
# districts + Khankendi city stay childless).
exp_l2 = {
 'az:district:absheron': 12,
 'az:district:agdam': 8,
 'az:district:agdash': 11,
 'az:district:aghjabadi': 15,
 'az:district:agstafa': 12,
 'az:district:agsu': 10,
 'az:district:astara': 6,
 'az:district:babek': 7,
 'az:district:balakan': 13,
 'az:district:barda': 20,
 'az:district:beylagan': 16,
 'az:district:bilasuvar': 11,
 'az:district:dashkasan': 4,
 'az:district:fuzuli': 7,
 'az:district:gdby': 10,
 'az:district:gobustan': 5,
 'az:district:goranboy': 17,
 'az:district:goychay': 13,
 'az:district:goygol': 7,
 'az:district:hajigabul': 7,
 'az:district:imishli': 13,
 'az:district:ismayilli': 11,
 'az:district:jalilabad': 19,
 'az:district:julfa': 6,
 'az:district:kangarli': 3,
 'az:district:khachmaz': 13,
 'az:district:khizi': 2,
 'az:district:kurdamir': 14,
 'az:district:lankaran': 23,
 'az:district:lerik': 10,
 'az:district:masally': 18,
 'az:district:neftchala': 8,
 'az:district:oghuz': 6,
 'az:district:ordubad': 8,
 'az:district:qabala': 12,
 'az:district:qakh': 8,
 'az:district:qazakh': 11,
 'az:district:quba': 21,
 'az:district:qusar': 10,
 'az:district:saatly': 10,
 'az:district:sabirabad': 18,
 'az:district:sadarak': 2,
 'az:district:salyan': 14,
 'az:district:samukh': 8,
 'az:district:shabran': 5,
 'az:district:shahbuz': 4,
 'az:district:shaki': 16,
 'az:district:shamakhi': 10,
 'az:district:shamkir': 17,
 'az:district:sharur': 12,
 'az:district:siazan': 3,
 'az:district:tartar': 10,
 'az:district:tovuz': 20,
 'az:district:ujar': 9,
 'az:district:yardymli': 8,
 'az:district:yevlakh': 9,
 'az:district:zaqatala': 17,
 'az:district:zardab': 8,
 'az:municipality:baku': 45,
 'az:municipality:ganja': 2,
 'az:municipality:lankaran': 1,
 'az:municipality:mingachevir': 1,
 'az:municipality:naftalan': 1,
 'az:municipality:nakhchivan': 2,
 'az:municipality:shaki': 1,
 'az:municipality:shirvan': 1,
 'az:municipality:sumqayit': 3,
 'az:municipality:yevlakh': 1,
}
have_l2 = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
check('per-parent-l2', dict(have_l2) == exp_l2,
      str([k for k in exp_l2 if have_l2.get(k) != exp_l2[k]][:3]))
check('childless-8', sorted(s for s in byid if byid[s]['level'] == '1' and s not in have_l2) == ['az:autonomous_republic:nakhchivan', 'az:district:jabrayil', 'az:district:kalbajar', 'az:district:khojaly', 'az:district:khojavend', 'az:district:lachin', 'az:district:qubadli', 'az:district:shusha', 'az:district:zangilan', 'az:municipality:khankendi'])
check('codes-1186', len(codes) == 1186, str(len(codes)))
check('links-1186', len(links) == 1186, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^AZ \d{4}$', c['code'])]
check('code-format', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
cset = {c['code'] for c in codes}
lset = {l['postcode'] for l in links}
check('codes-linked-both-ways', cset == lset,
      f'unlinked={sorted(cset - lset)[:3]} dangling={sorted(lset - cset)[:3]}')
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 1186 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('zero-multilink', all(c == 1 for c in counts.values()))
check('all-l1', all(byid[l['area_source_id']]['level'] == '1' for l in links))
# 100-block grain: block NN -> L1 area (Baku holds 10+11; uncovered: 14 Jabrayil, 67-79
# Nakhchivan exclave). Every shipped code must sit in its area block.
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
block = {
 '01': 'az:district:absheron',
 '02': 'az:district:agdam',
 '03': 'az:district:agdash',
 '04': 'az:district:aghjabadi',
 '05': 'az:district:agstafa',
 '06': 'az:district:agsu',
 '07': 'az:district:astara',
 '08': 'az:district:balakan',
 '09': 'az:district:barda',
 '10': 'az:municipality:baku',
 '11': 'az:municipality:baku',
 '12': 'az:district:beylagan',
 '13': 'az:district:bilasuvar',
 '15': 'az:district:jalilabad',
 '16': 'az:district:dashkasan',
 '17': 'az:district:shabran',
 '18': 'az:municipality:shirvan',
 '19': 'az:district:fuzuli',
 '20': 'az:municipality:ganja',
 '21': 'az:district:gdby',
 '22': 'az:district:goranboy',
 '23': 'az:district:goychay',
 '24': 'az:district:hajigabul',
 '25': 'az:district:goygol',
 '26': 'az:municipality:khankendi',
 '27': 'az:district:khachmaz',
 '28': 'az:district:khojavend',
 '29': 'az:district:khojaly',
 '30': 'az:district:imishli',
 '31': 'az:district:ismayilli',
 '32': 'az:district:kalbajar',
 '33': 'az:district:kurdamir',
 '34': 'az:district:qakh',
 '35': 'az:district:qazakh',
 '36': 'az:district:qabala',
 '37': 'az:district:gobustan',
 '38': 'az:district:qusar',
 '39': 'az:district:qubadli',
 '40': 'az:district:quba',
 '41': 'az:district:lachin',
 '43': 'az:district:lerik',
 '44': 'az:district:masally',
 '45': 'az:municipality:mingachevir',
 '46': 'az:municipality:naftalan',
 '47': 'az:district:neftchala',
 '48': 'az:district:oghuz',
 '49': 'az:district:saatly',
 '50': 'az:municipality:sumqayit',
 '51': 'az:district:samukh',
 '52': 'az:district:salyan',
 '53': 'az:district:siazan',
 '54': 'az:district:sabirabad',
 '56': 'az:district:shamakhi',
 '57': 'az:district:shamkir',
 '58': 'az:district:shusha',
 '59': 'az:district:tartar',
 '60': 'az:district:tovuz',
 '61': 'az:district:ujar',
 '62': 'az:district:zaqatala',
 '63': 'az:district:zardab',
 '64': 'az:district:zangilan',
 '65': 'az:district:yardymli',
 '80': 'az:district:khizi',
}
# city/district shared blocks: (city sid, district sid, city max suffix).
split = {'42': ('az:municipality:lankaran', 'az:district:lankaran', '4205'),
 '55': ('az:municipality:shaki', 'az:district:shaki', '5508'),
 '66': ('az:municipality:yevlakh', 'az:district:yevlakh', '6605')}
def exp_area(pc):
    b = pc[3:5]
    if b in split:
        c, d, mx = split[b]
        return c if pc[3:] <= mx else d
    return block.get(b)
off = {pc: a for pc, a in pin.items() if exp_area(pc) != a}
check('block-grain-clean', not off, str(list(off.items())[:3]))
check('blocks-63-plus-3-split', len(block) == 63 and len(split) == 3,
      f'{len(block)}+{len(split)}')
check('no-14xx', not [c for c in cset if c[3:5] == '14'])
check('no-67-79xx', not [c for c in cset if 67 <= int(c[3:5]) <= 79])
check('uncovered-10', sorted(s for s in byid if byid[s]['level'] == '1' and Counter(pin.values()).get(s, 0) == 0) == ['az:autonomous_republic:nakhchivan', 'az:district:babek', 'az:district:jabrayil', 'az:district:julfa', 'az:district:kangarli', 'az:district:ordubad', 'az:district:sadarak', 'az:district:shahbuz', 'az:district:sharur', 'az:municipality:nakhchivan'])
# UPU AZE anchors: AZ1010/AZ1014/AZ1000 Baku (spaced form shipped).
for pc in ['AZ 1010', 'AZ 1014', 'AZ 1000']:
    check(f'upu-{pc}', pin.get(pc) == 'az:municipality:baku', str(pin.get(pc)))
# city/district split pins: NN00+eponym+N Sayli -> city; villages -> district.
for pc, sid in [
 ('AZ 5500', 'az:municipality:shaki'),
 ('AZ 5508', 'az:municipality:shaki'),
 ('AZ 5511', 'az:district:shaki'),
 ('AZ 5540', 'az:district:shaki'),
 ('AZ 4200', 'az:municipality:lankaran'),
 ('AZ 4205', 'az:municipality:lankaran'),
 ('AZ 4212', 'az:district:lankaran'),
 ('AZ 4244', 'az:district:lankaran'),
 ('AZ 6600', 'az:municipality:yevlakh'),
 ('AZ 6605', 'az:municipality:yevlakh'),
 ('AZ 6611', 'az:district:yevlakh'),
 ('AZ 6636', 'az:district:yevlakh'),
]:
    check(f'split-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# Nominatim-reverse pins (GN coords; verdict.md notes the two edges).
for pc, sid in [
 ('AZ 8000', 'az:district:khizi'),
 ('AZ 8011', 'az:district:khizi'),
 ('AZ 3218', 'az:district:kalbajar'),
 ('AZ 3900', 'az:district:qubadli'),
 ('AZ 2600', 'az:municipality:khankendi'),
 ('AZ 2900', 'az:district:khojaly'),
 ('AZ 2800', 'az:district:khojavend'),
 ('AZ 4100', 'az:district:lachin'),
 ('AZ 6400', 'az:district:zangilan'),
 ('AZ 2010', 'az:municipality:ganja'),
]:
    check(f'geo-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# addressed-sighting pins (gomap.az POI): Astara 0721, Dashkasan 1600,
# Khizi 8000, Shaki-city 5500, Qubadli 3900, Khojavend 2800.
for pc, sid in [('AZ 0721', 'az:district:astara'),
 ('AZ 1600', 'az:district:dashkasan'), ('AZ 8000', 'az:district:khizi'),
 ('AZ 5500', 'az:municipality:shaki'), ('AZ 3900', 'az:district:qubadli'),
 ('AZ 2800', 'az:district:khojavend')]:
    check(f'sight-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# per-area primary counts.
exp_counts = {
 'az:district:absheron': 24,
 'az:district:agdam': 8,
 'az:district:agdash': 15,
 'az:district:aghjabadi': 26,
 'az:district:agstafa': 18,
 'az:district:agsu': 16,
 'az:district:astara': 16,
 'az:district:balakan': 21,
 'az:district:barda': 22,
 'az:district:beylagan': 21,
 'az:district:bilasuvar': 12,
 'az:district:dashkasan': 4,
 'az:district:fuzuli': 6,
 'az:district:gdby': 22,
 'az:district:gobustan': 8,
 'az:district:goranboy': 23,
 'az:district:goychay': 27,
 'az:district:goygol': 13,
 'az:district:hajigabul': 13,
 'az:district:imishli': 18,
 'az:district:ismayilli': 24,
 'az:district:jalilabad': 21,
 'az:district:kalbajar': 2,
 'az:district:khachmaz': 27,
 'az:district:khizi': 2,
 'az:district:khojaly': 1,
 'az:district:khojavend': 1,
 'az:district:kurdamir': 19,
 'az:district:lachin': 1,
 'az:district:lankaran': 22,
 'az:district:lerik': 17,
 'az:district:masally': 40,
 'az:district:neftchala': 16,
 'az:district:oghuz': 11,
 'az:district:qabala': 26,
 'az:district:qakh': 19,
 'az:district:qazakh': 22,
 'az:district:quba': 23,
 'az:district:qubadli': 1,
 'az:district:qusar': 21,
 'az:district:saatly': 22,
 'az:district:sabirabad': 28,
 'az:district:salyan': 20,
 'az:district:samukh': 19,
 'az:district:shabran': 12,
 'az:district:shaki': 27,
 'az:district:shamakhi': 19,
 'az:district:shamkir': 34,
 'az:district:shusha': 1,
 'az:district:siazan': 7,
 'az:district:tartar': 13,
 'az:district:tovuz': 24,
 'az:district:ujar': 20,
 'az:district:yardymli': 7,
 'az:district:yevlakh': 21,
 'az:district:zangilan': 1,
 'az:district:zaqatala': 30,
 'az:district:zardab': 14,
 'az:municipality:baku': 142,
 'az:municipality:ganja': 22,
 'az:municipality:khankendi': 1,
 'az:municipality:lankaran': 6,
 'az:municipality:mingachevir': 11,
 'az:municipality:naftalan': 2,
 'az:municipality:shaki': 9,
 'az:municipality:shirvan': 7,
 'az:municipality:sumqayit': 12,
 'az:municipality:yevlakh': 6,
}
have = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
wrong = [sid for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-area-counts-68', not wrong, str(wrong))
check('covered-68', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 68)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
