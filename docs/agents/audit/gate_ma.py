import csv, re, sys
from collections import Counter
# Morocco gate. M4 revisit: tree verified clean against ISO 3166-2:MA
# (12 regions + 75 L2, all parents per Prefectures-and-provinces list);
# postal fix = 3 primary flips to the Poste Maroc annuaire filing
# (35224 Oulad Ayyad Taza->Taounate, 80100 + 80650 Agadir->Inezgane
# quartiers) + 6 xx119 Casablanca phantom drops. Run from repo root:
# python3 /tmp/geo-verify/M4/MA/gate_ma.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/morocco-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/morocco-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/morocco-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 12 regions + 13 prefectures + 62 provinces ---
check('areas-87', len(rows) == 87, str(len(rows)))
check('regions-12', sum(1 for r in rows if r['type'] == 'region') == 12)
check('prefectures-13', sum(1 for r in rows if r['type'] == 'prefecture') == 13)
check('provinces-62', sum(1 for r in rows if r['type'] == 'province') == 62)
regions = {'01': "Tanger-Tétouan-Al Hoceïma", '02': "L'Oriental",
 '03': 'Fès-Meknès', '04': 'Rabat-Salé-Kénitra', '05': 'Béni Mellal-Khénifra',
 '06': 'Casablanca-Settat', '07': 'Marrakech-Safi', '08': 'Drâa-Tafilalet',
 '09': 'Souss-Massa', '10': 'Guelmim-Oued Noun',
 '11': 'Laâyoune-Sakia El Hamra', '12': 'Dakhla-Oued Ed-Dahab'}
for code, name in regions.items():
    got = [r for r in rows if r['type'] == 'region' and r['code'] == code]
    check(f'region-{code}', len(got) == 1 and got[0]['name'] == name
          and got[0]['level'] == '1' and not got[0]['parent_source_id'],
          str(got))
