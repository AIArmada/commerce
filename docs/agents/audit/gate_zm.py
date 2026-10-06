import csv, os, sys
# Zambia (ZM) gate. Pins the B13 pass: 10 provinces + 116 districts = 126 rows.
# Fix vs pre-state: 'Mansa District, Zambia' page-title scrape → 'Mansa'
# (id+name; WP Districts of Zambia + Statoids). All other names/parents exact
# vs the Districts-of-Zambia oracle (116-district April-2018 set, counts
# 11/10/15/12/6/8/12/11/15/16). L1 codes 01-10 ISO 3166-2:ZM-exact. Postal:
# nothing to import — UPU zmbEn profile (01/2013) defines the 5-digit slot
# but states codes "have not yet been assigned and the coding method is yet
# to be defined" (examples are placeholders); UPU Sep-2025 list keeps Zambia
# require-side on paper; GeoNames ZM.zip 404. Run from repo root:
# python3 docs/agents/audit/gate_zm.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/zambia-address-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline (raw bytes) ---
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-126', len(rows) == 126, str(len(rows)))
check('areas-unique-ids', len(byid) == len(rows))
check('areas-country-ZM', all(r['country_code'] == 'ZM' for r in rows))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('provinces-10', len(l1) == 10 and all(r['type'] == 'province' and not r['parent_source_id'] for r in l1))
check('districts-116', len(l2) == 116 and all(r['type'] == 'district' for r in l2), str(len(l2)))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
# ISO 3166-2:ZM 01-10 (codes exact; 'Northwestern' display vs ISO
# 'North-Western' is deliberate, matching the bundled province label).
iso = {'zm:province:western': ('Western', '01'), 'zm:province:central': ('Central', '02'),
 'zm:province:eastern': ('Eastern', '03'), 'zm:province:luapula': ('Luapula', '04'),
 'zm:province:northern': ('Northern', '05'), 'zm:province:northwestern': ('Northwestern', '06'),
 'zm:province:southern': ('Southern', '07'), 'zm:province:copperbelt': ('Copperbelt', '08'),
 'zm:province:lusaka': ('Lusaka', '09'), 'zm:province:muchinga': ('Muchinga', '10')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code, str(r))
# Full membership: Districts of Zambia (116-district April-2018 set).
table = {'zm:province:central': ['Chibombo', 'Chisamba', 'Chitambo', 'Kabwe', 'Kapiri Mposhi', 'Luano', 'Mkushi', 'Mumbwa', 'Ngabwe', 'Serenje', 'Shibuyunji'],
 'zm:province:copperbelt': ['Chililabombwe', 'Chingola', 'Kalulushi', 'Kitwe', 'Luanshya', 'Lufwanyama', 'Masaiti', 'Mpongwe', 'Mufulira', 'Ndola'],
 'zm:province:eastern': ['Chadiza', 'Chama', 'Chasefu', 'Chipangali', 'Chipata', 'Kasenengwa', 'Katete', 'Lumezi', 'Lundazi', 'Lusangazi', 'Mambwe', 'Nyimba', 'Petauke', 'Sinda', 'Vubwi'],
 'zm:province:luapula': ['Chembe', 'Chiengi', 'Chifunabuli', 'Chipili', 'Kawambwa', 'Lunga', 'Mansa', 'Milenge', 'Mwansabombwe', 'Mwense', 'Nchelenge', 'Samfya'],
 'zm:province:lusaka': ['Chilanga', 'Chongwe', 'Kafue', 'Luangwa', 'Lusaka', 'Rufunsa'],
 'zm:province:muchinga': ['Chinsali', 'Isoka', 'Kanchibiya', 'Lavushimanda', 'Mafinga', 'Mpika', 'Nakonde', "Shiwang'andu"],
 'zm:province:northern': ['Chilubi', 'Kaputa', 'Kasama', 'Lunte', 'Lupososhi', 'Luwingu', 'Mbala', 'Mporokoso', 'Mpulungu', 'Mungwi', 'Nsama', 'Senga'],
 'zm:province:northwestern': ['Chavuma', 'Ikelenge', 'Kabompo', 'Kalumbila', 'Kasempa', 'Manyinga', 'Mufumbwe', 'Mushindamo', 'Mwinilunga', 'Solwezi', 'Zambezi'],
 'zm:province:southern': ['Chikankata', 'Chirundu', 'Choma', 'Gwembe', 'Itezhi-Tezhi', 'Kalomo', 'Kazungula', 'Livingstone', 'Mazabuka', 'Monze', 'Namwala', 'Pemba', 'Siavonga', 'Sinazongwe', 'Zimba'],
 'zm:province:western': ['Kalabo', 'Kaoma', 'Limulunga', 'Luampa', 'Lukulu', 'Mitete', 'Mongu', 'Mulobezi', 'Mwandi', 'Nalolo', 'Nkeyema', 'Senanga', 'Sesheke', "Shang'ombo", 'Sikongo', 'Sioma']}
kids = {}
for r in rows:
    if r['level'] == '2': kids.setdefault(r['parent_source_id'], []).append(r['name'])
for sid, names in table.items():
    check(f'mem-{sid.split(":")[-1]}', sorted(kids.get(sid, [])) == sorted(names), str(sorted(set(kids.get(sid, [])) ^ set(names))))
# B13 fix pins.
check('pin-mansa', byid.get('zm:district:mansa', {}).get('name') == 'Mansa'
    and byid.get('zm:district:mansa', {}).get('parent_source_id') == 'zm:province:luapula')
check('gone-mansa-district-zambia', 'zm:district:mansa-district-zambia' not in byid)
# No-postal pins: no ZM postal CSVs exist.
check('no-postal-csvs', not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/zambia-postal-codes.csv')
    and not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/zambia-postal-code-areas.csv'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
