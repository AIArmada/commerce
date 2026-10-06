import csv, os, sys
# Lesotho gate. B11 revisit: 1-cell fix — Mokhotlong #78 is Senqu
# (IEC LIST-OF-CONSTITUENCIES 2025 PDF + IEC 2022/2017/2015 results
# pages + 2012 election table + 2006 census); WP Constituencies page
# duplicates Malingoaneng in error and bundled copied it.
# Tree otherwise verify-only: 10 districts (ISO letters A-K) + 80
# constituencies per 2022 delimitation (Legal Notice 37/2022).
# Postal stays admin-ready (no CSVs): 3-digit system is live
# (Maseru 100) but no public allocation table exists (GeoNames
# LS.zip 404, no UPU PDF snapshot, Mapanet shell-only).
# Run from repo root:
# python3 docs/agents/audit/gate_ls.py
A = './packages/addressing/resources/geography/lesotho-address-areas.csv'
C = './packages/addressing/resources/geography/lesotho-postal-codes.csv'
L = './packages/addressing/resources/geography/lesotho-postal-code-areas.csv'
L1 = {'ls:district:berea': ('Berea', 'D'),
      'ls:district:butha-buthe': ('Butha-Buthe', 'B'),
      'ls:district:leribe': ('Leribe', 'C'),
      'ls:district:mafeteng': ('Mafeteng', 'E'),
      'ls:district:maseru': ('Maseru', 'A'),
      'ls:district:mohales-hoek': ("Mohale's Hoek", 'F'),
      'ls:district:mokhotlong': ('Mokhotlong', 'J'),
      'ls:district:qachas-nek': ("Qacha's Nek", 'H'),
      'ls:district:quthing': ('Quthing', 'G'),
      'ls:district:thaba-tseka': ('Thaba-Tseka', 'K')}
CON = {
 'ls:district:butha-buthe': ['Mechachane', 'Hololo', 'Motete', 'Qalo',
                             'Butha-Buthe'],
 'ls:district:leribe': ['Maliba-Matso', 'Mphosong', 'Thaba-Phatsoa',
                        'Mahobong', "Pela Ts'oeu", 'Matlakeng', 'Leribe',
                        'Hlotse', 'Tsikoane', 'Maputsoe', 'Moselinyane',
                        'Peka', 'Kolonyama'],
 'ls:district:berea': ['Mosalemane', "'Makhoroana", 'Bela-Bela',
                       'Malimong', 'Khafung', 'Teya-Teyaneng',
                       'Tsoana-Makhulo', 'Thuathe', 'Mokhethoaneng',
                       'Khubetsoana', 'Mabote'],
 'ls:district:maseru': ['Motimposo', "Majoe-Lits'oene", 'Stadium Area',
                        'Maseru', 'Thetsane', 'Tsolo', 'Likotsi',
                        'Qoaling', 'Lithoteng', 'Abia', 'Lithabaneng',
                        'Matala', 'Thaba-Bosiu', 'Machache',
                        'Thaba-Putsoa', 'Maama', 'Koro-Koro', 'Qeme',
                        'Rothe', 'Matsieng', 'Makhaleng',
                        'Maletsunyane'],
 'ls:district:mafeteng': ['Thaba-Phechela', 'Phoqoane', 'Matelile',
                          'Maliepetsane', 'Thabana-Morena', 'Qalabane',
                          'Mafeteng'],
 'ls:district:mohales-hoek': ['Taung', 'Mpharane', "Mohale's Hoek",
                              'Mekaling', 'Phamong', 'Hloahloeng'],
 'ls:district:quthing': ['Moyeni', 'Sempe', 'Mt. Moorosi', 'Qhoali'],
 'ls:district:qachas-nek': ["Qacha's Nek", 'Lebakeng', 'Tsoelike'],
 'ls:district:thaba-tseka': ['Mantsonyane', 'Thaba-Moea', 'Thaba-Tseka',
                             'Semena', 'Mashai'],
 'ls:district:mokhotlong': ['Malingoaneng', 'Senqu', 'Mokhotlong',
                            'Bobatsi']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-90', len(areas) == 90, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
got_l1 = {r['source_id']: (r['name'], r['code']) for r in l1}
check('l1-10', got_l1 == L1,
      str({k for k in L1 if got_l1.get(k) != L1[k]}))
co = [r for r in areas if r['type'] == 'constituency']
check('con-80', len(co) == 80, str(len(co)))
got = {}
for r in co:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in CON if sorted(got.get(k, [])) != sorted(CON[k])}
check('con-xmap', not bad, str(sorted(bad)))
mala = [r for r in co if r['name'] == 'Malingoaneng']
check('malingoaneng-once', len(mala) == 1, str(len(mala)))
senq = [r for r in co if r['name'] == 'Senqu']
check('senqu-present', len(senq) == 1
      and senq[0]['parent_source_id'] == 'ls:district:mokhotlong'
      and senq[0]['source_id'] == 'ls:constituency:senqu',
      str([(r['source_id'], r['parent_source_id']) for r in senq]))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)
