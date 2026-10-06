import csv, sys
# Iceland gate. B10 revisit: verify-only. Tree 8 regions
# (codes 1-8 = ISO 3166-2:IS) + 61 municipalities pinned vs
# the en-wp 2026 roster; 174 codes = the 195-code register
# universe (is.wiki Pósturinn-derived list == GeoNames IS.zip
# set-identical) minus the 21 documented exclusions (18 box,
# 150/155 institutions, 512 Ísafjarðardjúp per overlay rules);
# 4 duals pinned (276 Kjós+Hvalfjörður explicit, 641/701/851
# neighbour-served: no register code names Tjörnes,
# Fljótsdalshreppur, or Ásahreppur).
# Run from repo root:
# python3 docs/agents/audit/gate_is.py
A = './packages/addressing/resources/geography/iceland-address-areas.csv'
C = './packages/addressing/resources/geography/iceland-postal-codes.csv'
L = './packages/addressing/resources/geography/iceland-postal-code-areas.csv'
REGIONS = {'is:region:capital': ('Capital', '1'),
           'is:region:southern-peninsula': ('Southern Peninsula', '2'),
           'is:region:western': ('Western', '3'),
           'is:region:westfjords': ('Westfjords', '4'),
           'is:region:northwestern': ('Northwestern', '5'),
           'is:region:northeastern': ('Northeastern', '6'),
           'is:region:eastern': ('Eastern', '7'),
           'is:region:southern': ('Southern', '8')}
MUNI = {
 'is:region:capital': ['Reykjavík', 'Kópavogur', 'Seltjarnarnes',
                       'Garðabær', 'Hafnarfjörður', 'Mosfellsbær',
                       'Kjósarhreppur'],
 'is:region:southern-peninsula': ['Reykjanesbær', 'Grindavíkurbær',
                                  'Suðurnesjabær', 'Vogar'],
 'is:region:western': ['Akranes', 'Hvalfjarðarsveit', 'Borgarbyggð',
                       'Grundarfjarðarbær', 'Eyja- og Miklaholtshreppur',
                       'Snæfellsbær', 'Stykkishólmur', 'Dalabyggð'],
 'is:region:westfjords': ['Bolungarvík', 'Ísafjarðarbær',
                          'Reykhólahreppur', 'Vesturbyggð', 'Súðavík',
                          'Árneshreppur', 'Kaldrananeshreppur',
                          'Strandabyggð'],
 'is:region:northwestern': ['Húnaþing vestra', 'Skagaströnd',
                            'Húnabyggð', 'Skagafjörður'],
 'is:region:northeastern': ['Akureyri', 'Norðurþing', 'Fjallabyggð',
                            'Dalvíkurbyggð', 'Eyjafjarðarsveit',
                            'Hörgársveit', 'Svalbarðsstrandarhreppur',
                            'Grýtubakkahreppur', 'Tjörneshreppur',
                            'Þingeyjarsveit', 'Langanesbyggð'],
 'is:region:eastern': ['Fjarðabyggð', 'Múlaþing',
                       'Vopnafjarðarhreppur', 'Fljótsdalshreppur'],
 'is:region:southern': ['Hornafjörður', 'Vestmannaeyjar', 'Árborg',
                        'Mýrdalshreppur', 'Skaftárhreppur', 'Ásahreppur',
                        'Rangárþing eystra', 'Rangárþing ytra',
                        'Hrunamannahreppur', 'Hveragerði', 'Ölfus',
                        'Grímsnes- og Grafningshreppur',
                        'Skeiða- og Gnúpverjahreppur', 'Bláskógabyggð',
                        'Flóahreppur']}
EXCLUDED = {'121', '123', '124', '125', '127', '128', '129', '130',
            '132', '150', '155', '172', '202', '212', '222', '232',
            '302', '512', '602', '802', '902'}
DUALS = {'276': ('is:municipality:kjosarhreppur',
                 'is:municipality:hvalfjararsveit'),
         '641': ('is:municipality:noruring',
                 'is:municipality:tjorneshreppur'),
         '701': ('is:municipality:mulaing',
                 'is:municipality:fljotsdalshreppur'),
         '851': ('is:municipality:rangaring-ytra',
                 'is:municipality:asahreppur')}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-69', len(areas) == 69, str(len(areas)))
rg = [r for r in areas if r['type'] == 'region']
got_r = {r['source_id']: (r['name'], r['code']) for r in rg}
check('regions-8', got_r == REGIONS,
      str({k for k in REGIONS if got_r.get(k) != REGIONS[k]}))
mu = [r for r in areas if r['type'] == 'municipality']
check('muni-61', len(mu) == 61, str(len(mu)))
got = {}
for r in mu:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in MUNI if sorted(got.get(k, [])) != sorted(MUNI[k])}
check('muni-xmap', not bad, str(sorted(bad)))
codes = [r['code'] for r in csv.DictReader(open(C, encoding='utf-8'))]
check('codes-174', len(codes) == 174, str(len(codes)))
check('codes-3digit', all(len(c) == 3 and c.isdigit() for c in codes))
check('excluded-21', not (set(codes) & EXCLUDED))
links = [(r['postcode'], r['area_source_id'], r['is_primary'])
         for r in csv.DictReader(open(L, encoding='utf-8'))]
check('links-178', len(links) == 178, str(len(links)))
check('primaries-174', sum(1 for _, _, t in links if t == 'true') == 174)
by = {}
for p, a, t in links:
    by.setdefault(p, []).append((a, t))
multi = {p: v for p, v in by.items() if len(v) > 1}
check('duals-4', set(multi) == set(DUALS), str(sorted(multi)))
ok = True
for p, (pp, ss) in DUALS.items():
    prim = [a for a, t in by.get(p, []) if t == 'true']
    sec = [a for a, t in by.get(p, []) if t == 'false']
    ok = ok and prim == [pp] and sec == [ss]
check('dual-legs', ok)
covered = {a for _, a, _ in links}
check('no-codeless', all(r['source_id'] in covered for r in mu))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)
