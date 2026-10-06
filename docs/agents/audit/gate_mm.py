import csv
import os

# Myanmar geography gate. Pins the CORRECTED post-2022 state from the B12
# revisit (141 rows: 15 L1 + 126 L2 = 121 GAD districts + 5 SAZ rows kept
# as district-typed L2 under their state, per MIMU v9.7 + bundled convention).
# Oracles: en.wiki Districts of Myanmar (126-row table incl. Wa section) +
# MOI announcement table 2 May 2022 (75 originals + 46 expansion incl. all
# 51 gross adds / 5 suppressions) + MIMU PCode v9.7 (house spellings).
# Run from repo root: python3 docs/agents/audit/gate_mm.py
A = './packages/addressing/resources/geography/myanmar-address-areas.csv'
C = './packages/addressing/resources/geography/myanmar-postal-codes.csv'
L = './packages/addressing/resources/geography/myanmar-postal-code-areas.csv'
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('eol-lf-only', b'\r' not in raw)
check('eol-trailing-lf', raw.endswith(b'\n') and not raw.endswith(b'\n\n'))
rows = list(csv.DictReader(raw.decode('utf-8').splitlines()))
byid = {r['source_id']: r for r in rows}
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
# --- headline counts: 15 L1 + 126 L2 (121 districts + 5 SAZ rows) ---
check('areas-141', len(rows) == 141, str(len(rows)))
check('l1-15', len(l1) == 15, str(len(l1)))
check('l2-126', len(l2) == 126, str(len(l2)))
check('l2-all-district', all(r['type'] == 'district' for r in l2))
check('parents-resolve',
      all(r['parent_source_id'] in byid for r in l2))
kids = {'mm:region:ayeyarwady': 8, 'mm:region:bago': 6, 'mm:state:chin': 6,
        'mm:state:kachin': 6, 'mm:state:kayah': 4, 'mm:state:kayin': 6,
        'mm:region:magway': 7, 'mm:region:mandalay': 11, 'mm:state:mon': 4,
        'mm:union_territory:naypyidaw': 4, 'mm:state:rakhine': 7,
        'mm:region:sagaing': 14, 'mm:state:shan': 25,
        'mm:region:tanintharyi': 4, 'mm:region:yangon': 14}
for parent, n in kids.items():
    have = [r for r in rows if r['parent_source_id'] == parent]
    check(f'kids-{parent.split(":")[-1]}-{n}', len(have) == n, str(len(have)))
# --- 5 suppressed pre-2022 rows are gone; retired codes not reused ---
for slug in ['mandalay', 'yangon-north', 'yangon-east', 'yangon-south',
             'yangon-west']:
    check(f'gone-{slug}', f'mm:district:{slug}' not in byid)
codes = [r['code'] for r in l2]
check('codes-unique-126', len(set(codes)) == len(codes) == 126,
      str(len(set(codes))))
check('codes-retired-absent',
      not ({'MMR010001', 'MMR013001', 'MMR013002', 'MMR013003',
            'MMR013004'} & set(codes)))
# --- 2 continuing NPT rows renamed in place (source_id + code kept) ---
check('rename-ottara', byid['mm:district:oke-ta-ra']['name'] == 'Ottara'
      and byid['mm:district:oke-ta-ra']['code'] == 'MMR018001')
check('rename-dekkhina', byid['mm:district:det-khi-na']['name'] == 'Dekkhina'
      and byid['mm:district:det-khi-na']['code'] == 'MMR018002')
