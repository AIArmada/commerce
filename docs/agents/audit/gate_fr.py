import csv, re, sys
from collections import Counter
# France gate. Pins the B13 fix-and-fill pass (20316 codes / 20340 links,
# 24 duals) plus the ISO 3166-2:FR / INSEE-COG tree (18 regions +
# 102 departments with the 69D/69M Lyon split). Oracles: fresh GeoNames
# FR.zip (51,611 rows), La Poste Hexasmal via datanova (39,192 rows),
# geo.api.gouv.fr (regions/departements/communes/epcis 200046977),
# ISO 3166-2:FR table, addressed-usage spot proofs (AIR/SP/CEDEX-9).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_fr.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/france-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/france-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/france-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
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
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 18 regions + 102 departments ---
check('areas-120', len(rows) == 120, str(len(rows)))
check('regions-18', sum(1 for r in rows if r['type'] == 'region') == 18)
check('departments-102', sum(1 for r in rows if r['type'] == 'department') == 102)
check('l1-18', sum(1 for r in rows if r['level'] == '1') == 18)
check('l2-102', sum(1 for r in rows if r['level'] == '2') == 102)
iso_r = {'fr:region:auvergne-rhone-alpes': ('Auvergne-Rhône-Alpes', 'ARA'),
         'fr:region:grand-est': ('Grand Est', 'GES'),
         'fr:region:corse': ('Corse', '20R'),
         'fr:region:french-guiana': ('Guyane', '973'),
         'fr:region:provence-alpes-cote-dazur': ("Provence-Alpes-Côte-d'Azur", 'PAC'),
         'fr:region:pays-de-la-loire': ('Pays-de-la-Loire', 'PDL')}
