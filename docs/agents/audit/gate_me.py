import csv, sys
# Montenegro gate. B6 verification: tree vs ISO 3166-2:ME, code set vs a
# fresh Pošta CG branch-network pull (165 branches, 150 unique codes via
# wp-json poste + jet-engine listing AJAX, Oct 2026), every link vs
# branch locality + Nominatim reverse-geocode of the branch pins (164/164).
# Oracles: postacg.me branch directory + working-hours notices, UPU mne
# profile (81000/85000/84000/85330/81205 anchors), ISO 3166-2:ME via WP,
# OSM addr:postcode aggregate (43k objects), Wayback branch archaeology.
# Fix: 85333 gains a Kotor secondary (Pošta Dobrota 2 branch + Jun-2023
# 'posta 85333 Dobrota 2' notice + Dobrota usage; Tivat primary kept).
# Holds: ME-06 'Old Royal Capital Cetinje' (official Prijestonica name,
# WP Municipalities lead corroborates); 80000 Express-hub service code;
# retired 81122 (2017 opening news, Brskutska 5 Zlatica), 81125 + 81128
# (branch pages to 2023/2025, Boška Buhe 24) + Ljubotinj branch excluded;
# stale commercial-data 85354/85357 + single-tag 81201/85358 held out;
# 84216 Pljevlja + 85317 Kotor kept over misplaced/border pins. Run from repo root:
# python3 docs/agents/audit/gate_me.py
A = './packages/addressing/resources/geography/montenegro-address-areas.csv'
C = './packages/addressing/resources/geography/montenegro-postal-codes.csv'
L = './packages/addressing/resources/geography/montenegro-postal-code-areas.csv'
P = 'me:municipality:'
PG, TU, KO, CT, ZE, BA, NI, DA, PZ, SA, BP, MO, PL, ZB, BE, RO, PE, AN, PV, GU, BU, KT, TI, HN, UL = (P + s for s in (
    'podgorica', 'tuzi', 'kolasin', 'old-royal-capital-cetinje', 'zeta', 'bar',
    'niksic', 'danilovgrad', 'pluzine', 'savnik', 'bijelo-polje', 'mojkovac',
    'pljevlja', 'zabljak', 'berane', 'rozaje', 'petnjica', 'andrijevica',
    'plav', 'gusinje', 'budva', 'kotor', 'tivat', 'herceg-novi', 'ulcinj'))
