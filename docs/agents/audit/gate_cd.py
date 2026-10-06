import csv, os, sys
from collections import Counter
# DR Congo gate. Verify-only B15 pass: 26/26 provinces match ISO
# 3166-2:CD on code + name (Kinshasa deliberately typed province, the
# post-2015 city-province, though ISO kinds it a city); 145/145
# territories match COD-AB (re-downloaded from HDX, valid 2019-09-11)
# on name + parent with per-province counts exact. The 19 extra
# COD-AB admin2 rows are villes (provincial capitals/major cities),
# deliberately excluded — the bundle ships territories only, and
# Kinshasa is terminal. Two COD-AB spellings overruled with EN+FR
# wiki support: Kanyama (not Kaniama, Haut-Lomami) and Gandajika
# (not Ngandajika, Lomami) — both EN redirects/targets and FR titles
# agree with the bundle. No postal files (no CD postcode system).
# Asserts POST-fix (= pre-fix) state; run from repo root:
# python3 docs/agents/audit/gate_cd.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/democratic-republic-of-congo-address-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
check('no-postal-codes-file',
      not os.path.exists(f'{G}/democratic-republic-of-congo-postal-codes.csv'))
check('no-postal-legs-file',
      not os.path.exists(f'{G}/democratic-republic-of-congo-postal-code-areas.csv'))
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-171', len(rows) == 171, str(len(rows)))
check('l1-26', sum(1 for r in rows if r['level'] == '1') == 26)
check('l2-145', sum(1 for r in rows if r['level'] == '2') == 145)
iso = {'cd:province:kongo-central': ('Kongo Central', 'BC'),
       'cd:province:bas-uele': ('Bas-Uélé', 'BU'),
       'cd:province:equateur': ('Équateur', 'EQ'),
       'cd:province:haut-katanga': ('Haut-Katanga', 'HK'),
       'cd:province:haut-lomami': ('Haut-Lomami', 'HL'),
       'cd:province:haut-uele': ('Haut-Uélé', 'HU'),
       'cd:province:ituri': ('Ituri', 'IT'),
       'cd:province:kasai-central': ('Kasaï Central', 'KC'),
       'cd:province:kasai-oriental': ('Kasaï Oriental', 'KE'),
       'cd:province:kwango': ('Kwango', 'KG'),
       'cd:province:kwilu': ('Kwilu', 'KL'),
       'cd:province:kinshasa': ('Kinshasa', 'KN'),
       'cd:province:kasai': ('Kasaï', 'KS'),
       'cd:province:lomami': ('Lomami', 'LO'),
       'cd:province:lualaba': ('Lualaba', 'LU'),
       'cd:province:maniema': ('Maniema', 'MA'),
       'cd:province:mai-ndombe': ('Mai-Ndombe', 'MN'),
       'cd:province:mongala': ('Mongala', 'MO'),
       'cd:province:nord-kivu': ('Nord-Kivu', 'NK'),
       'cd:province:nord-ubangi': ('Nord-Ubangi', 'NU'),
       'cd:province:sankuru': ('Sankuru', 'SA'),
       'cd:province:sud-kivu': ('Sud-Kivu', 'SK'),
       'cd:province:sud-ubangi': ('Sud-Ubangi', 'SU'),
       'cd:province:tanganyika': ('Tanganyika', 'TA'),
       'cd:province:tshopo': ('Tshopo', 'TO'),
       'cd:province:tshuapa': ('Tshuapa', 'TU')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'l1-{cd}', bool(r) and r['name'] == nm and r['code'] == cd
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
expect = {'cd:province:bas-uele': 6, 'cd:province:equateur': 7,
          'cd:province:haut-katanga': 6, 'cd:province:haut-lomami': 5,
          'cd:province:haut-uele': 6, 'cd:province:ituri': 5,
          'cd:province:kasai': 5, 'cd:province:kasai-central': 5,
          'cd:province:kasai-oriental': 5, 'cd:province:kongo-central': 10,
          'cd:province:kwango': 5, 'cd:province:kwilu': 5,
          'cd:province:lomami': 5, 'cd:province:lualaba': 5,
          'cd:province:mai-ndombe': 8, 'cd:province:maniema': 7,
          'cd:province:mongala': 3, 'cd:province:nord-kivu': 6,
          'cd:province:nord-ubangi': 4, 'cd:province:sankuru': 6,
          'cd:province:sud-kivu': 8, 'cd:province:sud-ubangi': 4,
          'cd:province:tanganyika': 6, 'cd:province:tshopo': 7,
          'cd:province:tshuapa': 6, 'cd:province:kinshasa': 0}
for par, n in expect.items():
    check(f'l2-{par.split(":")[-1]}-{n}', got.get(par, 0) == n,
          str(got.get(par, 0)))
check('l2-total-145', sum(got.values()) == 145, str(sum(got.values())))
# Deliberate spelling keeps (EN+FR over COD-AB).
for sid, nm, par in [
        ('cd:territory:kanyama', 'Kanyama', 'cd:province:haut-lomami'),
        ('cd:territory:gandajika', 'Gandajika', 'cd:province:lomami')]:
    r = byid.get(sid)
    check(f'spelling-{nm}', bool(r) and r['name'] == nm
          and r['parent_source_id'] == par, str(r))
orphans = [r['source_id'] for r in rows
           if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orphans, str(orphans[:5]))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
