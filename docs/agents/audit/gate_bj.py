import csv, os, sys
# Benin gate. No postcode system. M4 revisit: 5 official-spelling
# fixes proposed (see verdict.md); this gate pins the CORRECTED
# names, so it reports 4 FAILs until the CSV renames land.
# Run from repo root: python3 docs/agents/audit/gate_bj.py
A = './packages/addressing/resources/geography/benin-address-areas.csv'
C = './packages/addressing/resources/geography/benin-postal-codes.csv'
L = './packages/addressing/resources/geography/benin-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-89', len(rows) == 89, str(len(rows)))
check('departments-12', sum(1 for r in rows if r['type'] == 'department') == 12)
check('communes-77', sum(1 for r in rows if r['type'] == 'commune') == 77)
# ISO 3166-2:BJ codes. Deliberate deviation: provider uses the
# common/government-majority spellings Atakora + Kouffo where ISO
# prints Atacora + Couffo (Benin government sources themselves use
# both pairs; the Departments oracle agrees with the CSV).
iso = {'bj:department:alibori': ('Alibori', 'AL'),
 'bj:department:atakora': ('Atakora', 'AK'),
 'bj:department:atlantique': ('Atlantique', 'AQ'),
 'bj:department:borgou': ('Borgou', 'BO'),
 'bj:department:collines': ('Collines', 'CO'),
 'bj:department:donga': ('Donga', 'DO'),
 'bj:department:kouffo': ('Kouffo', 'KO'),
 'bj:department:littoral': ('Littoral', 'LI'),
 'bj:department:mono': ('Mono', 'MO'),
 'bj:department:oueme': ('Ouémé', 'OU'),
 'bj:department:plateau': ('Plateau', 'PL'),
 'bj:department:zou': ('Zou', 'ZO')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full department -> communes table per the Communes-of-Benin
# oracle, with 5 official-truth corrections applied: Ségbana
# (INSAE RGPH4 + CONAFIL), Péhunco (CONAFIL x4), Porto-Novo (UPU
# benEn), Covè + Zagnanado (2015-596 Zou decree + INSAE +
# CONAFIL). Kept deliberately despite challengers: Comé (the
# Mairie's own letterhead beats the grave-accent votes),
# Dassa-Zoumè (grave is the commune; acute is the town),
# Dogbo-Tota, Bembèrèkè, Kalalé, Athiémé (single-aggregator
# flips, outvoted; see verdict.md).
table = {'bj:department:alibori': ['Banikoara', 'Gogounou', 'Kandi',
    'Karimama', 'Malanville', 'Ségbana'],
 'bj:department:atakora': ['Boukoumbé', 'Cobly', 'Kérou', 'Kouandé',
    'Matéri', 'Natitingou', 'Péhunco', 'Tanguiéta', 'Toucountouna'],
 'bj:department:atlantique': ['Abomey-Calavi', 'Allada', 'Kpomassè',
    'Ouidah', 'Sô-Ava', 'Toffo', 'Tori-Bossito', 'Zè'],
 'bj:department:borgou': ['Bembèrèkè', 'Kalalé', "N'Dali", 'Nikki',
    'Parakou', 'Pèrèrè', 'Sinendé', 'Tchaourou'],
 'bj:department:collines': ['Bantè', 'Dassa-Zoumè', 'Glazoué',
    'Ouèssè', 'Savalou', 'Savé'],
 'bj:department:donga': ['Bassila', 'Copargo', 'Djougou', 'Ouaké'],
 'bj:department:kouffo': ['Aplahoué', 'Djakotomey', 'Klouékanmè',
    'Lalo', 'Toviklin', 'Dogbo-Tota'],
 'bj:department:littoral': ['Cotonou'],
 'bj:department:mono': ['Athiémé', 'Bopa', 'Comé', 'Grand-Popo',
    'Houéyogbé', 'Lokossa'],
 'bj:department:oueme': ['Adjarra', 'Adjohoun', 'Aguégués',
    'Akpro-Missérété', 'Avrankou', 'Bonou', 'Dangbo', 'Porto-Novo',
    'Sèmè-Kpodji'],
 'bj:department:plateau': ['Ifangni', 'Adja-Ouèrè', 'Kétou', 'Pobè',
    'Sakété'],
 'bj:department:zou': ['Abomey', 'Agbangnizoun', 'Bohicon', 'Covè',
    'Djidja', 'Ouinhi', 'Za-Kpota', 'Zagnanado', 'Zogbodomey']}
ok = True
for dep, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == dep)
    if have != sorted(names):
        print('FAIL department', dep, sorted(set(have) ^ set(names)))
        fails.append(f'department {dep}'); ok = False
if ok: print('PASS all 12 department mappings (77 communes)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU benEn (11/2025) shows P.O.-box-only
# addressing; the 2-digit Cotonou/Porto-Novo/Abomey/Parakou
# delivery-office prefixes are office codes (same shape as CI/GA),
# not postcodes. Sep-2025 UPU list carries Benin on
# do-not-require; GeoNames has no BJ postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