EXP = {
    '81000': (PG, []),
    '81101': (PG, []),
    '81102': (PG, []),
    '81103': (PG, []),
    '81104': (PG, []),
    '81105': (PG, []),
    '81106': (PG, []),
    '81107': (PG, []),
    '81108': (PG, []),
    '81109': (PG, []),
    '81110': (PG, []),
    '81111': (PG, []),
    '81112': (PG, []),
    '81113': (PG, []),
    '81114': (PG, []),
    '81115': (PG, []),
    '81116': (PG, []),
    '81117': (PG, []),
    '81118': (PG, []),
    '81119': (PG, []),
    '81120': (PG, []),
    '81121': (PG, []),
    '81123': (PG, []),
    '81124': (PG, []),
    '81126': (PG, []),
    '81127': (PG, []),
    '81204': (PG, []),
    '81205': (PG, []),
    '81206': (TU, []),
    '81210': (KO, []),
    '81211': (KO, []),
    '81214': (PG, []),
    '81215': (KO, []),
    '81216': (KO, []),
    '81217': (KO, []),
    '81250': (CT, []),
    '81253': (CT, []),
    '81255': (CT, []),
    '81257': (CT, []),
    '81258': (CT, []),
    '81259': (CT, []),
    '81260': (CT, []),
    '81304': (ZE, []),
    '81305': (BA, []),
    '81400': (NI, []),
    '81402': (NI, []),
    '81403': (NI, []),
    '81404': (NI, []),
    '81405': (NI, []),
    '81406': (NI, []),
    '81407': (NI, []),
    '81410': (DA, []),
    '81412': (DA, []),
    '81415': (DA, []),
    '81416': (DA, []),
    '81417': (NI, []),
    '81418': (NI, []),
    '81420': (NI, []),
    '81422': (NI, []),
    '81423': (NI, []),
    '81425': (NI, []),
    '81426': (NI, []),
    '81428': (NI, []),
    '81431': (NI, []),
    '81432': (PZ, []),
    '81435': (PZ, []),
    '81437': (PZ, []),
    '81450': (SA, []),
    '81453': (SA, []),
    '81455': (SA, []),
    '84000': (BP, []),
    '84001': (BP, []),
    '84002': (BP, []),
    '84205': (MO, []),
    '84206': (BP, []),
    '84210': (PL, []),
    '84212': (BP, []),
    '84213': (BP, []),
    '84214': (PL, []),
    '84215': (PL, []),
    '84216': (PL, []),
    '84217': (PL, []),
    '84218': (PL, []),
    '84219': (PL, []),
    '84220': (ZB, []),
    '84223': (PL, []),
    '84224': (ZB, []),
    '84300': (BE, []),
    '84303': (BP, []),
    '84305': (BP, []),
    '84306': (BE, []),
    '84310': (RO, []),
    '84311': (RO, []),
    '84312': (PE, []),
    '84314': (RO, []),
    '84315': (RO, []),
    '84320': (AN, []),
    '84322': (AN, []),
    '84323': (PV, []),
    '84325': (PV, []),
    '84326': (GU, []),
    '85000': (BA, []),
    '85101': (BA, []),
    '85300': (BU, []),
    '85306': (BA, []),
    '85310': (BU, []),
    '85311': (BU, []),
    '85312': (BU, []),
    '85313': (BU, []),
    '85314': (BU, []),
    '85315': (BU, []),
    '85316': (BU, []),
    '85317': (KT, []),
    '85318': (KT, []),
    '85319': (KT, []),
    '85320': (TI, []),
    '85321': (TI, []),
    '85323': (TI, []),
    '85324': (TI, []),
    '85330': (KT, []),
    '85331': (KT, []),
    '85332': (TI, []),
    '85333': (TI, [KT]),
    '85334': (KT, []),
    '85335': (KT, []),
    '85336': (KT, []),
    '85337': (KT, []),
    '85338': (KT, []),
    '85339': (KT, []),
    '85340': (HN, []),
    '85343': (HN, []),
    '85344': (HN, []),
    '85345': (HN, []),
    '85346': (HN, []),
    '85347': (HN, []),
    '85348': (HN, []),
    '85351': (BA, []),
    '85352': (BA, []),
    '85353': (BA, []),
    '85355': (BA, []),
    '85356': (BA, []),
    '85359': (BA, []),
    '85360': (UL, []),
    '85361': (UL, []),
    '85362': (UL, []),
    '85363': (UL, []),
    '85366': (UL, []),
    '85367': (UL, []),
    '85530': (HN, []),
}
NAMES = {'01': 'Andrijevica', '02': 'Bar', '03': 'Berane', '04': 'Bijelo Polje',
         '05': 'Budva', '06': 'Old Royal Capital Cetinje', '07': 'Danilovgrad',
         '08': 'Herceg-Novi', '09': 'Kolašin', '10': 'Kotor', '11': 'Mojkovac',
         '12': 'Nikšić', '13': 'Plav', '14': 'Pljevlja', '15': 'Plužine',
         '16': 'Podgorica', '17': 'Rožaje', '18': 'Šavnik', '19': 'Tivat',
         '20': 'Ulcinj', '21': 'Žabljak', '22': 'Gusinje', '23': 'Petnjica',
         '24': 'Tuzi', '25': 'Zeta'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
check('areas-25', len(areas) == 25, str(len(areas)))
check('areas-iso-codes', sorted(r['code'] for r in areas) == [f'{i:02d}' for i in range(1, 26)])
check('areas-names', all(r['name'] == NAMES[r['code']] for r in areas))
check('areas-flat', all(r['type'] == 'municipality' and r['level'] == '1' and not r['parent_source_id'] for r in areas))
check('me06-hold', byid[P + 'old-royal-capital-cetinje']['name'] == 'Old Royal Capital Cetinje',
      'official Prijestonica name; ISO short Cetinje is the outlier')
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-149', len(codes) == 149 and set(codes) == set(EXP), str(len(codes)))
check('codes-shape', all(len(c) == 5 and c.isdigit() and c[0] == '8' for c in codes))
check('service-excluded', '80000' not in codes, 'Express-hub code held out')
check('retired-excluded', not ({'81122', '81125', '81128'} & set(codes)), '81122/81125/81128 closed branches')
check('stale-excluded', not ({'85354', '85357', '81201', '85358'} & set(codes)),
      'stale commercial-data + single-tag codes held out')
links = list(csv.DictReader(open(L, newline='')))
check('links-150', len(links) == 150, str(len(links)))
check('primaries-149', sum(1 for r in links if r['is_primary'] == 'true') == 149)
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
bad = 0
for code, (prim, secs) in EXP.items():
    rows = got.get(code, [])
    want = [(prim, 'true')] + [(s, 'false') for s in secs]
    if rows != want:
        bad += 1
        if bad <= 8:
            print('FAIL link-' + code, 'got', rows, 'want', want)
if bad:
    fails.append('per-code-mapping')
else:
    print('PASS per-code-mapping 149/149')
check('extra-codes', set(got) == set(EXP), str(set(got) ^ set(EXP)))
check('85333-dual', got.get('85333') == [(P + 'tivat', 'true'), (P + 'kotor', 'false')],
      'Tivat primary (Lepetane) + Kotor secondary (Dobrota 2)')
check('pin-holds', got.get('84216') == [(P + 'pljevlja', 'true')] and got.get('85317') == [(P + 'kotor', 'true')],
      'misplaced Kovačevići pin + border Lastva pin overruled')
check('upu-anchors', got.get('81000') == [(P + 'podgorica', 'true')]
      and got.get('85000') == [(P + 'bar', 'true')]
      and got.get('84000') == [(P + 'bijelo-polje', 'true')]
      and got.get('85330') == [(P + 'kotor', 'true')]
      and got.get('81205') == [(P + 'podgorica', 'true')])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