# ISO 3166-2:MA L2: source_id -> (name, type, ISO code, parent region code).
# Deliberate deviations from the ISO table, all backed by the
# Prefectures-and-provinces list + Poste Maroc usage: Taroudant (ISO
# Taroudannt), Mohammedia (ISO Mohammadia), Ait->Aït diacritics,
# straight apostrophe in M'diq-Fnideq; CHT->09 + NOU->06 keep ours
# (the ISO wiki-table In-region cells 06/04 are list errors).
iso = {
 'ma:prefecture:agadir-ida-ou-tanane': ('Agadir-Ida-Ou-Tanane', 'prefecture', 'AGD', '09'),
 'ma:province:aousserd': ('Aousserd', 'province', 'AOU', '12'),
 'ma:province:assa-zag': ('Assa-Zag', 'province', 'ASZ', '10'),
 'ma:province:azilal': ('Azilal', 'province', 'AZI', '05'),
 'ma:province:beni-mellal': ('Béni Mellal', 'province', 'BEM', '05'),
 'ma:province:berkane': ('Berkane', 'province', 'BER', '02'),
 'ma:province:benslimane': ('Benslimane', 'province', 'BES', '06'),
 'ma:province:boujdour': ('Boujdour', 'province', 'BOD', '11'),
 'ma:province:boulemane': ('Boulemane', 'province', 'BOM', '03'),
 'ma:province:berrechid': ('Berrechid', 'province', 'BRR', '06'),
 'ma:prefecture:casablanca': ('Casablanca', 'prefecture', 'CAS', '06'),
 'ma:province:chefchaouen': ('Chefchaouen', 'province', 'CHE', '01'),
 'ma:province:chichaoua': ('Chichaoua', 'province', 'CHI', '07'),
 'ma:province:chtouka-ait-baha': ('Chtouka-Aït-Baha', 'province', 'CHT', '09'),
 'ma:province:driouch': ('Driouch', 'province', 'DRI', '02'),
 'ma:province:errachidia': ('Errachidia', 'province', 'ERR', '08'),
 'ma:province:essaouira': ('Essaouira', 'province', 'ESI', '07'),
 'ma:province:es-semara': ('Es-Semara', 'province', 'ESM', '11'),
 'ma:province:fahs-anjra': ('Fahs-Anjra', 'province', 'FAH', '01'),
 'ma:prefecture:fes': ('Fès', 'prefecture', 'FES', '03'),
 'ma:province:figuig': ('Figuig', 'province', 'FIG', '02'),
 'ma:province:fquih-ben-salah': ('Fquih Ben Salah', 'province', 'FQH', '05'),
 'ma:province:guelmim': ('Guelmim', 'province', 'GUE', '10'),
 'ma:province:guercif': ('Guercif', 'province', 'GUF', '02'),
 'ma:province:el-hajeb': ('El Hajeb', 'province', 'HAJ', '03'),
 'ma:province:al-haouz': ('Al Haouz', 'province', 'HAO', '07'),
 'ma:province:al-hoceima': ('Al Hoceïma', 'province', 'HOC', '01'),
 'ma:province:ifrane': ('Ifrane', 'province', 'IFR', '03'),
 'ma:prefecture:inezgane-ait-melloul': ('Inezgane-Aït-Melloul', 'prefecture', 'INE', '09'),
 'ma:province:el-jadida': ('El Jadida', 'province', 'JDI', '06'),
 'ma:province:jerada': ('Jerada', 'province', 'JRA', '02'),
 'ma:province:kenitra': ('Kénitra', 'province', 'KEN', '04'),
 'ma:province:el-kelaa-des-sraghna': ('El Kelâa des Sraghna', 'province', 'KES', '07'),
 'ma:province:khemisset': ('Khémisset', 'province', 'KHE', '04'),
 'ma:province:khenifra': ('Khénifra', 'province', 'KHN', '05'),
 'ma:province:khouribga': ('Khouribga', 'province', 'KHO', '05'),
 'ma:province:laayoune': ('Laâyoune', 'province', 'LAA', '11'),
 'ma:province:larache': ('Larache', 'province', 'LAR', '01'),
 'ma:prefecture:marrakech': ('Marrakech', 'prefecture', 'MAR', '07'),
 'ma:prefecture:m-diq-fnideq': ("M'diq-Fnideq", 'prefecture', 'MDF', '01'),
 'ma:province:mediouna': ('Médiouna', 'province', 'MED', '06'),
 'ma:prefecture:meknes': ('Meknès', 'prefecture', 'MEK', '03'),
 'ma:province:midelt': ('Midelt', 'province', 'MID', '08'),
 'ma:prefecture:mohammedia': ('Mohammedia', 'prefecture', 'MOH', '06'),
 'ma:province:moulay-yacoub': ('Moulay Yacoub', 'province', 'MOU', '03'),
 'ma:province:nador': ('Nador', 'province', 'NAD', '02'),
 'ma:province:nouaceur': ('Nouaceur', 'province', 'NOU', '06'),
 'ma:province:ouarzazate': ('Ouarzazate', 'province', 'OUA', '08'),
 'ma:province:oued-ed-dahab': ('Oued Ed-Dahab', 'province', 'OUD', '12'),
 'ma:prefecture:oujda-angad': ('Oujda-Angad', 'prefecture', 'OUJ', '02'),
 'ma:province:ouezzane': ('Ouezzane', 'province', 'OUZ', '01'),
 'ma:prefecture:rabat': ('Rabat', 'prefecture', 'RAB', '04'),
 'ma:province:rehamna': ('Rehamna', 'province', 'REH', '07'),
 'ma:province:safi': ('Safi', 'province', 'SAF', '07'),
 'ma:prefecture:sale': ('Salé', 'prefecture', 'SAL', '04'),
 'ma:province:sefrou': ('Sefrou', 'province', 'SEF', '03'),
 'ma:province:settat': ('Settat', 'province', 'SET', '06'),
 'ma:province:sidi-bennour': ('Sidi Bennour', 'province', 'SIB', '06'),
 'ma:province:sidi-ifni': ('Sidi Ifni', 'province', 'SIF', '10'),
 'ma:province:sidi-kacem': ('Sidi Kacem', 'province', 'SIK', '04'),
 'ma:province:sidi-slimane': ('Sidi Slimane', 'province', 'SIL', '04'),
 'ma:prefecture:skhirate-temara': ('Skhirate-Témara', 'prefecture', 'SKH', '04'),
 'ma:province:tarfaya': ('Tarfaya', 'province', 'TAF', '11'),
 'ma:province:taourirt': ('Taourirt', 'province', 'TAI', '02'),
 'ma:province:taounate': ('Taounate', 'province', 'TAO', '03'),
 'ma:province:taroudant': ('Taroudant', 'province', 'TAR', '09'),
 'ma:province:tata': ('Tata', 'province', 'TAT', '09'),
 'ma:province:taza': ('Taza', 'province', 'TAZ', '03'),
 'ma:province:tetouan': ('Tétouan', 'province', 'TET', '01'),
 'ma:province:tinghir': ('Tinghir', 'province', 'TIN', '08'),
 'ma:province:tiznit': ('Tiznit', 'province', 'TIZ', '09'),
 'ma:prefecture:tanger-assilah': ('Tanger-Assilah', 'prefecture', 'TNG', '01'),
 'ma:province:tan-tan': ('Tan-Tan', 'province', 'TNT', '10'),
 'ma:province:youssoufia': ('Youssoufia', 'province', 'YUS', '07'),
 'ma:province:zagora': ('Zagora', 'province', 'ZAG', '08'),
}
preg = {r['source_id']: r['code'] for r in rows if r['type'] == 'region'}
bad = [sid for sid, (name, typ, code, rg) in iso.items()
       if sid not in byid or byid[sid]['name'] != name
       or byid[sid]['type'] != typ or byid[sid]['code'] != code
       or byid[sid]['level'] != '2' or preg.get(byid[sid]['parent_source_id']) != rg]
