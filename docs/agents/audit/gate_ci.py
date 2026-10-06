import csv, os, sys
# Ivory Coast gate. No postcode system (2-digit post office codes are
# routing, not postcodes). M2 revisit: Bélier row moved into the Lacs
# group (ordering only; parent unchanged). Run from repo root:
# python3 docs/agents/audit/gate_ci.py
A = './packages/addressing/resources/geography/ivory-coast-address-areas.csv'
C = './packages/addressing/resources/geography/ivory-coast-postal-codes.csv'
L = './packages/addressing/resources/geography/ivory-coast-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-45', len(rows) == 45, str(len(rows)))
check('districts-12', sum(1 for r in rows if r['type'] == 'district') == 12)
check('autonomous-2', sum(1 for r in rows if r['type'] == 'autonomous_district') == 2)
check('regions-31', sum(1 for r in rows if r['type'] == 'region') == 31)
# L1 mnemonic codes pinned (provider-local, not ISO numeric).
codes = {'ci:autonomous_district:abidjan': 'AB', 'ci:district:bas-sassandra': 'BS',
 'ci:district:comoe': 'CM', 'ci:district:denguele': 'DN',
 'ci:district:goh-djiboua': 'GD', 'ci:district:lacs': 'LC',
 'ci:district:lagunes': 'LG', 'ci:district:montagnes': 'MG',
 'ci:district:sassandra-marahoue': 'SM', 'ci:district:savanes': 'SV',
 'ci:district:vallee-du-bandama': 'VB', 'ci:district:woroba': 'WR',
 'ci:autonomous_district:yamoussoukro': 'YM', 'ci:district:zanzan': 'ZZ'}
for sid, code in codes.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['code'] == code and r['level'] == '1', str(r))
# Full district -> regions table per Districts-of-Ivory-Coast oracle
# (14/14 groups incl. Bélier under Lacs; childless Abidjan/Yamoussoukro).
table = {'ci:district:bas-sassandra': ['Gbôklé', 'Nawa', 'San-Pédro'],
 'ci:district:comoe': ['Indénié-Djuablin', 'Sud-Comoé'],
 'ci:district:denguele': ['Folon', 'Kabadougou'],
 'ci:district:goh-djiboua': ['Gôh', 'Lôh-Djiboua'],
 'ci:district:lacs': ['Bélier', 'Iffou', 'Moronou', "N'Zi"],
 'ci:district:lagunes': ['Agnéby-Tiassa', 'Grands-Ponts', 'La Mé'],
 'ci:district:montagnes': ['Cavally', 'Guémon', 'Tonkpi'],
 'ci:district:sassandra-marahoue': ['Haut-Sassandra', 'Marahoué'],
 'ci:district:savanes': ['Bagoué', 'Poro', 'Tchologo'],
 'ci:district:vallee-du-bandama': ['Gbêkê', 'Hambol'],
 'ci:district:woroba': ['Bafing', 'Béré', 'Worodougou'],
 'ci:district:zanzan': ['Bounkani', 'Gontougo']}
ok = True
for dist, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == dist)
    if have != sorted(names):
        print('FAIL district', dist, have); fails.append(f'district {dist}'); ok = False
if ok: print('PASS all 12 district mappings (31 regions)')
for sid in ['ci:autonomous_district:abidjan', 'ci:autonomous_district:yamoussoukro']:
    kids = [r for r in rows if r['parent_source_id'] == sid]
    check(f'terminal-{sid.split(":")[-1]}', not kids, str(kids[:2]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU civEn (09/2004) shows the 2-digit code is a post
# office code on P.O.-Box lines; Sep-2025 UPU list puts Côte d'Ivoire
# on do-not-require; GeoNames has no CI postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
