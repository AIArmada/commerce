import csv, os, sys
# Central African Republic (CF) gate. Pins the B12 law pass: 20 prefectures +
# 85 subprefectures = 105 rows. Fixes vs pre-state: dropped 3 stale pre-split
# duplicates (Ouham Batangafo, Ouham-Pendé Paoua/Ngaoundaye) + 1 junk scrape row
# ('Prefectures of the Central African Republic' under Vakaga); added Moboma
# (Lobaye), Nana-Outa (Nana-Grébizi), Ouandja-Kotto (Haute-Kotto), Ouandja +
# Amdafock (Vakaga), Bangui's 4 (Rapides/Fleuve/Centre/Kagas). Oracles: Loi
# n°21.001 du 21 janv. 2021 (adopted 10 Dec 2020) per-prefecture lists via
# Oubangui Médias, June-2024 sous-préfet decree (posts 1-85 contiguous),
# ICASEES RGPH-4 cartography, citypopulation 20 prefecture pages, EN/FR WP
# prefecture pages, ISO 3166-2:CF (still 17; LP/ME/OF provisional). Judgment
# calls: Amdafock (3v1 over decree-literal Amdafoc), Nana-Outa (2v1 over
# Nana-Ouata). No postcode system: UPU cafEn/cafFr codeless, UPU Sep-2025
# do-not-require, GeoNames CF.zip + CF.txt 404. Run from repo root:
# python3 docs/agents/audit/gate_cf.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/central-african-republic-address-areas.csv'
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
check('areas-105', len(rows) == 105, str(len(rows)))
check('areas-unique-ids', len(byid) == len(rows))
check('areas-country-CF', all(r['country_code'] == 'CF' for r in rows))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('prefectures-18', sum(1 for r in l1 if r['type'] == 'prefecture') == 18)
check('economic-2', sum(1 for r in l1 if r['type'] == 'economic_prefecture') == 2)
check('l1-parentless', all(not r['parent_source_id'] for r in l1))
check('subprefectures-85', len(l2) == 85 and all(r['type'] == 'subprefecture' for r in l2), str(len(l2)))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
# 17 ISO 3166-2:CF codes + 3 provisional (LP/ME/OF; ISO last change NL II-2 2010).
iso = {'cf:prefecture:bamingui-bangoran': ('Bamingui-Bangoran', 'BB'), 'cf:prefecture:bangui': ('Bangui', 'BGF'),
 'cf:prefecture:basse-kotto': ('Basse-Kotto', 'BK'), 'cf:prefecture:haut-mbomou': ('Haut-Mbomou', 'HM'),
 'cf:prefecture:haute-kotto': ('Haute-Kotto', 'HK'), 'cf:prefecture:kemo': ('Kémo', 'KG'),
 'cf:prefecture:lim-pende': ('Lim-Pendé', 'LP'), 'cf:prefecture:lobaye': ('Lobaye', 'LB'),
 'cf:prefecture:mambere': ('Mambéré', 'ME'), 'cf:prefecture:mambere-kadei': ('Mambéré-Kadéï', 'HS'),
 'cf:prefecture:mbomou': ('Mbomou', 'MB'), 'cf:economic_prefecture:nana-grebizi': ('Nana-Grébizi', 'KB'),
 'cf:prefecture:nana-mambere': ('Nana-Mambéré', 'NM'), 'cf:prefecture:ombella-mpoko': ("Ombella-M'Poko", 'MP'),
 'cf:prefecture:ouaka': ('Ouaka', 'UK'), 'cf:prefecture:ouham': ('Ouham', 'AC'),
 'cf:prefecture:ouham-fafa': ('Ouham-Fafa', 'OF'), 'cf:prefecture:ouham-pende': ('Ouham-Pendé', 'OP'),
 'cf:economic_prefecture:sangha-mbaere': ('Sangha-Mbaéré', 'SE'), 'cf:prefecture:vakaga': ('Vakaga', 'VK')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code, str(r))
