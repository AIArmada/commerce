import csv, os, sys
# Mali gate. No postcode system (UPU do-not-require + mliEn
# profile with no postcode section + no GeoNames ML dump + List
# of postal codes 'no codes'). 2023-restructure era: 19 regions
# + Bamako district + 159 cercles per Laws 2023-006/007.
# M5 revisit: 1 fix PROPOSED (Niéma -> Niéna, name only, slug
# stable); gate encodes post-fix state.
# Run from repo root: python3 docs/agents/audit/gate_ml.py
A = './packages/addressing/resources/geography/mali-address-areas.csv'
C = './packages/addressing/resources/geography/mali-postal-codes.csv'
L = './packages/addressing/resources/geography/mali-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-179', len(rows) == 179, str(len(rows)))
check('regions-19', sum(1 for r in rows if r['type'] == 'region') == 19)
check('district-1', sum(1 for r in rows if r['type'] == 'district') == 1)
check('cercles-159', sum(1 for r in rows if r['type'] == 'cercle') == 159)
# National 2023 numbering (Laws 2023-006/007, citypopulation
# 01-19 dirs): Bamako BKO per ISO ML-BKO; 09 = Taoudénit and
# 10 = Ménaka, deliberately diverging from ISO 3166-2:ML which
# still assigns ML-9 to Ménaka and ML-10 to Taoudénit.
l1 = {
 'ml:district:bamako': ('Bamako', 'BKO'),
 'ml:region:bandiagara': ('Bandiagara', '19'),
 'ml:region:bougouni': ('Bougouni', '15'),
 'ml:region:dioila': ('Dioila', '13'),
 'ml:region:douentza': ('Douentza', '18'),
 'ml:region:gao': ('Gao', '7'),
 'ml:region:kayes': ('Kayes', '1'),
 'ml:region:kidal': ('Kidal', '8'),
 'ml:region:kita': ('Kita', '12'),
 'ml:region:koulikoro': ('Koulikoro', '2'),
 'ml:region:koutiala': ('Koutiala', '16'),
 'ml:region:menaka': ('Ménaka', '10'),
 'ml:region:mopti': ('Mopti', '5'),
 'ml:region:nara': ('Nara', '14'),
 'ml:region:nioro': ('Nioro', '11'),
 'ml:region:san': ('San', '17'),
 'ml:region:segou': ('Ségou', '4'),
 'ml:region:sikasso': ('Sikasso', '3'),
 'ml:region:taoudenit': ('Taoudénit', '9'),
 'ml:region:tombouctou': ('Tombouctou', '6'),
}
for sid, (name, code) in l1.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full region -> cercles table per Cercles-of-Mali oracle (159,
# counts exact per region). Fix encoded: Niéna (CSV Niéma;
# oracle Nièna + fr.wiki Niéna + citypop 0307__niéna). Keeps:
# Anéfif (CSV + citypop 0804; oracle Anétif probable typo),
# Dialassagou (2v2 stalemate vs Diallassagou), Taoudenni
# (CSV + citypop), Tombouctou (French vs Timbuktu exonym),
# Inlamawane (Fanfi) qualifier, Kadiana (CSV + citypop vs
# oracle Kadiala).
table = {
 'ml:region:bandiagara': ['Bandiagara', 'Bankass',
     'Dialassagou', 'Kani', 'Kendié', 'Koro', 'Ningari',
     'Sangha', 'Sokoura'],
 'ml:region:bougouni': ['Bougouni', 'Dogo',
     'Fakola', 'Garalo', 'Kadiana', 'Kolondiéba', 'Koumantou',
     'Ouéléssébougou', 'Sélingué', 'Yanfolila'],
 'ml:region:dioila': ['Banco', 'Béléko',
     'Dioïla', 'Fana', 'Massigui', 'Ména'],
 'ml:region:douentza': ['Boni', 'Boré',
     'Douentza', 'Hombori', 'Mondoro', "N'Gouma"],
 'ml:region:gao': ['Almoustrat', 'Ansongo',
     'Bamba', 'Bourem', 'Djebock', 'Ersane', 'Gabéro',
     'Gao', 'Kassambéré', "N'Tillit", 'Ouattagouna', 'Soni Aliber',
     'Tabankort', 'Talataye', 'Tessit', 'Tin-Aouker'],
 'ml:region:kayes': ['Ambidédi', 'Aourou',
     'Bafoulabé', 'Diamou', 'Kayes', 'Kéniéba', 'Oussoubidiagna',
     'Sadiola', 'Ségala', 'Yélimané'],
 'ml:region:kidal': ['Abeïbara', 'Achibogho',
     'Aguel-Hoc', 'Anéfif', 'Kidal', 'Takalote', 'Tessalit',
     'Timétrine', 'Tin-Essako'],
 'ml:region:kita': ['Kita', 'Sagabari',
     'Sirakoro', 'Sébékoro', 'Séféto', 'Toukoto'],
 'ml:region:koulikoro': ['Banamba', 'Kangaba',
     'Kati', 'Kolokani', 'Koulikoro', 'Nyamina', 'Néguéla',
     'Siby'],
 'ml:region:koutiala': ['Konséguéla', 'Kouniana',
     'Koury', 'Koutiala', "M'Péssoba", 'Molobala', 'Yorosso',
     'Zangasso'],
 'ml:region:menaka': ['Andéramboukane', 'Anouzagrène',
     'Inlamawane (Fanfi)', 'Inékar', 'Ménaka', 'Tidermène'],
 'ml:region:mopti': ['Djenné', 'Konna',
     'Korientzé', 'Mopti', 'Sofara', 'Toguéré-Coumbé', 'Ténenkou',
     'Youwarou'],
 'ml:region:nara': ['Ballé', 'Dilly',
     'Fallou', 'Guiré', 'Mourdiah', 'Nara'],
 'ml:region:nioro': ['Béma', 'Diangounté Camara',
     'Diéma', 'Nioro du Sahel', 'Sandaré', 'Troungoumbé'],
 'ml:region:san': ['Fangasso', 'Kimparana',
     'Mandiakuy', 'San', 'Sy', 'Tominian', 'Yangasso'],
 'ml:region:segou': ['Barouéli', 'Bla',
     'Dioro', 'Farako', 'Macina', 'Markala', 'Nampala',
     'Niono', 'Sarro', 'Sokolo', 'Ségou'],
 'ml:region:sikasso': ['Dandéresso', 'Kadiolo',
     'Kignan', 'Kléla', 'Lobougoula', 'Loulouni', 'Niéna',
     'Sikasso'],
 'ml:region:taoudenit': ['Achouratt', 'Al-Ourche',
     'Araouane', 'Boudje-Béha', 'Foum-Elba', 'Taoudenni'],
 'ml:region:tombouctou': ['Bambara Maoudé', 'Ber',
     'Bintagoungou', 'Diré', 'Gargando', 'Gossi', 'Goundam',
     'Gourma-Rharous', 'Léré', 'Niafunké', 'Saraféré', 'Tombouctou',
     'Tonka'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
    check(f"{par.split(':')[-1]}-{len(names)}", len(have) == len(names), str(len(have)))
if ok: print('PASS all 19 region mappings (159 cercles)')
check('bamako-terminal', not [r for r in rows if r['parent_source_id'] == 'ml:district:bamako'])
# Cercle codes: 4-digit, region prefix + gap-free sequence,
# eponymous cercle first.
bad = [r['source_id'] for r in rows if r['level'] == '2'
       and not (len(r['code']) == 4 and r['code'].isdigit())]
check('cercle-codes-4digit', not bad, str(bad[:3]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
