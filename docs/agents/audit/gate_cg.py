import csv, os, sys
# Congo (CG) gate. Pins the B12 law-rooted pass: 15 departments + 92 districts
# = 107 rows. Fixes vs pre-state: +Odziba (Djoué-Léfini, Law 24 creates),
# +Bouemba (Plateaux, Law 32), +Île Mbamou (Brazzaville, Law 29 + 2011 law),
# Ollombo Plateaux→Nkéni-Alima (Law 26), 5 JO-spelling renames (Vinza Law 25,
# Ongoni + Makotipoko Law 26, Bouaniéla Law 31, Mbandza-Ndounga Law 33).
# Oracles: JO N° 42-2024 Laws 24-34 (sgg.cg, primary), ISO 3166-2:CG (still 12;
# 17/18/19 provisional), EN+FR WP department/district pages, Statoids, GeoNames
# CG ADM dump, UNHCR briefing note, WHO sitrep, IRIN, ACAPS, 2011 rattachement
# laws (Tchiamba-Nzassi, Île Mbamou). No postcode system: UPU cogEn codeless
# (BP 652), UPU Sep-2025 do-not-require, GeoNames CG.zip 404. Run from repo
# root: python3 docs/agents/audit/gate_cg.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/congo-address-areas.csv'
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
check('areas-107', len(rows) == 107, str(len(rows)))
check('areas-unique-ids', len(byid) == len(rows))
check('areas-country-CG', all(r['country_code'] == 'CG' for r in rows))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('departments-15', len(l1) == 15 and all(r['type'] == 'department' and not r['parent_source_id'] for r in l1))
check('districts-92', len(l2) == 92 and all(r['type'] == 'district' for r in l2))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
# 12 ISO 3166-2:CG codes + 3 provisional (no ISO issue for the 2024 trio).
iso = {'cg:department:bouenza': ('Bouenza', '11'), 'cg:department:brazzaville': ('Brazzaville', 'BZV'),
 'cg:department:congo-oubangui': ('Congo-Oubangui', '17'), 'cg:department:cuvette': ('Cuvette', '8'),
 'cg:department:cuvette-ouest': ('Cuvette-Ouest', '15'), 'cg:department:djoue-lefini': ('Djoué-Léfini', '18'),
 'cg:department:kouilou': ('Kouilou', '5'), 'cg:department:lekoumou': ('Lékoumou', '2'),
 'cg:department:likouala': ('Likouala', '7'), 'cg:department:niari': ('Niari', '9'),
 'cg:department:nkeni-alima': ('Nkéni-Alima', '19'), 'cg:department:plateaux': ('Plateaux', '14'),
 'cg:department:pointe-noire': ('Pointe-Noire', '16'), 'cg:department:pool': ('Pool', '12'),
 'cg:department:sangha': ('Sangha', '13')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code, str(r))
# Full membership per JO N° 42-2024 Laws 24-34 (+ 2011 laws for Ile Mbamou /
# Tchiamba-Nzassi). Law spellings pinned even where directories dissent:
# Vinza (Statoids/FR-WP Vindza), Ongoni (WP Ongogni), Makotipoko (EN-WP
# Makotimpoko), Bouaniéla (WP Bouanéla), Mbandza-Ndounga (hyphen+dz).
table = {'cg:department:bouenza': ['Madingou', 'Mouyondzi', 'Boko–Songho', 'Mfouati', 'Loudima', 'Kayes', 'Kingoué', 'Mabombo', 'Tsiaki', 'Yamba'],
 'cg:department:brazzaville': ['Île Mbamou'],
 'cg:department:congo-oubangui': ['Mossaka', 'Bokoma', 'Liranga', 'Loukoléla'],
 'cg:department:cuvette': ['Owando', 'Makoua', 'Boundji', 'Oyo', 'Ngoko', 'Ntokou', 'Tchikapika'],
 'cg:department:cuvette-ouest': ['Ewo', 'Kellé', 'Mbomo', 'Okoyo', 'Etoumbi', 'Mbama'],
 'cg:department:djoue-lefini': ['Ignié', 'Kimba', 'Mayama', 'Ngabé', 'Odziba', 'Vinza'],
 'cg:department:kouilou': ['Hinda', 'Madingo–Kayes', 'Mvouti', 'Kakamoéka', 'Nzambi', 'Loango'],
 'cg:department:lekoumou': ['Sibiti', 'Komono', 'Zanaga', 'Bambama', 'Mayéyé'],
 'cg:department:likouala': ['Impfondo', 'Epéna', 'Dongou', 'Bétou', 'Bouaniéla', 'Enyellé'],
 'cg:department:niari': ['Louvakou', 'Kibangou', 'Divénié', 'Mayoko', 'Kimongo', 'Moutamba', 'Banda', 'Londéla–Kayes', 'Makabana', 'Mbinda', 'Moungoundou-sud', 'Nyanga', 'Moungoundou-nord', 'Yaya'],
 'cg:department:nkeni-alima': ['Abala', 'Allembé', 'Gamboma', 'Makotipoko', 'Ollombo', 'Ongoni'],
 'cg:department:plateaux': ['Djambala', 'Lékana', 'Mbon', 'Mpouya', 'Ngo', 'Bouemba'],
 'cg:department:pointe-noire': ['Tchiamba-Nzassi'],
 'cg:department:pool': ['Kinkala', 'Boko', 'Mindouli', 'Kindamba', 'Goma Tsé-Tsé', 'Mbandza-Ndounga', 'Louingui', 'Loumo'],
 'cg:department:sangha': ['Mokéko', 'Sembé', 'Souanké', 'Pikounda', "N'gbala", 'Kabo']}
kids = {}
for r in rows:
    if r['level'] == '2': kids.setdefault(r['parent_source_id'], []).append(r['name'])
for sid, names in table.items():
    check(f'mem-{sid.split(":")[-1]}', sorted(kids.get(sid, [])) == sorted(names), str(sorted(kids.get(sid, []))))
# B12 fix pins: new rows present, old slugs gone.
check('pin-odziba', byid.get('cg:district:odziba', {}).get('parent_source_id') == 'cg:department:djoue-lefini')
check('pin-bouemba', byid.get('cg:district:bouemba', {}).get('parent_source_id') == 'cg:department:plateaux')
check('pin-ile-mbamou', byid.get('cg:district:ile-mbamou', {}).get('name') == 'Île Mbamou')
check('pin-ollombo-moved', byid.get('cg:district:ollombo', {}).get('parent_source_id') == 'cg:department:nkeni-alima')
check('pin-vinza', byid.get('cg:district:vinza', {}).get('name') == 'Vinza')
check('pin-ongoni', byid.get('cg:district:ongoni', {}).get('name') == 'Ongoni')
check('pin-makotipoko', byid.get('cg:district:makotipoko', {}).get('name') == 'Makotipoko')
check('pin-bouaniela', byid.get('cg:district:bouaniela', {}).get('name') == 'Bouaniéla')
check('pin-mbandza', byid.get('cg:district:mbandzandounga', {}).get('name') == 'Mbandza-Ndounga')
for old in ['cg:district:vindza', 'cg:district:ongogni', 'cg:district:makotimpoko',
            'cg:district:bouanela', 'cg:district:mbanzandounga']:
    check(f'gone-{old.split(":")[-1]}', old not in byid)
# No-postal pins: no CG postal CSVs exist.
check('no-postal-csvs', not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/congo-postal-codes.csv')
    and not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/congo-postal-code-areas.csv'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