check('iso-75', not bad, str(bad[:3]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 2083 codes / 2088 links (post-fix) ---
check('codes-2083', len(codes) == 2083, str(len(codes)))
check('links-2088', len(links) == 2088, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
cset = {c['code'] for c in codes}
lset = {l['postcode'] for l in links}
check('codes-linked-both-ways', cset == lset,
      f'unlinked={sorted(cset - lset)[:3]} dangling={sorted(lset - cset)[:3]}')
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 2083 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-5', multis == ['29004', '35224', '80100', '80650', '86603'], str(multis))
# Full multi map (M4-adjudicated: 35224 + 80100 + 80650 primaries moved
# to the Poste Maroc annuaire filing; 29004 + 86603 keeps).
exp = {
 '29004': [('ma:province:mediouna', 'true'), ('ma:prefecture:casablanca', 'false')],
 '35224': [('ma:province:taounate', 'true'), ('ma:province:taza', 'false')],
 '80100': [('ma:prefecture:inezgane-ait-melloul', 'true'),
           ('ma:prefecture:agadir-ida-ou-tanane', 'false')],
 '80650': [('ma:prefecture:inezgane-ait-melloul', 'true'),
           ('ma:prefecture:agadir-ida-ou-tanane', 'false')],
 '86603': [('ma:prefecture:inezgane-ait-melloul', 'true'),
           ('ma:prefecture:agadir-ida-ou-tanane', 'false')],
}
got = {}
for l in links:
    if l['postcode'] in exp:
        got.setdefault(l['postcode'], []).append((l['area_source_id'], l['is_primary']))
ok = all(sorted(got.get(pc, [])) == sorted(v) for pc, v in exp.items())
check('multi-map-5', ok, str({k: got.get(k) for k in exp if sorted(got.get(k, [])) != sorted(exp[k])}))
# xx119 Casablanca phantoms dropped (no annuaire/GN/PCB trace, bare
# no-suburb Mapanet rows, suffix 119 unattested officially).
xx = [c for c in cset if c.endswith('119')]
check('no-xx119', not xx, str(xx))
# Annuaire-confirmed single-link anchors (GN-wins pair + keeps).
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
check('pin-90052-tanger', pin.get('90052') == 'ma:prefecture:tanger-assilah')
check('pin-16175-sidi-kacem', pin.get('16175') == 'ma:province:sidi-kacem')
check('pin-10000-rabat', pin.get('10000') == 'ma:prefecture:rabat')
check('pin-52000-errachidia', pin.get('52000') == 'ma:province:errachidia')
check('pin-26105-settat', pin.get('26105') == 'ma:province:settat')
check('pin-35113-guercif', pin.get('35113') == 'ma:province:guercif')
check('pin-12050-skhirate', pin.get('12050') == 'ma:prefecture:skhirate-temara')
# Per-area primary counts (62 covered L2; 13 new/small codeless).
exp_counts = {
 'ma:prefecture:agadir-ida-ou-tanane': 38,
 'ma:prefecture:casablanca': 135,
 'ma:prefecture:fes': 36,
 'ma:prefecture:inezgane-ait-melloul': 17,
 'ma:prefecture:m-diq-fnideq': 0,
 'ma:prefecture:marrakech': 57,
 'ma:prefecture:meknes': 46,
 'ma:prefecture:mohammedia': 12,
 'ma:prefecture:oujda-angad': 28,
 'ma:prefecture:rabat': 52,
 'ma:prefecture:sale': 36,
 'ma:prefecture:skhirate-temara': 24,
 'ma:prefecture:tanger-assilah': 34,
 'ma:province:al-haouz': 31,
 'ma:province:al-hoceima': 34,
 'ma:province:aousserd': 1,
 'ma:province:assa-zag': 8,
 'ma:province:azilal': 44,
 'ma:province:beni-mellal': 63,
 'ma:province:benslimane': 17,
 'ma:province:berkane': 23,
 'ma:province:berrechid': 0,
 'ma:province:boujdour': 2,
 'ma:province:boulemane': 31,
 'ma:province:chefchaouen': 32,
 'ma:province:chichaoua': 25,
 'ma:province:chtouka-ait-baha': 30,
 'ma:province:driouch': 0,
 'ma:province:el-hajeb': 16,
 'ma:province:el-jadida': 54,
 'ma:province:el-kelaa-des-sraghna': 57,
 'ma:province:errachidia': 63,
 'ma:province:es-semara': 3,
 'ma:province:essaouira': 41,
 'ma:province:fahs-anjra': 8,
 'ma:province:figuig': 17,
 'ma:province:fquih-ben-salah': 0,
 'ma:province:guelmim': 23,
 'ma:province:guercif': 1,
 'ma:province:ifrane': 14,
 'ma:province:jerada': 16,
 'ma:province:kenitra': 55,
 'ma:province:khemisset': 45,
 'ma:province:khenifra': 48,
 'ma:province:khouribga': 44,
 'ma:province:laayoune': 21,
 'ma:province:larache': 17,
 'ma:province:mediouna': 8,
 'ma:province:midelt': 0,
 'ma:province:moulay-yacoub': 11,
 'ma:province:nador': 49,
 'ma:province:nouaceur': 5,
 'ma:province:ouarzazate': 51,
 'ma:province:oued-ed-dahab': 3,
 'ma:province:ouezzane': 0,
 'ma:province:rehamna': 0,
 'ma:province:safi': 47,
 'ma:province:sefrou': 30,
 'ma:province:settat': 62,
 'ma:province:sidi-bennour': 0,
 'ma:province:sidi-ifni': 0,
 'ma:province:sidi-kacem': 38,
 'ma:province:sidi-slimane': 0,
 'ma:province:tan-tan': 7,
 'ma:province:taounate': 59,
 'ma:province:taourirt': 13,
 'ma:province:tarfaya': 0,
 'ma:province:taroudant': 82,
 'ma:province:tata': 24,
 'ma:province:taza': 70,
 'ma:province:tetouan': 30,
 'ma:province:tinghir': 0,
 'ma:province:tiznit': 67,
 'ma:province:youssoufia': 0,
 'ma:province:zagora': 28,
}
have = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
wrong = [sid for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-area-counts-75', not wrong, str(wrong[:5]))
check('covered-62', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 62)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