# --- 51 added rows: (slug, name, code, parent) ---
adds = [
    ('chipwi', 'Chipwi', 'MMR001005', 'mm:state:kachin'),
    ('tanai', 'Tanai', 'MMR001006', 'mm:state:kachin'),
    ('demoso', 'Demoso', 'MMR002003', 'mm:state:kayah'),
    ('mese', 'Mese', 'MMR002004', 'mm:state:kayah'),
    ('kyain-seikgyi', 'Kyain Seikgyi', 'MMR003005', 'mm:state:kayin'),
    ('thandaunggyi', 'Thandaunggyi', 'MMR003006', 'mm:state:kayin'),
    ('paletwa', 'Paletwa', 'MMR004005', 'mm:state:chin'),
    ('tedim', 'Tedim', 'MMR004006', 'mm:state:chin'),
    ('homalin', 'Homalin', 'MMR005012', 'mm:region:sagaing'),
    ('ye-u', 'Ye-U', 'MMR005013', 'mm:region:sagaing'),
    ('bokpyin', 'Bokpyin', 'MMR006004', 'mm:region:tanintharyi'),
    ('nyaunglebin', 'Nyaunglebin', 'MMR007003', 'mm:region:bago'),
    ('nattalin', 'Nattalin', 'MMR008003', 'mm:region:bago'),
    ('aunglan', 'Aunglan', 'MMR009006', 'mm:region:magway'),
    ('chauk', 'Chauk', 'MMR009007', 'mm:region:magway'),
    ('amarapura', 'Amarapura', 'MMR010008', 'mm:region:mandalay'),
    ('aungmyethazan', 'Aungmyethazan', 'MMR010009', 'mm:region:mandalay'),
    ('maha-aungmye', 'Maha Aungmye', 'MMR010010', 'mm:region:mandalay'),
    ('tada-u', 'Tada-U', 'MMR010011', 'mm:region:mandalay'),
    ('thabeikkyin', 'Thabeikkyin', 'MMR010012', 'mm:region:mandalay'),
    ('kyaikto', 'Kyaikto', 'MMR011003', 'mm:state:mon'),
    ('ye', 'Ye', 'MMR011004', 'mm:state:mon'),
    ('ann', 'Ann', 'MMR012006', 'mm:state:rakhine'),
    ('taungup', 'Taungup', 'MMR012007', 'mm:state:rakhine'),
    ('taikkyi', 'Taikkyi', 'MMR013005', 'mm:region:yangon'),
    ('hlegu', 'Hlegu', 'MMR013006', 'mm:region:yangon'),
    ('hmawbi', 'Hmawbi', 'MMR013007', 'mm:region:yangon'),
    ('mingaladon', 'Mingaladon', 'MMR013008', 'mm:region:yangon'),
    ('insein', 'Insein', 'MMR013009', 'mm:region:yangon'),
    ('kyauktada', 'Kyauktada', 'MMR013010', 'mm:region:yangon'),
    ('ahlon', 'Ahlon', 'MMR013011', 'mm:region:yangon'),
    ('kamayut', 'Kamayut', 'MMR013012', 'mm:region:yangon'),
    ('mayangon', 'Mayangon', 'MMR013013', 'mm:region:yangon'),
    ('thingangyun', 'Thingangyun', 'MMR013014', 'mm:region:yangon'),
    ('botahtaung', 'Botahtaung', 'MMR013015', 'mm:region:yangon'),
    ('dagon-myothit', 'Dagon Myothit', 'MMR013016', 'mm:region:yangon'),
    ('twantay', 'Twantay', 'MMR013017', 'mm:region:yangon'),
    ('thanlyin', 'Thanlyin', 'MMR013018', 'mm:region:yangon'),
    ('kalaw', 'Kalaw', 'MMR014004', 'mm:state:shan'),
    ('kutkai', 'Kutkai', 'MMR015009', 'mm:state:shan'),
    ('monghsu', 'Monghsu', 'MMR014005', 'mm:state:shan'),
    ('mongla', 'Mongla', 'MMR016005', 'mm:state:shan'),
    ('mongton', 'Mongton', 'MMR016006', 'mm:state:shan'),
    ('mongyang', 'Mong Yang', 'MMR016007', 'mm:state:shan'),
    ('mongyawng', 'Mongyawng', 'MMR016008', 'mm:state:shan'),
    ('nansang', 'Nansang', 'MMR014006', 'mm:state:shan'),
    ('tangyan', 'Tangyan', 'MMR015010', 'mm:state:shan'),
    ('kyonpyaw', 'Kyonpyaw', 'MMR017007', 'mm:region:ayeyarwady'),
    ('myanaung', 'Myanaung', 'MMR017008', 'mm:region:ayeyarwady'),
    ('zeyathiri', 'Zeyathiri', 'MMR018003',
     'mm:union_territory:naypyidaw'),
    ('pyinmana', 'Pyinmana', 'MMR018004', 'mm:union_territory:naypyidaw'),
]
bad_adds = []
for slug, name, code, parent in adds:
    r = byid.get(f'mm:district:{slug}')
    if not r or (r['name'], r['code'], r['parent_source_id'], r['type'],
                 r['level']) != (name, code, parent, 'district', '2'):
        bad_adds.append(slug)
check('adds-51', not bad_adds and len(adds) == 51, str(bad_adds[:8]))
# --- retained-row pins (existing test compat + SAZ decision) ---
check('keep-kengtung', byid['mm:district:kengtung']['code'] == 'MMR016001')
check('keep-hopang-shan',
      byid['mm:district:hopang']['parent_source_id'] == 'mm:state:shan'
      and byid['mm:district:matman']['parent_source_id'] == 'mm:state:shan')
for slug, name, code, parent in [
        ('naga-self-administered-zone', 'Naga Self-Administered Zone',
         'MMR005S001', 'mm:region:sagaing'),
        ('danu-self-administered-zone', 'Danu Self-Administered Zone',
         'MMR014S001', 'mm:state:shan'),
        ('pa-o-self-administered-zone', 'Pa-O Self-Administered Zone',
         'MMR014S002', 'mm:state:shan'),
        ('pa-laung-self-administered-zone',
         'Pa Laung Self-Administered Zone', 'MMR015S001', 'mm:state:shan'),
        ('kokang-self-administered-zone',
         'Kokang Self-Administered Zone', 'MMR015S002', 'mm:state:shan')]:
    r = byid.get(f'mm:district:{slug}')
    check(f'saz-{slug[:12]}', bool(r) and (r['name'], r['code'],
          r['parent_source_id'], r['type']) == (name, code, parent,
                                               'district'))
# --- en.wiki spelling variants rejected per stability + MIMU (bundled kept)
for slug, name in [('puta-o', 'Puta-O'), ('bawlake', 'Bawlake'),
                   ('thayarwady', 'Thayarwady'), ('maubin', 'Maubin'),
                   ('yinmarbin', 'Yinmarbin'), ('langkho', 'Langkho'),
                   ('muse', 'Muse'), ('monghsat', 'Monghsat'),
                   ('kawthoung', 'Kawthoung'),
                   ('pyinoolwin', 'Pyinoolwin'),
                   ('kyaukpyu', 'Kyaukpyu'), ('tamu', 'Tamu'),
                   ('hkamti', 'Hkamti')]:
    r = byid.get(f'mm:district:{slug}')
    check(f'stable-{slug}', bool(r) and r['name'] == name)
# --- postal stance parked: no MM postal CSVs (see overlay.txt) ---
check('postal-parked', not os.path.exists(C) and not os.path.exists(L))
print('GATE', 'PASS' if not fails else f'FAIL {fails}')
raise SystemExit(1 if fails else 0)
