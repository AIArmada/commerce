import csv, os, sys
A = './packages/addressing/resources/geography/togo-address-areas.csv'
G = './packages/addressing/resources/geography/'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-44', len(rows) == 44, str(len(rows)))
check('regions-5', sum(1 for r in rows if r['type'] == 'region') == 5)
check('prefectures-39', sum(1 for r in rows if r['type'] == 'prefecture') == 39)
# Oracle: en.wikipedia Prefectures of Togo (5/39; stale ISO lists show 30).
table = {
 'savanes': ['Kpendjal', 'Oti', 'Tandjouaré', 'Tône', 'Cinkassé', 'Oti-Sud', 'Kpendjal-Ouest'],
 'kara': ['Assoli', 'Bassar', 'Bimah', 'Dankpen', 'Doufelgou', 'Kéran', 'Kozah'],
 'centrale': ['Blitta', 'Sotouboua', 'Tchamba', 'Tchaoudjo', 'Mô'],
 'plateaux': ['Agou', 'Amou', 'Danyi', 'Est-Mono', 'Haho', 'Kloto', 'Moyen-Mono', 'Ogou', 'Wawa', 'Akébou', 'Anié', 'Kpélé'],
 'maritime': ['Avé', 'Golfe', 'Lacs', 'Vo', 'Yoto', 'Zio', 'Agoè-Nyivé', 'Bas-Mono'],
}
ok = True
for region, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == f'tg:region:{region}')
    if have != sorted(names):
        print('FAIL region', region, have); fails.append(f'region {region}'); ok = False
    check(f'{region}-{len(names)}', len(have) == len(names), str(len(have)))
if ok: print('PASS all 5 region mappings (39 names)')
# Post-ISO prefectures that distinguish the 39-count tree from stale 30-count lists.
for slug, region in [('kpendjal-ouest', 'savanes'), ('oti-sud', 'savanes'), ('cinkasse', 'savanes'),
                     ('mo', 'centrale'), ('akebou', 'plateaux'), ('anie', 'plateaux'),
                     ('kpele', 'plateaux'), ('agoe-nyive', 'maritime'), ('bas-mono', 'maritime')]:
    r = byid.get(f'tg:prefecture:{slug}')
    check(f'new-{slug}->{region}', r and r['parent_source_id'] == f'tg:region:{region}', str(r))
# Primary spellings where the oracle lists alternates (Bimah/Binah, Kozah/Koza).
check('bimah-primary', byid.get('tg:prefecture:bimah', {}).get('name') == 'Bimah')
check('kozah-primary', byid.get('tg:prefecture:kozah', {}).get('name') == 'Kozah')
for slug, code in [('centrale', 'C'), ('kara', 'K'), ('maritime', 'M'), ('plateaux', 'P'), ('savanes', 'S')]:
    r = byid.get(f'tg:region:{slug}')
    check(f'code-{slug}-{code}', r and r['code'] == code, str(r))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: no postal overlay files (UPU TGO profile is BP-based, no postcode).
check('no-codes-file', not os.path.exists(G + 'togo-postal-codes.csv'))
check('no-links-file', not os.path.exists(G + 'togo-postal-code-areas.csv'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
