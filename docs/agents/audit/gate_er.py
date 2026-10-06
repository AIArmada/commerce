import csv, sys
# Eritrea gate. B9 revisit: fix (Kudo Be'ur -> Emni Haili, the
# Debub 12th subregion: UN OCHA COD ER610 + GeoNames ADM2 +
# en-wp "Regions of Eritrea" + "Subdivisions of Eritrea" all
# say Emni Haili; only the "Subregions of Eritrea" list says
# Kudo Be'ur). Tree 6 regions (ISO 3166-2:ER codes) + 58
# subregions (11/7/14/10/12/4). WP-English naming convention
# kept (North Eastern etc.; COD/GN native forms noted but not
# adopted). No postal files bundled (no postcode system).
# Run from repo root:
# python3 docs/agents/audit/gate_er.py
A = './packages/addressing/resources/geography/eritrea-address-areas.csv'
REGIONS = {'er:region:anseba': ('Anseba', 'AN'),
           'er:region:debub': ('Debub', 'DU'),
           'er:region:gash-barka': ('Gash-Barka', 'GB'),
           'er:region:maekel': ('Maekel', 'MA'),
           'er:region:northern-red-sea': ('Northern Red Sea', 'SK'),
           'er:region:southern-red-sea': ('Southern Red Sea', 'DK')}
COUNTS = {'er:region:anseba': 11, 'er:region:maekel': 7,
          'er:region:gash-barka': 14, 'er:region:northern-red-sea': 10,
          'er:region:debub': 12, 'er:region:southern-red-sea': 4}
SUBS = {
 'er:region:anseba': ['Adi Tekelezan', 'Asmat', 'Hamelmalo', 'Elabered',
                      'Geleb', 'Hagaz', 'Halhal', 'Habero', 'Keren',
                      'Kerkebet', 'Sela'],
 'er:region:maekel': ['Berikh', 'Ghala Nefhi', 'North Eastern',
                      'North western', 'Serejaka', 'South Eastern',
                      'South Western'],
 'er:region:gash-barka': ['Akurdet', 'Barentu', 'Dghe', 'Forto', 'Gogne',
                          'Omhajer', 'Haykota', 'Logo Anseba', 'Mensura',
                          'Mogolo', 'Molki', 'Shambuko', 'Teseney',
                          'Upper Gash'],
 'er:region:northern-red-sea': ['Afabet', 'Adobha', 'Dahlak', "Ghela'elo",
                               'Foro', 'Ghinda', 'Karura', 'Massawa',
                               'Nakfa', "She'eb"],
 'er:region:debub': ['Mai ani', 'Tsorona', 'Emni Haili', 'Adi Keyh',
                     'Adi Quala', 'Areza', 'Debarwa', 'Dekemhare',
                     'Mai-Mne', 'Mendefera', 'Segeneiti', 'Senafe'],
 'er:region:southern-red-sea': ["Are'eta", 'Assab', 'Central Denkalya',
                                'Southern Denkalya']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-64', len(areas) == 64, str(len(areas)))
regs = [r for r in areas if r['type'] == 'region']
got_r = {r['source_id']: (r['name'], r['code']) for r in regs}
check('regions-6', got_r == REGIONS,
      str({k for k in REGIONS if got_r.get(k) != REGIONS[k]}))
subs = [r for r in areas if r['type'] == 'subregion']
check('subs-58', len(subs) == 58, str(len(subs)))
got = {}
for r in subs:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
check('sub-counts', {k: len(v) for k, v in got.items()} == COUNTS,
      str({k: len(got.get(k, [])) for k in COUNTS}))
bad = {k for k in SUBS if sorted(got.get(k, [])) != sorted(SUBS[k])}
check('sub-names', not bad, str(sorted(bad)))
check('emni-haili-id', any(r['source_id'] == 'er:subregion:emni-haili' for r in subs))
check('no-kudo', not any('kudo' in r['source_id'].lower() or 'Kudo' in r['name'] for r in subs))
check('levels', all(r['level'] == '2' for r in subs) and all(r['level'] == '1' for r in regs))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)
