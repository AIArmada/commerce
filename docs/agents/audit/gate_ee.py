import csv, re, sys
from collections import Counter
# Estonia gate. Pins the B11 fix-and-fill pass: 93 areas (15 EHAK counties +
# 78 post-merger municipalities: 63 rural + 15 urban, Toila retired 28.11.2025)
# + 5481 codes / 5499 links. Fixes vs pre-state: 11 stale EHAK code cells
# (430/431 swap + Antsla 142->145, Johvi 251->250, Marjamaa 503->502,
# Narva-Joesuu 514->515, Pohja-Parnumaa 638->637, Saue 726->725, Sillamae
# 735->736, Tori 809->806, Valga 855->857) and 84 fill codes, each
# postiindeks.ee + Maa-amet ADS (In-Aadress street sihtnumber) agreed:
# Narva 21026-21076 x50, Noarootsi 912xx x21, Viimsi 74022-24, Hiiumaa
# 92141/92179, Antsla 66304/66306, Tartu-vald 60545, Kehtna 79054, Tallinn
# 13525, Peipsiaare 60429, Viljandi-vald 70182, Voru-vald 65501.
# Oracles: GeoNames EE.zip (5398 rows / 5293 codes, all present), Maa-amet ADS,
# EMTA KOV table, stat.ee EHAK changes doc, ISO 3166-2:EE, UPU EST (5-digit),
# Omniva locations feed (postkontor bases), postiindeks.ee. Run from repo root:
# python3 docs/agents/audit/gate_ee.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/estonia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/estonia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/estonia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes) ---
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
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 15 counties + 78 municipalities ---
check('areas-93', len(rows) == 93, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-15-counties', len(l1) == 15 and all(r['type'] == 'county' and not r['parent_source_id'] for r in l1))
check('l1-ehak-codes', sorted(r['code'] for r in l1) == ['37', '39', '45', '50', '52', '56', '60', '64', '68', '71', '74', '79', '81', '84', '87'])
check('l2-78', len(l2) == 78, str(len(l2)))
check('l2-type-split', Counter(r['type'] for r in l2) == {'rural_municipality': 63, 'urban_municipality': 15}, str(Counter(r['type'] for r in l2)))
check('areas-unique-ids', len(byid) == 93)
check('areas-country-EE', all(r['country_code'] == 'EE' for r in rows))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
check('no-toila', not any('toila' in r['source_id'] or r['name'] == 'Toila' for r in rows))
# per-county L2 membership: 16/1/7/3/3/3/8/7/3/4/3/8/3/4/5
parent = {r['source_id']: r['parent_source_id'] for r in l2}
mem = Counter(parent[r['source_id']] for r in l2)
check('county-membership', mem == {'ee:county:harju': 16, 'ee:county:hiiu': 1, 'ee:county:ida-viru': 7, 'ee:county:jarva': 3, 'ee:county:jogeva': 3, 'ee:county:laane': 3, 'ee:county:laane-viru': 8, 'ee:county:parnu': 7, 'ee:county:polva': 3, 'ee:county:rapla': 4, 'ee:county:saare': 3, 'ee:county:tartu': 8, 'ee:county:valga': 3, 'ee:county:viljandi': 4, 'ee:county:voru': 5}, str(dict(mem)))
# --- B11 EHAK fixes: full 78-code table (post-state) ---
ehak = {
 'ee:rural_municipality:alutaguse': '130', 'ee:rural_municipality:anija': '141',
 'ee:rural_municipality:antsla': '145', 'ee:rural_municipality:elva': '171',
 'ee:urban_municipality:haapsalu': '184', 'ee:rural_municipality:haljala': '191',
 'ee:rural_municipality:harku': '198', 'ee:rural_municipality:hiiumaa': '205',
 'ee:rural_municipality:haademeeste': '214', 'ee:rural_municipality:joelahtme': '245',
 'ee:rural_municipality:jogeva': '247', 'ee:rural_municipality:johvi': '250',
 'ee:rural_municipality:jarva': '255', 'ee:rural_municipality:kadrina': '272',
 'ee:rural_municipality:kambja': '283', 'ee:rural_municipality:kanepi': '284',
 'ee:rural_municipality:kastre': '291', 'ee:rural_municipality:kehtna': '293',
 'ee:urban_municipality:keila': '296', 'ee:rural_municipality:kihnu': '303',
 'ee:rural_municipality:kiili': '305', 'ee:rural_municipality:kohila': '317',
 'ee:urban_municipality:kohtla-jarve': '321', 'ee:rural_municipality:kose': '338',
 'ee:rural_municipality:kuusalu': '353', 'ee:urban_municipality:loksa': '424',
 'ee:rural_municipality:laane-harju': '431', 'ee:rural_municipality:laaneranna': '430',
 'ee:rural_municipality:luunja': '432', 'ee:rural_municipality:laane-nigula': '441',
 'ee:rural_municipality:luganuse': '442', 'ee:urban_municipality:maardu': '446',
 'ee:rural_municipality:muhu': '478', 'ee:rural_municipality:mulgi': '480',
 'ee:rural_municipality:mustvee': '486', 'ee:rural_municipality:marjamaa': '502',
 'ee:urban_municipality:narva': '511', 'ee:urban_municipality:narva-joesuu': '515',
 'ee:rural_municipality:noo': '528', 'ee:rural_municipality:otepaa': '557',
 'ee:urban_municipality:paide': '567', 'ee:rural_municipality:peipsiaare': '586',
 'ee:rural_municipality:pohja-sakala': '615', 'ee:rural_municipality:poltsamaa': '618',
 'ee:rural_municipality:polva': '622', 'ee:urban_municipality:parnu': '624',
 'ee:rural_municipality:pohja-parnumaa': '637', 'ee:rural_municipality:raasiku': '651',
 'ee:rural_municipality:rae': '653', 'ee:rural_municipality:rakvere': '661',
 'ee:urban_municipality:rakvere': '663', 'ee:rural_municipality:rapla': '668',
 'ee:rural_municipality:ruhnu': '689', 'ee:rural_municipality:rouge': '698',
 'ee:rural_municipality:rapina': '708', 'ee:rural_municipality:saarde': '712',
 'ee:rural_municipality:saaremaa': '714', 'ee:rural_municipality:saku': '719',
 'ee:rural_municipality:saue': '725', 'ee:rural_municipality:setomaa': '732',
 'ee:urban_municipality:sillamae': '736', 'ee:urban_municipality:tallinn': '784',
 'ee:rural_municipality:tapa': '792', 'ee:urban_municipality:tartu': '793',
 'ee:rural_municipality:tartu': '796', 'ee:rural_municipality:tori': '806',
 'ee:rural_municipality:torva': '824', 'ee:rural_municipality:turi': '834',
 'ee:rural_municipality:valga': '857', 'ee:rural_municipality:viimsi': '890',
 'ee:urban_municipality:viljandi': '897', 'ee:rural_municipality:viljandi': '899',
 'ee:rural_municipality:vinni': '901', 'ee:rural_municipality:viru-nigula': '903',
 'ee:rural_municipality:vormsi': '907', 'ee:rural_municipality:voru': '917',
 'ee:urban_municipality:voru': '919', 'ee:rural_municipality:vaike-maarja': '928',
}
wrongcode = [(sid, byid[sid]['code'], c) for sid, c in ehak.items() if byid.get(sid, {}).get('code') != c]
check('ehak-78-exact', not wrongcode and len(ehak) == 78, str(wrongcode[:4]))
check('ehak-unique', len(set(ehak.values())) == 78)
# name pins incl. pre-existing diacritic corrections
check('names-kept', byid['ee:rural_municipality:noo']['name'] == 'Nõo'
    and byid['ee:rural_municipality:pohja-parnumaa']['name'] == 'Põhja-Pärnumaa'
    and byid['ee:rural_municipality:poltsamaa']['name'] == 'Põltsamaa'
    and byid['ee:rural_municipality:joelahtme']['name'] == 'Jõelähtme')
check('twins-share-names', byid['ee:urban_municipality:tartu']['name'] == byid['ee:rural_municipality:tartu']['name'] == 'Tartu'
    and byid['ee:urban_municipality:voru']['name'] == byid['ee:rural_municipality:voru']['name'] == 'Võru'
    and byid['ee:urban_municipality:viljandi']['name'] == byid['ee:rural_municipality:viljandi']['name'] == 'Viljandi'
    and byid['ee:urban_municipality:rakvere']['name'] == byid['ee:rural_municipality:rakvere']['name'] == 'Rakvere')
# --- postal: 5481 codes / 5499 links ---
check('codes-5481', len(codes) == 5481, str(len(codes)))
check('links-5499', len(links) == 5499, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == len(clist))
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{5}$', c)]
check('code-format-5digit', not bad, str(bad[:3]))
check('codes-range', min(clist) == '10001' and max(clist) == '94766', f'{min(clist)}-{max(clist)}')
check('codes-country-EE', all(c['country_code'] == 'EE' for c in codes))
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
check('every-code-one-primary', len(prim) == len(codes) and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('secondary-18', sum(1 for l in links if l['is_primary'] == 'false') == 18)
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('links-all-l2', all(byid[l['area_source_id']]['level'] == '2' for l in links))
# --- 17 dual links: exact table, all same-county ---
dual = {
 '10112': ('ee:urban_municipality:tallinn', ['ee:rural_municipality:rae']),
 '11218': ('ee:urban_municipality:tallinn', ['ee:rural_municipality:rae']),
 '30503': ('ee:urban_municipality:kohtla-jarve', ['ee:rural_municipality:johvi']),
 '41534': ('ee:urban_municipality:kohtla-jarve', ['ee:rural_municipality:johvi']),
 '41537': ('ee:urban_municipality:kohtla-jarve', ['ee:rural_municipality:johvi']),
 '41538': ('ee:urban_municipality:kohtla-jarve', ['ee:rural_municipality:johvi']),
 '41542': ('ee:urban_municipality:kohtla-jarve', ['ee:rural_municipality:johvi']),
 '41551': ('ee:urban_municipality:kohtla-jarve', ['ee:rural_municipality:johvi']),
 '45202': ('ee:rural_municipality:haljala', ['ee:rural_municipality:kadrina']),
 '60532': ('ee:urban_municipality:tartu', ['ee:rural_municipality:tartu']),
 '65555': ('ee:urban_municipality:voru', ['ee:rural_municipality:voru']),
 '70101': ('ee:urban_municipality:viljandi', ['ee:rural_municipality:viljandi']),
 '74114': ('ee:urban_municipality:maardu', ['ee:rural_municipality:joelahtme']),
 '74115': ('ee:urban_municipality:maardu', ['ee:urban_municipality:tallinn', 'ee:rural_municipality:joelahtme']),
 '76902': ('ee:rural_municipality:harku', ['ee:urban_municipality:tallinn']),
 '76912': ('ee:rural_municipality:harku', ['ee:urban_municipality:tallinn']),
 '85009': ('ee:urban_municipality:parnu', ['ee:rural_municipality:tori']),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
wrongdual = [p for p, (pp, sss) in dual.items()
             if prim.get(p) != pp or sorted(l['area_source_id'] for l in havemulti.get(p, []) if l['is_primary'] == 'false') != sorted(sss)]
check('dual-set-17-exact', set(havemulti) == set(dual), str(sorted(set(havemulti) ^ set(dual))[:5]))
check('dual-pairs-exact', not wrongdual, str(wrongdual[:5]))
check('duals-same-county', all(len({parent[l['area_source_id']] for l in v}) == 1 for v in havemulti.values()))
# --- B11 fill: 84 codes, exact single-primary mapping ---
fill = {}
for c in ['21026', '21027', '21028', '21029', '21030', '21031', '21032', '21033', '21034', '21035', '21036', '21037', '21038', '21039', '21040', '21041', '21042', '21043', '21044', '21045', '21046', '21047', '21048', '21049', '21050', '21051', '21052', '21053', '21054', '21055', '21056', '21057', '21058', '21059', '21060', '21061', '21062', '21063', '21064', '21066', '21067', '21068', '21069', '21070', '21071', '21072', '21073', '21074', '21075', '21076']:
    fill[c] = 'ee:urban_municipality:narva'
for c in ['91201', '91202', '91203', '91204', '91205', '91206', '91208', '91211', '91212', '91213', '91214', '91216', '91217', '91218', '91219', '91220', '91221', '91230', '91231', '91232', '91233']:
    fill[c] = 'ee:rural_municipality:laane-nigula'
fill.update({'13525': 'ee:urban_municipality:tallinn', '60429': 'ee:rural_municipality:peipsiaare',
 '60545': 'ee:rural_municipality:tartu', '65501': 'ee:rural_municipality:voru',
 '66304': 'ee:rural_municipality:antsla', '66306': 'ee:rural_municipality:antsla',
 '70182': 'ee:rural_municipality:viljandi', '74022': 'ee:rural_municipality:viimsi',
 '74023': 'ee:rural_municipality:viimsi', '74024': 'ee:rural_municipality:viimsi',
 '79054': 'ee:rural_municipality:kehtna', '92141': 'ee:rural_municipality:hiiumaa',
 '92179': 'ee:rural_municipality:hiiumaa'})
wrongfill = [c for c, sid in fill.items() if len(by.get(c, [])) != 1 or prim.get(c) != sid]
check('fill-84-exact', len(fill) == 84 and not wrongfill, str(wrongfill[:5]))
# holds stay absent: 9 single-signal pii codes + unassigned gaps
for h in ['15050', '15172', '41598', '43299', '50050', '50096', '66710', '80099', '86217', '21065', '91209', '91210', '91222', '91229']:
    check(f'hold-absent-{h}', h not in by)
# --- cluster pins ---
check('pin-10001-tallinn', prim.get('10001') == 'ee:urban_municipality:tallinn')
check('pin-20308-narva', prim.get('20308') == 'ee:urban_municipality:narva')
check('pin-21026-narva-fill', prim.get('21026') == 'ee:urban_municipality:narva')
check('pin-41702-johvi-ex-toila', prim.get('41702') == 'ee:rural_municipality:johvi')
check('pin-30503-kohtla-primary', prim.get('30503') == 'ee:urban_municipality:kohtla-jarve')
check('pin-74115-maardu-triple', prim.get('74115') == 'ee:urban_municipality:maardu')
check('pin-91201-laane-nigula-fill', prim.get('91201') == 'ee:rural_municipality:laane-nigula')
check('pin-91207-bundled-laane-nigula', prim.get('91207') == 'ee:rural_municipality:laane-nigula')
check('pin-13525-tallinn-fill', prim.get('13525') == 'ee:urban_municipality:tallinn')
check('pin-76601-keila', prim.get('76601') == 'ee:urban_municipality:keila')
check('pin-74801-loksa', prim.get('74801') == 'ee:urban_municipality:loksa')
check('pin-90501-haapsalu', prim.get('90501') == 'ee:urban_municipality:haapsalu')
# --- per-county primary counts (15) ---
expc = {'ee:county:harju': 733, 'ee:county:hiiu': 196, 'ee:county:ida-viru': 332, 'ee:county:jarva': 223, 'ee:county:jogeva': 226, 'ee:county:laane': 209, 'ee:county:laane-viru': 442, 'ee:county:parnu': 458, 'ee:county:polva': 204, 'ee:county:rapla': 328, 'ee:county:saare': 508, 'ee:county:tartu': 471, 'ee:county:valga': 163, 'ee:county:viljandi': 314, 'ee:county:voru': 674}
havec = Counter(parent[s] for s in prim.values())
check('per-county-primaries', dict(havec) == expc, str([(k, havec.get(k, 0), v) for k, v in expc.items() if havec.get(k, 0) != v]))
# --- per-municipality primary counts, full 78-row table ---
expm = {
 'ee:rural_municipality:alutaguse': 74, 'ee:rural_municipality:anija': 35,
 'ee:rural_municipality:antsla': 43, 'ee:rural_municipality:elva': 92,
 'ee:rural_municipality:haademeeste': 30, 'ee:rural_municipality:haljala': 75,
 'ee:rural_municipality:harku': 27, 'ee:rural_municipality:hiiumaa': 196,
 'ee:rural_municipality:jarva': 106, 'ee:rural_municipality:joelahtme': 36,
 'ee:rural_municipality:jogeva': 101, 'ee:rural_municipality:johvi': 52,
 'ee:rural_municipality:kadrina': 42, 'ee:rural_municipality:kambja': 45,
 'ee:rural_municipality:kanepi': 52, 'ee:rural_municipality:kastre': 51,
 'ee:rural_municipality:kehtna': 52, 'ee:rural_municipality:kihnu': 4,
 'ee:rural_municipality:kiili': 16, 'ee:rural_municipality:kohila': 56,
 'ee:rural_municipality:kose': 64, 'ee:rural_municipality:kuusalu': 70,
 'ee:rural_municipality:laane-harju': 59, 'ee:rural_municipality:laane-nigula': 124,
 'ee:rural_municipality:laaneranna': 156, 'ee:rural_municipality:luganuse': 59,
 'ee:rural_municipality:luunja': 21, 'ee:rural_municipality:marjamaa': 119,
 'ee:rural_municipality:muhu': 52, 'ee:rural_municipality:mulgi': 73,
 'ee:rural_municipality:mustvee': 60, 'ee:rural_municipality:noo': 22,
 'ee:rural_municipality:otepaa': 59, 'ee:rural_municipality:peipsiaare': 92,
 'ee:rural_municipality:pohja-parnumaa': 89, 'ee:rural_municipality:pohja-sakala': 82,
 'ee:rural_municipality:poltsamaa': 65, 'ee:rural_municipality:polva': 82,
 'ee:rural_municipality:raasiku': 15, 'ee:rural_municipality:rae': 33,
 'ee:rural_municipality:rakvere': 51, 'ee:rural_municipality:rapina': 70,
 'ee:rural_municipality:rapla': 101, 'ee:rural_municipality:rouge': 275,
 'ee:rural_municipality:ruhnu': 1, 'ee:rural_municipality:saarde': 41,
 'ee:rural_municipality:saaremaa': 455, 'ee:rural_municipality:saku': 23,
 'ee:rural_municipality:saue': 58, 'ee:rural_municipality:setomaa': 157,
 'ee:rural_municipality:tapa': 65, 'ee:rural_municipality:tartu': 78,
 'ee:rural_municipality:tori': 46, 'ee:rural_municipality:torva': 44,
 'ee:rural_municipality:turi': 65, 'ee:rural_municipality:vaike-maarja': 68,
 'ee:rural_municipality:valga': 60, 'ee:rural_municipality:viimsi': 21,
 'ee:rural_municipality:viljandi': 138, 'ee:rural_municipality:vinni': 77,
 'ee:rural_municipality:viru-nigula': 50, 'ee:rural_municipality:vormsi': 14,
 'ee:rural_municipality:voru': 187, 'ee:urban_municipality:haapsalu': 71,
 'ee:urban_municipality:keila': 8, 'ee:urban_municipality:kohtla-jarve': 31,
 'ee:urban_municipality:loksa': 4, 'ee:urban_municipality:maardu': 9,
 'ee:urban_municipality:narva': 85, 'ee:urban_municipality:narva-joesuu': 26,
 'ee:urban_municipality:paide': 52, 'ee:urban_municipality:parnu': 92,
 'ee:urban_municipality:rakvere': 14, 'ee:urban_municipality:sillamae': 5,
 'ee:urban_municipality:tallinn': 255, 'ee:urban_municipality:tartu': 70,
 'ee:urban_municipality:viljandi': 21, 'ee:urban_municipality:voru': 12,
}
havem = Counter(prim.values())
wrongm = [(sid, havem.get(sid, 0), n) for sid, n in expm.items() if havem.get(sid, 0) != n]
check('per-municipality-primaries-78', not wrongm, str(wrongm[:4]))
check('covered-78', sum(1 for sid in expm if havem.get(sid, 0) > 0) == 78)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