for sid, (name, code) in iso_r.items():
    r = byid.get(sid)
    check(f'region-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
iso_d = {'fr:department:ain': ('Ain', '01', 'auvergne-rhone-alpes'),
         'fr:department:corse-du-sud': ('Corse-du-Sud', '2A', 'corse'),
         'fr:department:haute-corse': ('Haute-Corse', '2B', 'corse'),
         'fr:department:rhone': ('Rhône', '69D', 'auvergne-rhone-alpes'),
         'fr:department:lyon': ('Métropole de Lyon', '69M', 'auvergne-rhone-alpes'),
         'fr:department:paris': ('Paris', '75', 'ile-de-france'),
         'fr:department:bas-rhin': ('Bas-Rhin', '67', 'grand-est'),
         'fr:department:mayotte': ('Mayotte', '976', 'mayotte')}
for sid, (name, code, par) in iso_d.items():
    r = byid.get(sid)
    check(f'dept-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '2'
          and r['parent_source_id'] == f'fr:region:{par}', str(r))
deptfull = {
 '01': ('Ain', 'ARA'),
 '02': ('Aisne', 'HDF'),
 '03': ('Allier', 'ARA'),
 '04': ('Alpes-de-Haute-Provence', 'PAC'),
 '05': ('Hautes-Alpes', 'PAC'),
 '06': ('Alpes-Maritimes', 'PAC'),
 '07': ('Ardèche', 'ARA'),
 '08': ('Ardennes', 'GES'),
 '09': ('Ariège', 'OCC'),
 '10': ('Aube', 'GES'),
 '11': ('Aude', 'OCC'),
 '12': ('Aveyron', 'OCC'),
 '13': ('Bouches-du-Rhône', 'PAC'),
 '14': ('Calvados', 'NOR'),
 '15': ('Cantal', 'ARA'),
 '16': ('Charente', 'NAQ'),
 '17': ('Charente-Maritime', 'NAQ'),
 '18': ('Cher', 'CVL'),
 '19': ('Corrèze', 'NAQ'),
 '21': ("Côte-d'Or", 'BFC'),
 '22': ("Côtes-d'Armor", 'BRE'),
 '23': ('Creuse', 'NAQ'),
 '24': ('Dordogne', 'NAQ'),
 '25': ('Doubs', 'BFC'),
 '26': ('Drôme', 'ARA'),
 '27': ('Eure', 'NOR'),
 '28': ('Eure-et-Loir', 'CVL'),
 '29': ('Finistère', 'BRE'),
 '2A': ('Corse-du-Sud', '20R'),
 '2B': ('Haute-Corse', '20R'),
 '30': ('Gard', 'OCC'),
 '31': ('Haute-Garonne', 'OCC'),
 '32': ('Gers', 'OCC'),
 '33': ('Gironde', 'NAQ'),
 '34': ('Hérault', 'OCC'),
 '35': ('Ille-et-Vilaine', 'BRE'),
 '36': ('Indre', 'CVL'),
 '37': ('Indre-et-Loire', 'CVL'),
 '38': ('Isère', 'ARA'),
 '39': ('Jura', 'BFC'),
 '40': ('Landes', 'NAQ'),
 '41': ('Loir-et-Cher', 'CVL'),
 '42': ('Loire', 'ARA'),
 '43': ('Haute-Loire', 'ARA'),
 '44': ('Loire-Atlantique', 'PDL'),
 '45': ('Loiret', 'CVL'),
 '46': ('Lot', 'OCC'),
 '47': ('Lot-et-Garonne', 'NAQ'),
 '48': ('Lozère', 'OCC'),
 '49': ('Maine-et-Loire', 'PDL'),
 '50': ('Manche', 'NOR'),
 '51': ('Marne', 'GES'),
 '52': ('Haute-Marne', 'GES'),
 '53': ('Mayenne', 'PDL'),
 '54': ('Meurthe-et-Moselle', 'GES'),
 '55': ('Meuse', 'GES'),
 '56': ('Morbihan', 'BRE'),
 '57': ('Moselle', 'GES'),
 '58': ('Nièvre', 'BFC'),
 '59': ('Nord', 'HDF'),
 '60': ('Oise', 'HDF'),
 '61': ('Orne', 'NOR'),
 '62': ('Pas-de-Calais', 'HDF'),
 '63': ('Puy-de-Dôme', 'ARA'),
 '64': ('Pyrénées-Atlantiques', 'NAQ'),
 '65': ('Hautes-Pyrénées', 'OCC'),
 '66': ('Pyrénées-Orientales', 'OCC'),
 '67': ('Bas-Rhin', 'GES'),
 '68': ('Haut-Rhin', 'GES'),
 '69D': ('Rhône', 'ARA'),
 '69M': ('Métropole de Lyon', 'ARA'),
 '70': ('Haute-Saône', 'BFC'),
 '71': ('Saône-et-Loire', 'BFC'),
 '72': ('Sarthe', 'PDL'),
 '73': ('Savoie', 'ARA'),
 '74': ('Haute-Savoie', 'ARA'),
 '75': ('Paris', 'IDF'),
 '76': ('Seine-Maritime', 'NOR'),
 '77': ('Seine-et-Marne', 'IDF'),
 '78': ('Yvelines', 'IDF'),
 '79': ('Deux-Sèvres', 'NAQ'),
 '80': ('Somme', 'HDF'),
 '81': ('Tarn', 'OCC'),
 '82': ('Tarn-et-Garonne', 'OCC'),
 '83': ('Var', 'PAC'),
 '84': ('Vaucluse', 'PAC'),
 '85': ('Vendée', 'PDL'),
 '86': ('Vienne', 'NAQ'),
 '87': ('Haute-Vienne', 'NAQ'),
 '88': ('Vosges', 'GES'),
 '89': ('Yonne', 'BFC'),
 '90': ('Territoire de Belfort', 'BFC'),
 '91': ('Essonne', 'IDF'),
 '92': ('Hauts-de-Seine', 'IDF'),
 '93': ('Seine-Saint-Denis', 'IDF'),
 '94': ('Val-de-Marne', 'IDF'),
 '95': ("Val-d'Oise", 'IDF'),
 '971': ('Guadeloupe', '971'),
 '972': ('Martinique', '972'),
 '973': ('Guyane', '973'),
 '974': ('La Réunion', '974'),
 '976': ('Mayotte', '976'),
}
regcode = {r['source_id']: r['code'] for r in rows if r['level'] == '1'}
wrongdept = []
for r in rows:
    if r['level'] != '2':
        continue
    exp = deptfull.get(r['code'])
    if exp is None or r['name'] != exp[0] or regcode.get(r['parent_source_id']) != exp[1]:
        wrongdept.append((r['code'], r['name']))
check('deptfull-102', not wrongdept and len(deptfull) == 102, str(wrongdept[:4]))
check('l2-parents-l1', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('no-curly-apos', '\u2019' not in open(A, encoding='utf-8').read())
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('l1-no-parents', all(r['parent_source_id'] == '' for r in rows if r['level'] == '1'))
# --- postal counts: 20316 codes / 20340 links (CRLF) ---
check('codes-20316', len(codes) == 20316, str(len(codes)))
check('links-20340', len(links) == 20340, str(len(links)))
check('country-FR', all(c['country_code'] == 'FR' for c in codes))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5digit', not bad, str(bad[:3]))
check('codes-unique', len({c['code'] for c in codes}) == 20316)
check('codes-sorted', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
check('links-postcode-order', [l['postcode'] for l in links] == sorted(l['postcode'] for l in links))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
badrel = [l for l in links if l['relationship_type'] != 'served_by' or l['is_primary'] not in ('true', 'false')]
check('link-shape', not badrel, str(badrel[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 20316 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('every-code-linked', {c['code'] for c in codes} == {l['postcode'] for l in links})
# primary leg first within each code
by = {}
for l in links:
    by.setdefault(l['postcode'], []).append(l)
check('primary-first', all(v[0]['is_primary'] == 'true' for v in by.values() if len(v) > 1))
counts = Counter(l['postcode'] for l in links)
duals = sorted(pc for pc, c in counts.items() if c == 2)
check('duals-24', duals == ['01200', '01410', '01590', '05110', '05130', '05160', '05700', '06260',
      '13780', '21340', '33220', '37160', '42620', '43450', '48250', '52100',
      '69280', '69290', '69330', '69360', '69380', '69390', '69700', '69780'], str(duals))
check('no-triples', all(c <= 2 for c in counts.values()))
# dual primaries (seat-majority rule; 01590/69700/69780 flipped B13)
dualprim = {'01200': '01', '01410': '01', '01590': '01', '05110': '05', '05130': '05',
            '05160': '05', '05700': '05', '06260': '06', '13780': '13', '21340': '21',
            '33220': '33', '37160': '37', '42620': '42', '43450': '43', '48250': '48',
            '52100': '52', '69280': '69M', '69290': '69M', '69330': '69M', '69360': '69D',
            '69380': '69D', '69390': '69M', '69700': '69M', '69780': '69M'}
for pc, dept in dualprim.items():
    legs = [(byid[l['area_source_id']]['code'], l['is_primary']) for l in by[pc]]
    got = [d for d, p in legs if p == 'true']
    check(f'dualprim-{pc}', got == [dept], str(legs))
# dropped GN-artefact legs stay single
for pc, dept in [('02160', '02'), ('30130', '30'), ('49440', '49'), ('61420', '61'),
                 ('62147', '62'), ('62760', '62'), ('73670', '73')]:
    legs = [(byid[l['area_source_id']]['code'], l['is_primary']) for l in by[pc]]
    check(f'single-{pc}', legs == [(dept, 'true')], str(legs))
# anchors: fill, strips, retargets, cross-prefix, exclusions
def leg(pc):
    return sorted((byid[l['area_source_id']]['code'], l['is_primary']) for l in by.get(pc, []))
check('anchor-93380-fill', leg('93380') == [('93', 'true')], str(leg('93380')))
check('anchor-94390-xprefix', leg('94390') == [('91', 'true')], str(leg('94390')))
check('anchor-01014-strip', leg('01014') == [('01', 'true')], str(leg('01014')))
check('anchor-13661-air', leg('13661') == [('13', 'true')], str(leg('13661')))
check('anchor-75350-sp', leg('75350') == [('75', 'true')], str(leg('75350')))
check('anchor-78078-city', leg('78078') == [('78', 'true')], str(leg('78078')))
check('anchor-20900-corsica', leg('20900') == [('2A', 'true')], str(leg('20900')))
check('anchor-20223-corsica', leg('20223') == [('2B', 'true')], str(leg('20223')))
check('anchor-69310-metro', leg('69310') == [('69M', 'true')], str(leg('69310')))
check('anchor-69520-metro', leg('69520') == [('69M', 'true')], str(leg('69520')))
check('anchor-69600-metro', leg('69600') == [('69M', 'true')], str(leg('69600')))
check('anchor-69491-metro', leg('69491') == [('69M', 'true')], str(leg('69491')))
check('anchor-69921-metro', leg('69921') == [('69M', 'true')], str(leg('69921')))
check('anchor-69001-lyon', leg('69001') == [('69M', 'true')], str(leg('69001')))
check('anchor-69822-belleville', leg('69822') == [('69D', 'true')], str(leg('69822')))
check('clipperton-absent', '98799' not in {c['code'] for c in codes})
check('roissy-hold-absent', '95701' not in {c['code'] for c in codes})
check('orly-hold-absent', '94391' not in {c['code'] for c in codes})
check('no-spaced-left', not any(' ' in c['code'] for c in codes))
check('no-97-in-FR', not any(c['code'].startswith('97') for c in codes))
# per-department primary counts (97 linked depts; 5 DOM depts unlinked by design)
primdept = Counter(byid[l['area_source_id']]['code'] for l in links if l['is_primary'] == 'true')
expect = {'01': 164, '02': 139, '03': 99, '04': 76, '05': 58, '06': 315, '07': 112, '08': 71,
          '09': 51, '10': 106, '11': 126, '12': 95, '13': 646, '14': 253, '15': 58, '16': 118,
          '17': 218, '18': 99, '19': 93, '21': 192, '22': 169, '23': 50, '24': 120, '25': 214,
          '26': 146, '27': 198, '28': 124, '29': 232, '2A': 102, '2B': 83, '30': 197, '31': 313,
          '32': 72, '33': 358, '34': 322, '35': 315, '36': 78, '37': 159, '38': 341, '39': 119,
          '40': 107, '41': 128, '42': 233, '43': 70, '44': 402, '45': 259, '46': 54, '47': 102,
          '48': 37, '49': 212, '50': 145, '51': 176, '52': 79, '53': 106, '54': 266, '55': 60,
          '56': 218, '57': 326, '58': 98, '59': 672, '60': 284, '61': 100, '62': 354, '63': 202,
          '64': 189, '65': 120, '66': 175, '67': 366, '68': 228, '69D': 77, '69M': 471, '70': 58,
          '71': 150, '72': 149, '73': 199, '74': 220, '75': 846, '76': 294, '77': 471, '78': 439,
          '79': 167, '80': 200, '81': 118, '82': 79, '83': 242, '84': 238, '85': 185, '86': 166,
          '87': 136, '88': 167, '89': 120, '90': 48, '91': 482, '92': 686, '93': 370, '94': 599,
          '95': 370}
for dept, n in expect.items():
    if primdept.get(dept) != n:
        check(f'primcount-{dept}', False, f'got {primdept.get(dept)} want {n}')
        break
else:
    check('primcounts-97', len(primdept) == 97, str(len(primdept)))
check('unlinked-dom5', [d for d in ('971', '972', '973', '974', '976') if d in primdept] == [])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
