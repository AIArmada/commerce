import csv, re, sys
from collections import Counter
# Mozambique gate. M5 revisit: 32 fills + 1 Doa secondary (113->145
# codes, 118->151 links). Fresh 436-row/138-cell Mapanet re-pull
# (build saw 329/100): all 113 kept codes still present, cell
# attribution consistent; fills carry Mapanet cell+capital with OSM
# containment per locality. Run from repo root:
# python3 docs/agents/audit/gate_mz.py
A = './packages/addressing/resources/geography/mozambique-address-areas.csv'
C = './packages/addressing/resources/geography/mozambique-postal-codes.csv'
L = './packages/addressing/resources/geography/mozambique-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 10 provinces + Maputo city + 136 districts ---
check('areas-147', len(rows) == 147, str(len(rows)))
check('provinces-10', sum(1 for r in rows if r['type'] == 'province') == 10)
check('cities-1', sum(1 for r in rows if r['type'] == 'city') == 1)
check('districts-136', sum(1 for r in rows if r['type'] == 'district') == 136)
iso = {'mz:province:niassa': ('Niassa', 'A'),
 'mz:province:manica': ('Manica', 'B'),
 'mz:province:gaza': ('Gaza', 'G'),
 'mz:province:inhambane': ('Inhambane', 'I'),
 'mz:province:maputo-province': ('Maputo Province', 'L'),
 'mz:city:maputo-city': ('Maputo City', 'MPM'),
 'mz:province:nampula': ('Nampula', 'N'),
 'mz:province:cabo-delgado': ('Cabo Delgado', 'P'),
 'mz:province:zambezia': ('Zambezia', 'Q'),
 'mz:province:sofala': ('Sofala', 'S'),
 'mz:province:tete': ('Tete', 'T')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Zambezia ships ASCII (ISO Zambézia); UPU profile also uses Zambezia.
check('zambezia-ascii', byid['mz:province:zambezia']['name'] == 'Zambezia')
table = {
 'mz:city:maputo-city': ['KaMavota', 'KaMaxaquene', 'KaMpfumo', 'KaMubukwana', 'KaNyaka', 'KaTembe', 'Nlhamankulu'],
 'mz:province:cabo-delgado': ['Ancuabe', 'Balama', 'Chiúre', 'Ibo', 'Macomia', 'Mecúfi', 'Meluco', 'Metuge', 'Mocímboa da Praia', 'Montepuez', 'Mueda', 'Muidumbe', 'Namuno', 'Nangade', 'Palma', 'Quissanga'],
 'mz:province:gaza': ['Bilene Macia', 'Chibuto', 'Chicualacuala', 'Chigubo', 'Chókwè', 'Guijá', 'Mabalane', 'Manjacaze', 'Massangena', 'Massingir', 'Xai-Xai'],
 'mz:province:inhambane': ['Funhalouro', 'Govuro', 'Homoine', 'Inharrime', 'Inhassoro', 'Jangamo', 'Mabote', 'Massinga', 'Morrumbene', 'Panda', 'Vilanculos', 'Zavala'],
 'mz:province:manica': ['Báruè', 'Gondola', 'Guro', 'Machaze', 'Macossa', 'Manica', 'Mossurize', 'Sussundenga', 'Tambara'],
 'mz:province:maputo-province': ['Boane', 'Magude', 'Manhiça', 'Marracuene', 'Matutuíne', 'Moamba', 'Namaacha'],
 'mz:province:nampula': ['Angoche', 'Eráti', 'Lalaua', 'Malema', 'Meconta', 'Mecubúri', 'Memba', 'Mogincual', 'Mogovolas', 'Moma', 'Monapo', 'Mossuril', 'Muecate', 'Murrupula', 'Nacala-a-Velha', 'Nacarôa', 'Nampula', 'Ribáuè'],
 'mz:province:niassa': ['Cuamba', 'Lago', 'Lichinga', 'Majune', 'Mandimba', 'Marrupa', 'Mavago', 'Maúa', 'Mecanhelas', 'Mecula', 'Metarica', 'Muembe', "N'gauma", 'Nipepe', 'Sanga'],
 'mz:province:sofala': ['Buzi', 'Caia', 'Chemba', 'Cheringoma', 'Chibabava', 'Dondo', 'Gorongosa', 'Machanga', 'Maringué', 'Marromeu', 'Muanza', 'Nhamatanda'],
 'mz:province:tete': ['Angónia', 'Cahora-Bassa', 'Changara', 'Chifunde', 'Chiuta', 'Doa', 'Macanga', 'Magoé', 'Marávia', 'Moatize', 'Mutarara', 'Tsangano', 'Zumbo'],
 'mz:province:zambezia': ['Alto Molocue', 'Chinde', 'Gilé', 'Gurué', 'Ile', 'Inhassunge', 'Lugela', 'Maganja da Costa', 'Milange', 'Mocuba', 'Mopeia', 'Morrumbala', 'Namacurra', 'Namarroi', 'Nicoadala', 'Pebane'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 11 parent mappings (136 districts)')
# Maxixe is a city (municipality), not a district: no district row,
# no "Maxixe District" article, Statoids lists Cidade de Maxixe.
check('no-maxixe-district', 'mz:district:maxixe' not in byid)
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 145 codes / 151 links, all L2 ---
check('codes-145', len(codes) == 145, str(len(codes)))
check('links-151', len(links) == 151, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{4}$', c['code'])]
check('code-format-4n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 145 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('all-l2', all(l['area_source_id'].split(':')[1] == 'district'
      for l in links))
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('codes-align-links', code_set == link_set)
# --- multis: 3206/3211 triples + 3301/2307 duals ---
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-4', multis == ['2307', '3206', '3211', '3301'], str(multis))
exp = {'3206': [('mz:district:ancuabe', 'true'),
    ('mz:district:chiure', 'false'), ('mz:district:mecufi', 'false')],
 '3211': [('mz:district:macomia', 'true'),
    ('mz:district:ibo', 'false'), ('mz:district:quissanga', 'false')],
 '3301': [('mz:district:mecanhelas', 'true'),
    ('mz:district:mandimba', 'false')],
 '2307': [('mz:district:mutarara', 'true'),
    ('mz:district:doa', 'false')]}
for pc, legs in exp.items():
    got = sorted((l['area_source_id'], l['is_primary']) for l in links
                 if l['postcode'] == pc)
    check(f'multi-{pc}', got == sorted(legs), str(got))
# secondaries are OSM-contained: Chiure-Sede in the Ancuabe cell is
# Chiure town; Murrebue is Mecufi; Quirimba is Ibo; Mandimba-Sede is
# Mandimba; the Doa town row sits in the Mutarara cell.
# --- per-district primary counts (post M5 fills) ---
expect = {
 'mz:district:alto-molocue': 1,
 'mz:district:ancuabe': 1,
 'mz:district:angoche': 1,
 'mz:district:angonia': 1,
 'mz:district:balama': 1,
 'mz:district:barue': 1,
 'mz:district:bilene-macia': 1,
 'mz:district:boane': 1,
 'mz:district:buzi': 1,
 'mz:district:cahora-bassa': 1,
 'mz:district:caia': 1,
 'mz:district:changara': 1,
 'mz:district:chemba': 1,
 'mz:district:cheringoma': 1,
 'mz:district:chibabava': 1,
 'mz:district:chibuto': 1,
 'mz:district:chicualacuala': 1,
 'mz:district:chifunde': 1,
 'mz:district:chigubo': 1,
 'mz:district:chinde': 1,
 'mz:district:chiure': 1,
 'mz:district:chiuta': 1,
 'mz:district:chokwe': 1,
 'mz:district:cuamba': 1,
 'mz:district:dondo': 2,
 'mz:district:erati': 1,
 'mz:district:funhalouro': 1,
 'mz:district:gile': 1,
 'mz:district:gondola': 1,
 'mz:district:gorongosa': 1,
 'mz:district:govuro': 1,
 'mz:district:guija': 1,
 'mz:district:guro': 1,
 'mz:district:gurue': 1,
 'mz:district:homoine': 1,
 'mz:district:ibo': 1,
 'mz:district:ile': 1,
 'mz:district:inharrime': 1,
 'mz:district:inhassoro': 1,
 'mz:district:inhassunge': 1,
 'mz:district:jangamo': 2,
 'mz:district:kamavota': 1,
 'mz:district:kampfumo': 6,
 'mz:district:kamubukwana': 3,
 'mz:district:kanyaka': 1,
 'mz:district:katembe': 1,
 'mz:district:lago': 1,
 'mz:district:lalaua': 1,
 'mz:district:lichinga': 1,
 'mz:district:lugela': 1,
 'mz:district:mabalane': 1,
 'mz:district:mabote': 1,
 'mz:district:macanga': 1,
 'mz:district:machanga': 1,
 'mz:district:machaze': 1,
 'mz:district:macomia': 1,
 'mz:district:macossa': 1,
 'mz:district:maganja-da-costa': 1,
 'mz:district:magoe': 1,
 'mz:district:magude': 1,
 'mz:district:majune': 1,
 'mz:district:malema': 1,
 'mz:district:mandimba': 1,
 'mz:district:manhica': 2,
 'mz:district:manica': 2,
 'mz:district:manjacaze': 1,
 'mz:district:maravia': 1,
 'mz:district:maringue': 1,
 'mz:district:marracuene': 1,
 'mz:district:marromeu': 1,
 'mz:district:marrupa': 1,
 'mz:district:massangena': 1,
 'mz:district:massinga': 1,
 'mz:district:massingir': 1,
 'mz:district:matutuine': 1,
 'mz:district:maua': 1,
 'mz:district:mavago': 1,
 'mz:district:mecanhelas': 1,
 'mz:district:meconta': 2,
 'mz:district:mecuburi': 1,
 'mz:district:mecufi': 1,
 'mz:district:mecula': 1,
 'mz:district:meluco': 1,
 'mz:district:memba': 1,
 'mz:district:metarica': 1,
 'mz:district:metuge': 2,
 'mz:district:milange': 1,
 'mz:district:moamba': 2,
 'mz:district:moatize': 1,
 'mz:district:mocimboa-da-praia': 1,
 'mz:district:mocuba': 1,
 'mz:district:mogovolas': 1,
 'mz:district:moma': 1,
 'mz:district:monapo': 1,
 'mz:district:montepuez': 1,
 'mz:district:mopeia': 1,
 'mz:district:morrumbala': 1,
 'mz:district:morrumbene': 1,
 'mz:district:mossuril': 1,
 'mz:district:mossurize': 1,
 'mz:district:muanza': 1,
 'mz:district:muecate': 1,
 'mz:district:mueda': 1,
 'mz:district:muembe': 1,
 'mz:district:muidumbe': 1,
 'mz:district:murrupula': 1,
 'mz:district:mutarara': 1,
 'mz:district:n-gauma': 1,
 'mz:district:nacala-a-velha': 1,
 'mz:district:nacaroa': 1,
 'mz:district:namaacha': 1,
 'mz:district:namacurra': 1,
 'mz:district:namarroi': 1,
 'mz:district:namuno': 1,
 'mz:district:nangade': 1,
 'mz:district:nhamatanda': 1,
 'mz:district:nicoadala': 1,
 'mz:district:nipepe': 1,
 'mz:district:nlhamankulu': 1,
 'mz:district:palma': 1,
 'mz:district:panda': 1,
 'mz:district:pebane': 1,
 'mz:district:quissanga': 1,
 'mz:district:ribaue': 1,
 'mz:district:sanga': 1,
 'mz:district:sussundenga': 1,
 'mz:district:tambara': 1,
 'mz:district:tsangano': 1,
 'mz:district:vilanculos': 1,
 'mz:district:zavala': 1,
 'mz:district:zumbo': 1,
}
have = Counter(l['area_source_id'] for l in links
               if l['is_primary'] == 'true')
for sid, n in expect.items():
    check(f"count-{sid.split(':')[-1]}-{n}", have[sid] == n,
          str(have[sid]))
# --- fill pins: every M5 fill code -> district ---
fills = {'1304': 'mz:district:vilanculos', '1310': 'mz:district:panda',
 '1312': 'mz:district:zavala', '2106': 'mz:district:nhamatanda',
 '2112': 'mz:district:muanza', '2114': 'mz:district:marromeu',
 '2309': 'mz:district:tsangano', '2312': 'mz:district:zumbo',
 '2401': 'mz:district:nicoadala', '2402': 'mz:district:namacurra',
 '2405': 'mz:district:pebane', '2409': 'mz:district:morrumbala',
 '2413': 'mz:district:mopeia', '2415': 'mz:district:namarroi',
 '3104': 'mz:district:mossuril', '3107': 'mz:district:muecate',
 '3110': 'mz:district:murrupula', '3113': 'mz:district:nacala-a-velha',
 '3115': 'mz:district:erati', '3119': 'mz:district:ribaue',
 '3202': 'mz:district:metuge', '3205': 'mz:district:metuge',
 '3209': 'mz:district:namuno', '3213': 'mz:district:quissanga',
 '3215': 'mz:district:muidumbe', '3218': 'mz:district:nangade',
 '3219': 'mz:district:palma', '3302': 'mz:district:sanga',
 '3307': 'mz:district:metarica', '3308': 'mz:district:n-gauma',
 '3310': 'mz:district:nipepe', '3311': 'mz:district:muembe'}
link = {}
for l in links:
    if l['is_primary'] == 'true':
        link[l['postcode']] = l['area_source_id']
for pc, sid in fills.items():
    check(f'fill-{pc}', link.get(pc) == sid, str(link.get(pc)))
# UPU mozEn anchors: 1100 Maputo, 3314 Mecula.
check('upu-1100', link.get('1100') == 'mz:district:kampfumo')
check('upu-3314', link.get('3314') == 'mz:district:mecula')
# Codeless by design: Xai-Xai (cell mixes city + Chonguene district),
# Mogincual (3111 splits Mossuril/Mogincual/Liupo), Nampula rural
# (3100 serves city + Rapale), KaMaxaquene (no Maputo cell rows).
for sid in ['mz:district:xai-xai', 'mz:district:mogincual',
            'mz:district:nampula', 'mz:district:kamaxaquene']:
    check(f'codeless-{sid.split(":")[-1]}',
          not [l for l in links if l['area_source_id'] == sid])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
