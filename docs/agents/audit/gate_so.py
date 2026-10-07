import csv, os, sys
# Somalia gate. Verdict admin-ready since Stage 2 (2026-10-07):
# AA NNNNN format triply attested (UPU somEn + Google SO pattern +
# commerceguys), allocation unlisted, operational use unconfirmed.
# M5 revisit: 1 spelling fix proposed (see
# verdict.md); this gate pins the CORRECTED names, so it reports
# 2 FAILs (iso-SH, iso-SD) until the CSV renames land.
# Run from repo root: python3 docs/agents/audit/gate_so.py
A = './packages/addressing/resources/geography/somalia-address-areas.csv'
C = './packages/addressing/resources/geography/somalia-postal-codes.csv'
L = './packages/addressing/resources/geography/somalia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-107', len(rows) == 107, str(len(rows)))
check('regions-18', sum(1 for r in rows if r['type'] == 'region') == 18)
check('districts-89', sum(1 for r in rows if r['type'] == 'district') == 89)
# ISO 3166-2:SO codes. 'Woqooyi Galbeed' follows ISO against the
# oracle table's Somaliland rename 'Maroodi Jeex'. 'Hiran' +
# 'Nugal' follow the oracle table display against the ISO so-names
# Hiiraan/Nugaal (Hiran is a Statoids-recognized variant; both
# kept deliberately, see verdict.md). CORRECTED: 'Lower/Middle
# Shabelle' (ISO en-ref + oracle table + Statoids English variant
# + OCHA COD-AB v03; 'Shebelle' is the river's English name, the
# region articles use Shabelle; fails pre-fix by design).
iso = {'so:region:awdal': ('Awdal', 'AW'),
 'so:region:bakool': ('Bakool', 'BK'),
 'so:region:banaadir': ('Banaadir', 'BN'),
 'so:region:bari': ('Bari', 'BR'),
 'so:region:bay': ('Bay', 'BY'),
 'so:region:galguduud': ('Galguduud', 'GA'),
 'so:region:gedo': ('Gedo', 'GE'),
 'so:region:hiran': ('Hiran', 'HI'),
 'so:region:lower-juba': ('Lower Juba', 'JH'),
 'so:region:lower-shebelle': ('Lower Shabelle', 'SH'),
 'so:region:middle-juba': ('Middle Juba', 'JD'),
 'so:region:middle-shebelle': ('Middle Shabelle', 'SD'),
 'so:region:mudug': ('Mudug', 'MU'),
 'so:region:nugal': ('Nugal', 'NU'),
 'so:region:sanaag': ('Sanaag', 'SA'),
 'so:region:sool': ('Sool', 'SO'),
 'so:region:togdheer': ('Togdheer', 'TO'),
 'so:region:woqooyi-galbeed': ('Woqooyi Galbeed', 'WO')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full region -> districts table per the Regions-and-districts
# oracle (89/89 names + parents match; automated diff, zero
# diffs). Slug note: region source_ids stay as-is (stable
# identifiers); only the SH/SD display names flip to Shabelle.
table = {'so:region:awdal': ['Borama', 'Zeila', 'Lughaya',
    'Baki'],
 'so:region:bakool': ['El Barde', 'Hudur', 'Tiyeglow',
    'Wajid', 'Rabdhure'],
 'so:region:banaadir': ['Abdiaziz', 'Bondhere', 'Daynile',
    'Dharkenley', 'Hamar Jajab', 'Hamar Weyne', 'Hodan',
    'Howlwadaag', 'Huriwa', 'Karan', 'Shibis', 'Shangani',
    'Waberi', 'Wadajir', 'Warta Nabada', 'Yaqshid'],
 'so:region:bari': ['Bayla', 'Bosaso', 'Alula', 'Iskushuban',
    'Qandala', 'Qardho'],
 'so:region:bay': ['Baidoa', 'Burhakaba', 'Dinsoor',
    'Qasahdhere'],
 'so:region:galguduud': ['Abudwak', 'Adado', 'Dusmareb',
    'El Buur', 'El Dher'],
 'so:region:gedo': ['Bardhere', 'Beled Hawo', 'El Wak',
    'Dolow', 'Garbaharey', 'Luuq'],
 'so:region:hiran': ['Beledweyne', 'Buloburde', 'Jalalaqsi'],
 'so:region:lower-juba': ['Afmadow', 'Badhadhe', 'Jamame',
    'Kismayo'],
 'so:region:lower-shebelle': ['Afgooye', 'Barawa',
    'Kurtunwarey', 'Merca', 'Qoriyoley', 'Sablale',
    'Wanlaweyn'],
 'so:region:middle-juba': ["Bu'ale", 'Jilib', 'Sakow'],
 'so:region:middle-shebelle': ['Adale', 'Adan Yabal',
    'Balad', 'Jowhar'],
 'so:region:mudug': ['Galkayo', 'Galdogob', 'Harardhere',
    'Hobyo', 'Jariban'],
 'so:region:nugal': ['Garowe', 'Burtinle', 'Eyl'],
 'so:region:sanaag': ['Erigavo', 'Badhan', 'El Afweyn'],
 'so:region:sool': ['Las Anod', 'Hudun', 'Taleh', 'Aynaba'],
 'so:region:togdheer': ['Burao', 'Oodweyne', 'Buhoodle',
    'Sheikh'],
 'so:region:woqooyi-galbeed': ['Hargeisa', 'Berbera',
    'Gabiley']}
ok = True
for reg, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == reg)
    if have != sorted(names):
        print('FAIL region', reg, have); fails.append(f'region {reg}'); ok = False
if ok: print('PASS all 18 region mappings (89 districts)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict admin-ready: format attested (UPU somEn + Google SO +
# commerceguys agree on AA NNNNN) but no allocation list is
# published and operational use is unconfirmed; GeoNames has no SO
# postal dump (404). Admin already bundled, so only links queued —
# no CSVs ship until the district allocation exists.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
