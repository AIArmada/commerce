import csv, os, sys
# Qatar gate. No postcode system. M4 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_qa.py
A = './packages/addressing/resources/geography/qatar-address-areas.csv'
C = './packages/addressing/resources/geography/qatar-postal-codes.csv'
L = './packages/addressing/resources/geography/qatar-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-98', len(rows) == 98, str(len(rows)))
check('municipalities-8', sum(1 for r in rows if r['type'] == 'municipality') == 8)
check('zones-90', sum(1 for r in rows if r['type'] == 'zone') == 90)
# ISO 3166-2:QA codes. Provider English names are a deliberate
# deviation from the ISO BGN/PCGN forms (Ad Dawhah + Madinat ash
# Shamal kept as aliases in areaNames).
iso = {'qa:municipality:doha': ('Doha', 'DA'),
 'qa:municipality:al-khor': ('Al Khor', 'KH'),
 'qa:municipality:al-shamal': ('Al Shamal', 'MS'),
 'qa:municipality:al-rayyan': ('Al Rayyan', 'RA'),
 'qa:municipality:al-sheehaniya': ('Al Sheehaniya', 'SH'),
 'qa:municipality:umm-salal': ('Umm Salal', 'US'),
 'qa:municipality:al-wakrah': ('Al Wakrah', 'WA'),
 'qa:municipality:al-daayen': ('Al Daayen', 'ZA')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full municipality -> zones table per the 2015-census
# reservation oracle (Zones of Qatar). Zones 8-11 sit inside
# Doha's reserved 1-50 block but are not in use; 59/87/88/89 are
# in no reservation range.
table = {'qa:municipality:doha': [1, 2, 3, 4, 5, 6, 7, 12, 13, 14,
    15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30,
    31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46,
    47, 48, 49, 50, 57, 58, 60, 61, 62, 63, 64, 65, 66, 67, 68],
 'qa:municipality:al-rayyan': [51, 52, 53, 54, 55, 56, 81, 83, 96, 97],
 'qa:municipality:al-daayen': [69, 70],
 'qa:municipality:umm-salal': [71],
 'qa:municipality:al-khor': [74, 75, 76],
 'qa:municipality:al-shamal': [77, 78, 79],
 'qa:municipality:al-sheehaniya': [72, 73, 80, 82, 84, 85, 86],
 'qa:municipality:al-wakrah': [90, 91, 92, 93, 94, 95, 98]}
ok = True
for muni, nums in table.items():
    have = sorted(int(r['code']) for r in rows if r['parent_source_id'] == muni)
    if have != sorted(nums):
        print('FAIL municipality', muni, sorted(set(have) ^ set(nums)))
        fails.append(f'municipality {muni}'); ok = False
if ok: print('PASS all 8 municipality mappings (90 zones)')
zones = [r for r in rows if r['type'] == 'zone']
check('zone-code-is-number', all(r['code'] == str(int(r['code'])) for r in zones))
check('zone-name-is-zone-N',
      all(r['name'] == f"Zone {int(r['code'])}" for r in zones))
allnums = {int(r['code']) for r in zones}
check('gaps-8-9-10-11-59-87-88-89',
      allnums == set(range(1, 99)) - {8, 9, 10, 11, 59, 87, 88, 89},
      str(sorted(set(range(1, 99)) - allnums)))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU qatEn (08/2024) shows P.O.-Box / zone-street
# delivery with no postcode; Sep-2025 UPU list carries Qatar on
# do-not-require; GeoNames has no QA postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