# Full membership per Loi 21.001 lists + 2024 decree posts 1-85.
table = {'cf:prefecture:bamingui-bangoran': ['Bamingui', 'Ndélé'],
 'cf:prefecture:bangui': ['Bangui-Rapides', 'Bangui-Fleuve', 'Bangui-Centre', 'Bangui-Kagas'],
 'cf:prefecture:basse-kotto': ['Alindao', 'Kembé', 'Mingala', 'Mobaye', 'Satema', 'Zangba'],
 'cf:prefecture:haut-mbomou': ['Djemah', 'Obo', 'Zemio', 'Bambouti', 'Mboki'],
 'cf:prefecture:haute-kotto': ['Bria', 'Ouadda', 'Yalinga', 'Ouandja-Kotto'],
 'cf:prefecture:kemo': ['Dekoa', 'Sibut', 'Mala', 'Ndjoukou'],
 'cf:prefecture:lim-pende': ['Paoua', 'Ngaoundaye', 'Ndim', 'Kodi', 'Taley'],
 'cf:prefecture:lobaye': ['Boda', 'Mbaiki', 'Mongoumba', 'Boganangone', 'Boganda', 'Moboma'],
 'cf:prefecture:mambere': ['Carnot', 'Amada-Gaza', 'Gadzi', 'Senkpa-Mbaéré'],
 'cf:prefecture:mambere-kadei': ['Berbérati', 'Gamboula', 'Dédé-Makouba', 'Sosso-Nakombo'],
 'cf:prefecture:mbomou': ['Bakouma', 'Bangassou', 'Rafai', 'Gambo', 'Ouango'],
 'cf:economic_prefecture:nana-grebizi': ['Kaga-Bandoro', 'Mbrès', 'Nana-Outa'],
 'cf:prefecture:nana-mambere': ['Baboua', 'Baoro', 'Bouar', 'Abba'],
 'cf:prefecture:ombella-mpoko': ['Boali', 'Damara', 'Bogangolo', 'Yaloke', 'Bossembele'],
 'cf:prefecture:ouaka': ['Bakala', 'Bambari', 'Grimari', 'Ippy', 'Kouango'],
 'cf:prefecture:ouham': ['Bossangoa', 'Markounda', 'Nana-Bakassa', 'Nanga-Boguila'],
 'cf:prefecture:ouham-fafa': ['Batangafo', 'Bouca', 'Kabo', 'Sido'],
 'cf:prefecture:ouham-pende': ['Bocaranga', 'Bozoum', 'Bossemptele', 'Koui'],
 'cf:economic_prefecture:sangha-mbaere': ['Bambio', 'Bayanga', 'Nola'],
 'cf:prefecture:vakaga': ['Birao', 'Ouanda Djallé', 'Ouandja', 'Amdafock']}
kids = {}
for r in rows:
    if r['level'] == '2': kids.setdefault(r['parent_source_id'], []).append(r['name'])
for sid, names in table.items():
    check(f'mem-{sid.split(":")[-1]}', sorted(kids.get(sid, [])) == sorted(names), str(sorted(kids.get(sid, []))))
# B12 fix pins: new rows present under the right parents.
check('pin-moboma', byid.get('cf:subprefecture:moboma', {}).get('parent_source_id') == 'cf:prefecture:lobaye')
check('pin-nana-outa', byid.get('cf:subprefecture:nana-outa', {}).get('name') == 'Nana-Outa')
check('pin-ouandja-kotto', byid.get('cf:subprefecture:ouandja-kotto', {}).get('parent_source_id') == 'cf:prefecture:haute-kotto')
check('pin-ouandja', byid.get('cf:subprefecture:ouandja', {}).get('parent_source_id') == 'cf:prefecture:vakaga')
check('pin-amdafock', byid.get('cf:subprefecture:amdafock', {}).get('name') == 'Amdafock')
for b in ['rapides', 'fleuve', 'centre', 'kagas']:
    check(f'pin-bangui-{b}', byid.get(f'cf:subprefecture:bangui-{b}', {}).get('parent_source_id') == 'cf:prefecture:bangui')
# Drops gone; clean post-split copies kept.
for old in ['cf:subprefecture:batangafo', 'cf:subprefecture:ouham-pende:paoua',
            'cf:subprefecture:ouham-pende:ngaoundaye',
            'cf:subprefecture:prefectures-of-the-central-african-republic']:
    check(f'gone-{old.split(":")[-1]}', old not in byid)
check('kept-lim-pende-paoua', byid.get('cf:subprefecture:paoua', {}).get('parent_source_id') == 'cf:prefecture:lim-pende')
check('kept-lim-pende-ngaoundaye', byid.get('cf:subprefecture:ngaoundaye', {}).get('parent_source_id') == 'cf:prefecture:lim-pende')
check('kept-ouham-fafa-batangafo', byid.get('cf:subprefecture:ouham-fafa:batangafo', {}).get('parent_source_id') == 'cf:prefecture:ouham-fafa')
# ASCII holds (citypopulation accents are single-source styling).
check('hold-ascii', byid.get('cf:subprefecture:bossemptele', {}).get('name') == 'Bossemptele'
    and byid.get('cf:subprefecture:dekoa', {}).get('name') == 'Dekoa')
# No-postal pins: no CF postal CSVs exist.
check('no-postal-csvs', not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/central-african-republic-postal-codes.csv')
    and not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/central-african-republic-postal-code-areas.csv'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
