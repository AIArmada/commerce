import csv, os, sys
# Kuwait gate. Live 5-digit postcode system, but block/sector-level
# with no usable allocation source (gap, not codeless): MOC
# Governorate/Area/Block tables render empty, PACI unreachable,
# GeoNames has no KW dump (404), Mapanet rows unattributable at L2.
# M5 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_kw.py
A = './packages/addressing/resources/geography/kuwait-address-areas.csv'
C = './packages/addressing/resources/geography/kuwait-postal-codes.csv'
L = './packages/addressing/resources/geography/kuwait-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-141', len(rows) == 141, str(len(rows)))
check('governorates-6', sum(1 for r in rows if r['type'] == 'governorate') == 6)
check('areas-135', sum(1 for r in rows if r['type'] == 'area') == 135)
# ISO 3166-2:KW codes.
iso = {'kw:governorate:al-ahmadi': ('Al Ahmadi', 'AH'),
 'kw:governorate:al-asimah': ('Al Asimah', 'KU'),
 'kw:governorate:al-farwaniyah': ('Al Farwaniyah', 'FA'),
 'kw:governorate:al-jahra': ('Al Jahra', 'JA'),
 'kw:governorate:hawalli': ('Hawalli', 'HA'),
 'kw:governorate:mubarak-al-kabeer': ('Mubarak Al-Kabeer', 'MU')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full governorate -> areas table per Areas-of-Kuwait oracle (140
# rows = 135 bundled + 5 uninhabited islands excluded here: Miskan,
# Ouha, Umm an Namil, Bubiyan, Warbah; + Sabah Al-Salem University
# campus and Sulaibiya Industrial rows not shipped as areas; -
# Kuwait City capital area and Al-Shadadiya which the oracle table
# omits). Transliteration keeps: Abdulla/Bneid/Hawalli/Mansouriya/
# Mirqab/Qortuba/Riggae/Ftaira/Fnaitees/Messila/Adan/Qurain.
table = {
 'kw:governorate:al-asimah': ['Abdulla Al-Salem', 'Adailiya',
     'Al-Sour Gardens', 'Bnaid Al-Qar', 'Daiya', 'Dasma', 'Doha',
     'Doha Port', 'Faiha', 'Failaka Island', 'Granada', 'Jibla',
     'Kaifan', 'Khaldiya', 'Kuwait City', 'Mansouriya', 'Mirqab',
     'Nahdha', 'North West Sulaibikhat', 'Nuzha', 'Qadsiya', 'Qairawan',
     'Qortuba', 'Rawda', 'Shamiya', 'Sharq', 'Shuwaikh',
     'Shuwaikh Industrial Area', 'Shuwaikh Port', 'Sulaibikhat', 'Surra', 'Yarmouk'],
 'kw:governorate:hawalli': ["Al-Bida'a", 'Al-Siddiq',
     'Anjafa', 'Bayan', 'Hawalli', 'Hitteen', 'Jabriya',
     'Ministries Area', 'Mishrif', 'Mubarak Al-Abdullah', 'Rumaithiya', 'Salam',
     'Salmiya', 'Salwa', 'Shaab', 'Shuhada', 'Zahra'],
 'kw:governorate:mubarak-al-kabeer': ['Abu Al Hasaniya', 'Abu Ftaira',
     'Al Qurain', 'Al-Adan', 'Al-Fnaitees', 'Al-Masayel', 'Al-Qusour',
     'Messila', 'Mubarak Al-Kabeer', 'Sabah Al-Salem', 'Subhan Industrial', 'West Abu Ftaira Herafiya',
     'Wista'],
 'kw:governorate:al-ahmadi': ['Abu Halifa', 'Ahmadi',
     'Al Shadadiya Industrial', "Al-Julaia'a", 'Al-Nuwaiseeb', 'Ali Sabah Al-Salem', 'Bar Al-Ahmadi',
     'Bnaider', 'Dhaher', 'Egaila', 'Fahad Al-Ahmad', 'Fahaheel',
     'Fintas', 'Hadiya', 'Jaber Al-Ali', 'Khairan', 'Magwa',
     'Mahboula', 'Mangaf', 'Mina Abdulla', 'Riqqa', 'Sabah Al Ahmad',
     'Sabah Al Ahmad Sea City', 'Sabahiya', 'Shuaiba Industrial', 'South Sabahiya', 'Wafra',
     'Wafra Residential', 'Zoor'],
 'kw:governorate:al-farwaniyah': ['Abdullah Al-Mubarak', 'Airport',
     'Al-Dajeej', 'Al-Rai', 'Al-Riggai', 'Al-Shadadiya', 'Andalus',
     'Ardiya', 'Ardiya Herafiya', 'Farwaniya', 'Ferdous', 'Ishbiliya',
     'Jleeb Al-Shuyoukh', 'Khaitan', 'Omariya', 'Rabiya', 'Rehab',
     'Sabah Al-Nasser', 'South Abdullah Al-Mubarak', 'West Abdullah Al-Mubarak'],
 'kw:governorate:al-jahra': ['Abdali', 'Al-Mutlaa',
     'Al-Nahda', 'Al-Sheqaya', 'Amghara Industrial', 'Bahra', 'Bar Al-Jahra',
     'Jaber Al-Ahmad', 'Jahra', 'Jahra Industrial Herafiya', 'Kabd', 'Kazma',
     'Naeem', 'Nasseem', 'Oyoun', 'Qasr', 'Saad Al Abdullah',
     'Salmi', 'Subiya', 'Sulaibiya', 'Sulaibiya Agricultural Area', 'Sulaibiya Residential',
     'Taima', 'Waha'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
    check(f"{par.split(':')[-1]}-{len(names)}", len(have) == len(names), str(len(have)))
if ok: print('PASS all 6 governorate mappings (135 areas)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Gap, not codeless: UPU require-list + kwtEn profile (07/2002)
# document a live 5-digit system (PO Box or block zone/sector);
# codes nest under areas via blocks (MOC table headers, Mapanet
# Al Dasma block-range rows) but no 2-signal allocation source
# exists, so no overlay ships.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
