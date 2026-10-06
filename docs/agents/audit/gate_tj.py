import csv, os, sys
# Tajikistan gate. M3 revisit 2026-10-03: verify-only-tree, zero data
# changes. Tree matches ISO 3166-2:TJ + Districts of Tajikistan oracle
# (rev 2026-09-30) 69/69. No postal overlay ships: the Tajik Post index
# table (336 codes) is truncated mid-Khatlon with 14 shared codes and
# single-signal localities, and no second directory exists (Mapanet
# paywalled, GeoNames 404, youbianku stub, WPC 404) -- see the gap
# program in the Tajikistan findings bullet in 18-postal-overlays.md.
# Run from repo root:
# python3 docs/agents/audit/gate_tj.py
A = './packages/addressing/resources/geography/tajikistan-address-areas.csv'
C = './packages/addressing/resources/geography/tajikistan-postal-codes.csv'
L = './packages/addressing/resources/geography/tajikistan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-74', len(rows) == 74, str(len(rows)))
check('L1-5', sum(1 for r in rows if r['level'] == '1') == 5)
check('L2-69', sum(1 for r in rows if r['level'] == '2') == 69)
check('types-2-1-1-1-51-18',
      sum(1 for r in rows if r['type'] == 'region') == 2
      and sum(1 for r in rows if r['type'] == 'capital_territory') == 1
      and sum(1 for r in rows if r['type'] == 'autonomous_region') == 1
      and sum(1 for r in rows if r['type'] == 'districts_under_republic_administration') == 1
      and sum(1 for r in rows if r['type'] == 'district') == 51
      and sum(1 for r in rows if r['type'] == 'city') == 18)
# ISO 3166-2:TJ first level (DU added 2014, RA added 2016,
# Leninabad -> Sughd 2002; RA keeps pre-2017 capitalisation).
iso = {'tj:capital_territory:dushanbe': ('Dushanbe', 'DU'),
       'tj:autonomous_region:gorno-badakhshan': ('Gorno-Badakhshan', 'GB'),
       'tj:region:khatlon': ('Khatlon', 'KT'),
       'tj:districts_under_republic_administration:nohiyahoi-tobei-jumhuri':
       ('Nohiyahoi Tobei Jumhurí', 'RA'),
       'tj:region:sughd': ('Sughd', 'SU')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'iso-{cd}', r and r['name'] == nm and r['code'] == cd
          and r['parent_source_id'] == '' and r['level'] == '1', str(r))
# Full L2 table: Districts of Tajikistan oracle (47 districts +
# 4 Dushanbe districts + 18 cities incl. Dushanbe under RRP).
table = {
 'tj:region:sughd': ['Mastchoh', 'Bobojon Ghafurov', 'Asht', 'Zafarobod',
    'Spitamen', 'Jabbor Rasulov', 'Shahriston', 'Devashtich', 'Ayni',
    'Kuhistoni Mastchoh', 'Konibodom', 'Isfara', 'Istaravshan',
    'Panjakent', 'Khujand', 'Istiqlol', 'Guliston', 'Buston'],
 'tj:region:khatlon': ['Khuroson', 'Yovon', 'Baljuvon', 'Khovaling',
    'Jomi', 'Danghara', 'Temurmalik', "Mu'minobod", 'Kushoniyon',
    'Vakhsh', "Vose'", 'Shamsiddin Shohin', 'Nosiri Khusrav',
    'Shahritus', 'Qubodiyon', 'Dusti', 'Jayhun', 'Jaloliddin Balkhi',
    'Farkhor', 'Panj', 'Hamadoni', 'Bokhtar', 'Norak', 'Levakant',
    'Kulob'],
 'tj:autonomous_region:gorno-badakhshan': ['Darvoz', 'Vanj', 'Rushon',
    'Shughnon', "Roshtqal'a", 'Ishkoshim', 'Murghob', 'Khorugh'],
 'tj:districts_under_republic_administration:nohiyahoi-tobei-jumhuri':
    ['Shahrinav', 'Varzob', 'Rasht', 'Lakhsh', 'Rudaki', 'Fayzobod',
     'Nurobod', 'Tojikobod', 'Sangvor', 'Tursunzoda', 'Hisor',
     'Vahdat', 'Roghun', 'Dushanbe'],
 'tj:capital_territory:dushanbe': ['Ibn Sina', 'Firdavsi',
    'Ismail Somoni', 'Shohmansur'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 5 parent mappings (69 L2)')
check('all-L2-parented', all(r['parent_source_id'] in byid
      for r in rows if r['level'] == '2'))
orph = [r['source_id'] for r in rows
        if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Deliberate deviation: oracle table display says "Roshtqala" but the
# target article is "Roshtqal'a District" and native is Роштқалъа.
r = byid.get('tj:district:roshtqal-a')
check('roshtqala-deviation', r and r['name'] == "Roshtqal'a"
      and r['parent_source_id'] == 'tj:autonomous_region:gorno-badakhshan',
      str(r))
# Post-Soviet renames are applied (spot pins; full list in verdict).
for sid, nm in [('tj:region:sughd', 'Sughd'),
                ('tj:district:spitamen', 'Spitamen'),
                ('tj:district:devashtich', 'Devashtich'),
                ('tj:city:istaravshan', 'Istaravshan'),
                ('tj:city:istiqlol', 'Istiqlol'),
                ('tj:city:guliston', 'Guliston'),
                ('tj:city:buston', 'Buston'),
                ('tj:city:bokhtar', 'Bokhtar'),
                ('tj:city:levakant', 'Levakant'),
                ('tj:district:jayhun', 'Jayhun'),
                ('tj:district:jaloliddin-balkhi', 'Jaloliddin Balkhi'),
                ('tj:district:shamsiddin-shohin', 'Shamsiddin Shohin'),
                ('tj:district:lakhsh', 'Lakhsh'),
                ('tj:district:rasht', 'Rasht'),
                ('tj:district:rudaki', 'Rudaki'),
                ('tj:city:vahdat', 'Vahdat'),
                ('tj:district:kushoniyon', 'Kushoniyon')]:
    check(f'rename-{sid.split(":")[-1]}', byid.get(sid, {}).get('name') == nm,
          str(byid.get(sid)))
soviet = ['Leninabad', 'Leninobod', 'Uroteppa', 'Taboshar', 'Kayrakkum',
          'Chkalovsk', 'Qurghonteppa', 'Sarband', 'Kofarnihon',
          'Jirgatol', 'Gharm', 'Leninskiy', 'Jilikul', 'Qumsangir',
          'Shuroobod', 'Moskovskiy', 'Rumi', 'Beshkent']
bad = [r['source_id'] for r in rows if r['name'] in soviet]
check('no-soviet-names', not bad, str(bad[:3]))
# Verdict verify-only-tree (scope stays expansion): no postal overlay
# ships yet. The 6-digit system is live (UPU TJK 09/2019 + Tajik Post
# table modified 2025-09-16) but the operator table is truncated
# mid-Khatlon (22/69 L2 codeless incl. all Dushanbe districts) with 14
# shared codes, and every locality code is single-signal. A future seed
# must resolve the gap program in verdict.json first -- and delete
# these two pins deliberately, never silently.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
