import csv, os, sys
# Uganda gate. Adopted 5-digit postcode system (UPU ugaEn 1.2026)
# with no published allocation rows (gap, not codeless): ugapost
# serves a 559-byte app shell, UCC chart has zero postcode rows,
# Mapanet has no UG rows, GeoNames has no UG dump (404).
# M5 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_ug.py
A = './packages/addressing/resources/geography/uganda-address-areas.csv'
C = './packages/addressing/resources/geography/uganda-postal-codes.csv'
L = './packages/addressing/resources/geography/uganda-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-150', len(rows) == 150, str(len(rows)))
check('regions-4', sum(1 for r in rows if r['type'] == 'region') == 4)
check('districts-135', sum(1 for r in rows if r['type'] == 'district') == 135)
check('cities-11', sum(1 for r in rows if r['type'] == 'city') == 11)
# ISO 3166-2:UG region codes.
iso = {'ug:region:central': ('Central', 'C'),
 'ug:region:eastern': ('Eastern', 'E'),
 'ug:region:northern': ('Northern', 'N'),
 'ug:region:western': ('Western', 'W')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full region -> districts table per Districts-of-Uganda oracle
# (1 Jul 2020 era): 134 oracle rows + Kampala match exactly; CSV
# adds Madi-Okollo (real 2019-created district, ISO UG-336; the
# oracle tables total 134 + Kampala against their own 135 claim).
# Deliberate deviations from ISO spellings (oracle + UBOS agree
# with CSV): Bukomansimbi (ISO Bukomansibi), Luweero (ISO Luwero);
# Terego is post-ISO-vintage (oracle + CSV have it).
districts = {
 'ug:region:central': ['Buikwe', 'Bukomansimbi',
     'Butambala', 'Buvuma', 'Gomba', 'Kalangala', 'Kalungu',
     'Kasanda', 'Kayunga', 'Kiboga', 'Kyankwanzi', 'Kyotera',
     'Luweero', 'Lwengo', 'Lyantonde', 'Masaka', 'Mityana',
     'Mpigi', 'Mubende', 'Mukono', 'Nakaseke', 'Nakasongola',
     'Rakai', 'Sembabule', 'Wakiso'],
 'ug:region:eastern': ['Amuria', 'Budaka',
     'Bududa', 'Bugiri', 'Bugweri', 'Bukedea', 'Bukwo',
     'Bulambuli', 'Busia', 'Butaleja', 'Butebo', 'Buyende',
     'Iganga', 'Jinja', 'Kaberamaido', 'Kalaki', 'Kaliro',
     'Kamuli', 'Kapchorwa', 'Kapelebyong', 'Katakwi', 'Kibuku',
     'Kumi', 'Kween', 'Luuka', 'Manafwa', 'Mayuge',
     'Mbale', 'Namayingo', 'Namisindwa', 'Namutumba', 'Ngora',
     'Pallisa', 'Serere', 'Sironko', 'Soroti', 'Tororo'],
 'ug:region:northern': ['Abim', 'Adjumani',
     'Agago', 'Alebtong', 'Amolatar', 'Amudat', 'Amuru',
     'Apac', 'Arua', 'Dokolo', 'Gulu', 'Kaabong',
     'Karenga', 'Kitgum', 'Koboko', 'Kole', 'Kotido',
     'Kwania', 'Lamwo', 'Lira', 'Madi-Okollo', 'Maracha',
     'Moroto', 'Moyo', 'Nabilatuk', 'Nakapiripirit', 'Napak',
     'Nebbi', 'Nwoya', 'Obongi', 'Omoro', 'Otuke',
     'Oyam', 'Pader', 'Pakwach', 'Terego', 'Yumbe',
     'Zombo'],
 'ug:region:western': ['Buhweju', 'Buliisa',
     'Bundibugyo', 'Bunyangabu', 'Bushenyi', 'Hoima', 'Ibanda',
     'Isingiro', 'Kabale', 'Kabarole', 'Kagadi', 'Kakumiro',
     'Kamwenge', 'Kanungu', 'Kasese', 'Kazo', 'Kibaale',
     'Kikuube', 'Kiruhura', 'Kiryandongo', 'Kisoro', 'Kitagwenda',
     'Kyegegwa', 'Kyenjojo', 'Masindi', 'Mbarara', 'Mitooma',
     'Ntoroko', 'Ntungamo', 'Rubanda', 'Rubirizi', 'Rukiga',
     'Rukungiri', 'Rwampara', 'Sheema'],
}
# 10 regional cities operational 1 Jul 2020 (IGC/NPC list) plus
# Kampala capital city (ISO UG-102). The 5 approved-but-unfunded
# cities (Kabale, Moroto, Wakiso, Nakasongola, Entebbe) are out.
cities = {
 'ug:region:central': ['Kampala', 'Masaka'],
 'ug:region:eastern': ['Jinja', 'Mbale', 'Soroti'],
 'ug:region:northern': ['Arua', 'Gulu', 'Lira'],
 'ug:region:western': ['Fort Portal', 'Hoima', 'Mbarara'],
}
ok = True
for par, names in districts.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par and r['type'] == 'district')
    if have != sorted(names):
        print('FAIL districts', par, have); fails.append(f'districts {par}'); ok = False
    check(f"dist-{par.split(':')[-1]}-{len(names)}", len(have) == len(names), str(len(have)))
for par, names in cities.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par and r['type'] == 'city')
    if have != sorted(names):
        print('FAIL cities', par, have); fails.append(f'cities {par}'); ok = False
    check(f"city-{par.split(':')[-1]}-{len(names)}", len(have) == len(names), str(len(have)))
if ok: print('PASS all 4 region mappings (135 districts + 11 cities)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Gap stands: UPU require-list + ugaEn profile (1.2026) + 99999
# format document an adopted district/locality/zone system, but
# no allocation rows are published anywhere, so no overlay ships.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
